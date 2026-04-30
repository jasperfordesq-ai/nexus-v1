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
use Illuminate\Support\Facades\Hash;
use Tests\Laravel\TestCase;

/**
 * SOC13 — when an OAuth provider returns an email that already matches a
 * verified user in the same tenant, the identity is linked to the existing
 * user rather than creating a duplicate account.
 */
class ExistingEmailCallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_verified_email_links_identity_to_existing_user(): void
    {
        $email = 'oauth_existing_' . uniqid() . '@example.com';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $service = app(SocialAuthService::class);
        $providerUserId = 'g_' . uniqid();
        $providerUser = new StubSocialiteUser($providerUserId, $email, 'Ada Lovelace');

        $result = $service->findOrCreateFromOauth('google', $providerUser, $this->testTenantId);

        $this->assertFalse($result['is_new']);
        $this->assertSame((int) $user->id, (int) $result['user']->id);

        // Identity row exists pointing at the existing user
        $row = DB::selectOne(
            'SELECT user_id FROM oauth_identities WHERE provider = ? AND provider_user_id = ?',
            ['google', $providerUserId]
        );
        $this->assertNotNull($row);
        $this->assertSame((int) $user->id, (int) $row->user_id);

        // No duplicate user was created
        $count = DB::selectOne(
            'SELECT COUNT(*) AS c FROM users WHERE tenant_id = ? AND email = ?',
            [$this->testTenantId, $email]
        );
        $this->assertSame(1, (int) $count->c);
    }
}
