<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Auth\Oauth;

use App\Models\User;
use App\Services\Auth\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private const BROWSER_VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
    private const BROWSER_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    public function test_oauth_callback_code_is_single_use(): void
    {
        $service = app(SocialAuthService::class);

        $code = $service->issueCallbackCode(
            'short-lived-access-token',
            'google',
            false,
            $this->testTenantId,
            self::BROWSER_CHALLENGE,
            'rotating-refresh-token',
            900,
            2592000
        );

        $payload = $service->consumeCallbackCode($code, self::BROWSER_VERIFIER);
        $this->assertSame('short-lived-access-token', $payload['token']);
        $this->assertSame('rotating-refresh-token', $payload['refresh_token']);
        $this->assertSame(900, $payload['expires_in']);
        $this->assertSame('google', $payload['provider']);
        $this->assertSame($this->testTenantId, $payload['tenant_id']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth callback code is invalid or expired.');
        $service->consumeCallbackCode($code, self::BROWSER_VERIFIER);
    }

    public function test_link_identity_is_not_mutated_until_the_browser_verifier_is_proven(): void
    {
        $service = app(SocialAuthService::class);
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email_verified_at' => now(),
            'status' => 'active',
            'is_approved' => 1,
        ]);
        $startedAt = time();
        $providerUserId = 'custom-domain-link-subject';
        $identityLink = [
            'provider' => 'google',
            'provider_user_id' => $providerUserId,
            'provider_email' => 'linked@example.test',
            'avatar_url' => null,
            'raw_payload' => ['sub' => $providerUserId],
            'authentication_started_at' => $startedAt,
            'expected_verified_email' => null,
        ];

        $issuance = $service->issuePendingLinkCallbackCode(
            (int) $user->id,
            $this->testTenantId,
            'google',
            $startedAt,
            self::BROWSER_CHALLENGE,
            $identityLink
        );
        $code = (string) $issuance['callback_code'];

        $this->assertSame(0, DB::table('oauth_identities')
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->count());

        try {
            $service->consumeCallbackCode($code, str_repeat('A', 43));
            self::fail('A different browser must not complete a pending account link.');
        } catch (\RuntimeException $e) {
            $this->assertSame('OAuth callback code is invalid or expired.', $e->getMessage());
        }

        $this->assertSame(0, DB::table('oauth_identities')
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->count());

        $payload = $service->consumeCallbackCode($code, self::BROWSER_VERIFIER);

        $this->assertNotEmpty($payload['token']);
        $this->assertNotEmpty($payload['refresh_token']);
        $this->assertSame(1, DB::table('oauth_identities')
            ->where('user_id', (int) $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->where('provider', 'google')
            ->where('provider_user_id', $providerUserId)
            ->count());
    }
}
