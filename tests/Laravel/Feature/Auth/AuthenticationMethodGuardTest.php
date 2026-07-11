<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Auth;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\Auth\AuthenticationMethodGuard;
use App\Services\Auth\SocialAuthService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the tenant-scoped "last sign-in method" guard.
 */
final class AuthenticationMethodGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_passkey_removal_requires_a_password_or_tenant_scoped_oauth_identity(): void
    {
        $user = $this->createPasswordlessUser();

        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $user->id,
            $this->testTenantId
        ));

        // A passkey cannot count as its own alternative when all passkeys are removed.
        $this->insertPasskey((int) $user->id, $this->testTenantId, 'current-passkey');
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $user->id,
            $this->testTenantId
        ));

        // Corrupt/cross-tenant identity data must not authorize a removal.
        $this->insertOauthIdentity((int) $user->id, 999, 'google');
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $user->id,
            $this->testTenantId
        ));

        $this->insertOauthIdentity((int) $user->id, $this->testTenantId, 'facebook');
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $user->id,
            $this->testTenantId
        ));
    }

    public function test_both_current_and_legacy_password_columns_count_as_alternatives(): void
    {
        $currentUser = $this->createPasswordlessUser();
        DB::table('users')->where('id', $currentUser->id)->update([
            'password_hash' => password_hash('current-password', PASSWORD_BCRYPT),
        ]);

        $legacyUser = $this->createPasswordlessUser();
        DB::table('users')->where('id', $legacyUser->id)->update([
            'password' => password_hash('legacy-password', PASSWORD_BCRYPT),
        ]);

        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $currentUser->id,
            $this->testTenantId
        ));
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $legacyUser->id,
            $this->testTenantId
        ));
    }

    public function test_oauth_removal_counts_only_tenant_scoped_alternative_methods(): void
    {
        $user = $this->createPasswordlessUser();
        $userId = (int) $user->id;
        $this->insertOauthIdentity($userId, $this->testTenantId, 'google');

        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        $this->insertPasskey($userId, 999, 'other-tenant-passkey');
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        $this->insertPasskey($userId, $this->testTenantId, 'same-tenant-passkey');
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        DB::table('webauthn_credentials')->where('user_id', $userId)->delete();
        $this->insertOauthIdentity($userId, 999, 'facebook');
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        $this->insertOauthIdentity($userId, $this->testTenantId, 'apple');
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));
    }

    public function test_missing_or_wrong_tenant_user_fails_closed(): void
    {
        $user = $this->createPasswordlessUser();

        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $user->id,
            999
        ));
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            999_999_999,
            $this->testTenantId,
            'google'
        ));
    }

    public function test_social_unlinks_serialize_on_the_user_and_preserve_the_final_method(): void
    {
        $user = $this->createPasswordlessUser();
        $userId = (int) $user->id;
        $this->insertOauthIdentity($userId, $this->testTenantId, 'google');
        $this->insertOauthIdentity($userId, $this->testTenantId, 'facebook');
        TenantContext::setById($this->testTenantId);

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $service = app(SocialAuthService::class);
        $service->unlinkProvider($userId, 'google');

        try {
            $service->unlinkProvider($userId, 'facebook');
            $this->fail('Removing the final OAuth sign-in method should have been blocked.');
        } catch (\RuntimeException $exception) {
            // The second serialized removal sees the first deletion.
            $this->assertSame(__('api.cannot_remove_last_sign_in_method'), $exception->getMessage());
        }

        $this->assertDatabaseMissing('oauth_identities', [
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'provider' => 'google',
        ]);
        $this->assertDatabaseHas('oauth_identities', [
            'user_id' => $userId,
            'tenant_id' => $this->testTenantId,
            'provider' => 'facebook',
        ]);

        // MariaDB/PostgreSQL compile lockForUpdate() as FOR UPDATE. Both unlink
        // attempts must request the same user-row lock before counting methods.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'pgsql'], true)) {
            $lockingUserQueries = array_values(array_filter(
                $queries,
                static fn (string $sql): bool => str_contains($sql, 'users')
                    && str_contains($sql, 'for update')
            ));

            $this->assertCount(2, $lockingUserQueries);
        }
    }

    private function createPasswordlessUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'auth-method-guard-' . uniqid('', true) . '@example.test',
            'password_hash' => null,
            'status' => 'active',
            'is_approved' => true,
        ]);

        DB::table('users')->where('id', $user->id)->update([
            'password' => null,
            'password_hash' => null,
        ]);

        return $user;
    }

    private function insertOauthIdentity(int $userId, int $tenantId, string $provider): void
    {
        DB::table('oauth_identities')->insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'provider_user_id' => $provider . '-' . uniqid('', true),
            'provider_email' => $provider . '-' . uniqid('', true) . '@example.test',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPasskey(int $userId, int $tenantId, string $credentialId): void
    {
        DB::table('webauthn_credentials')->insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'credential_id' => $credentialId . '-' . uniqid('', true),
            'public_key' => 'test-public-key',
            'device_name' => 'Test passkey',
            'authenticator_type' => 'platform',
            'created_at' => now(),
        ]);
    }
}
