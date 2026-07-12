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
use App\Services\TenantSettingsService;
use App\Services\TenantFeatureConfig;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the tenant-scoped "last sign-in method" guard.
 */
final class AuthenticationMethodGuardTest extends TestCase
{
    use DatabaseTransactions;

    private string|false $originalOauthEnabled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalOauthEnabled = getenv('OAUTH_ENABLED');
        putenv('OAUTH_ENABLED=true');
        $this->setEnabledSocialProviders(SocialAuthService::SUPPORTED_PROVIDERS);
    }

    protected function tearDown(): void
    {
        app(TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);
        if ($this->originalOauthEnabled === false) {
            putenv('OAUTH_ENABLED');
        } else {
            putenv('OAUTH_ENABLED=' . $this->originalOauthEnabled);
        }

        parent::tearDown();
    }

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

    public function test_only_the_password_hash_used_by_login_counts_as_an_alternative(): void
    {
        $currentUser = $this->createPasswordlessUser();
        DB::table('users')->where('id', $currentUser->id)->update([
            'password_hash' => password_hash('current-password', PASSWORD_BCRYPT),
        ]);

        $legacyUser = $this->createPasswordlessUser();
        DB::table('users')->where('id', $legacyUser->id)->update([
            // OAuth provisioning historically filled this legacy column with
            // a random value the member never knew. It is not read by login.
            'password' => password_hash('unknown-random-value', PASSWORD_BCRYPT),
        ]);

        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $currentUser->id,
            $this->testTenantId
        ));
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            (int) $legacyUser->id,
            $this->testTenantId
        ));
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            (int) $legacyUser->id,
            $this->testTenantId,
            'google'
        ));
    }

    public function test_disabled_social_identities_do_not_count_as_usable_alternatives(): void
    {
        $user = $this->createPasswordlessUser();
        $userId = (int) $user->id;
        $this->insertOauthIdentity($userId, $this->testTenantId, 'facebook');

        $this->setEnabledSocialProviders(['google']);
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            $userId,
            $this->testTenantId
        ));

        $this->setEnabledSocialProviders(['facebook']);
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            $userId,
            $this->testTenantId
        ));

        putenv('OAUTH_ENABLED=false');
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            $userId,
            $this->testTenantId
        ));
        putenv('OAUTH_ENABLED=true');
    }

    public function test_only_enabled_tenant_sso_identities_count_as_usable_alternatives(): void
    {
        $user = $this->createPasswordlessUser();
        $userId = (int) $user->id;
        $providerKey = 'guard-' . substr(bin2hex(random_bytes(6)), 0, 12);
        $identityProvider = "sso:{$this->testTenantId}:{$providerKey}";

        DB::table('tenant_sso_providers')->insert([
            'tenant_id' => $this->testTenantId,
            'provider_key' => $providerKey,
            'display_name' => 'Guard test SSO',
            'issuer_url' => 'https://idp.example.test',
            'client_id' => 'guard-test-client',
            'is_enabled' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertOauthIdentity($userId, $this->testTenantId, $identityProvider);

        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            $userId,
            $this->testTenantId
        ));

        DB::table('tenant_sso_providers')
            ->where('tenant_id', $this->testTenantId)
            ->where('provider_key', $providerKey)
            ->update(['is_enabled' => 1]);

        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToPasskeys(
            $userId,
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

    public function test_oauth_removal_counts_only_currently_usable_passkeys(): void
    {
        config([
            'webauthn.authentication_enabled' => true,
            'webauthn.rp_id' => 'localhost',
            'webauthn.allowed_origins' => ['http://localhost'],
        ]);
        $user = $this->createPasswordlessUser();
        $userId = (int) $user->id;
        $this->insertOauthIdentity($userId, $this->testTenantId, 'google');
        $this->insertPasskey($userId, $this->testTenantId, 'policy-passkey');
        DB::table('webauthn_credentials')
            ->where('user_id', $userId)
            ->update(['rp_id' => 'localhost']);

        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['biometric_login'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        $this->assertTrue(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        config(['webauthn.authentication_enabled' => false]);
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        config(['webauthn.authentication_enabled' => true]);
        $features['biometric_login'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
            $userId,
            $this->testTenantId,
            'google'
        ));

        $features['biometric_login'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features, JSON_THROW_ON_ERROR),
        ]);
        DB::table('webauthn_credentials')
            ->where('user_id', $userId)
            ->update(['rp_id' => 'retired.example.test']);
        $this->assertFalse(AuthenticationMethodGuard::hasAlternativeToOauthProvider(
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
        $data = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'credential_id' => $credentialId . '-' . uniqid('', true),
            'public_key' => 'test-public-key',
            'device_name' => 'Test passkey',
            'authenticator_type' => 'platform',
            'created_at' => now(),
        ];
        if (Schema::hasColumn('webauthn_credentials', 'user_handle')) {
            $data['user_handle'] = rtrim(strtr(base64_encode(hash(
                'sha256',
                $userId . ':' . $tenantId,
                true
            )), '+/', '-_'), '=');
        }

        DB::table('webauthn_credentials')->insert($data);
    }

    /** @param list<string> $providers */
    private function setEnabledSocialProviders(array $providers): void
    {
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'auth.oauth.enabled_providers',
            json_encode($providers, JSON_THROW_ON_ERROR),
            'json'
        );
    }
}
