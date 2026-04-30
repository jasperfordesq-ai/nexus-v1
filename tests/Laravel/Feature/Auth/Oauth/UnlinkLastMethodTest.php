<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Auth\Oauth;

use App\Models\User;
use App\Services\Auth\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * SOC13 — `unlinkProvider()` refuses to delete the user's only remaining
 * sign-in method (no password, no passkey, no other OAuth identity).
 */
class UnlinkLastMethodTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unlink_blocks_when_oauth_is_only_auth_method(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'oauth_only_' . uniqid() . '@example.com',
            'password_hash' => '', // no password
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        // Insert one OAuth identity directly so we don't depend on Socialite.
        DB::statement(
            'INSERT INTO oauth_identities
                (user_id, tenant_id, provider, provider_user_id, provider_email, linked_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
            [$user->id, $this->testTenantId, 'google', 'g_only_' . uniqid(), $user->email]
        );

        // Force password to NULL/empty in case the factory set a value
        DB::table('users')->where('id', $user->id)->update(['password_hash' => '']);

        $service = app(SocialAuthService::class);

        $this->expectException(\RuntimeException::class);
        $service->unlinkProvider((int) $user->id, 'google');
    }

    public function test_unlink_allowed_when_password_exists(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'oauth_with_pw_' . uniqid() . '@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        DB::statement(
            'INSERT INTO oauth_identities
                (user_id, tenant_id, provider, provider_user_id, provider_email, linked_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
            [$user->id, $this->testTenantId, 'google', 'g_pw_' . uniqid(), $user->email]
        );

        $service = app(SocialAuthService::class);
        $service->unlinkProvider((int) $user->id, 'google');

        $remaining = DB::table('oauth_identities')
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->count();
        $this->assertSame(0, $remaining);
    }
}
