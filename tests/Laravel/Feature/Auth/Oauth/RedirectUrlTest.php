<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Auth\Oauth;

use App\Services\Auth\SocialAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * SOC13 — `GET /v2/auth/oauth/{provider}/redirect`
 *
 * The endpoint must always return a verifiable state token even when Socialite
 * isn't installed (in which case it returns a 503 with a useful error). When
 * Socialite IS installed, it builds a real provider URL.
 */
class RedirectUrlTest extends TestCase
{
    use DatabaseTransactions;

    public function test_redirect_returns_signed_state_token(): void
    {
        $service = app(SocialAuthService::class);
        $result = $service->redirectUrl('google', $this->testTenantId, 'login');

        $this->assertArrayHasKey('state', $result);
        $this->assertNotEmpty($result['state']);
        $this->assertStringContainsString('.', $result['state']); // body.signature
    }

    public function test_redirect_endpoint_responds(): void
    {
        $response = $this->apiGet('/v2/auth/oauth/google/redirect');
        // Either 200 (Socialite installed + valid keys) or 503 (Socialite missing)
        // or 400 (no provider keys configured) — all acceptable in CI.
        $this->assertContains($response->status(), [200, 400, 503]);
    }

    public function test_redirect_rejects_unknown_provider(): void
    {
        $response = $this->apiGet('/v2/auth/oauth/twitter/redirect');
        // Route constraint blocks unsupported providers with 404
        $this->assertSame(404, $response->status());
    }
}
