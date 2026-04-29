<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\PartnerApi;

use App\Services\PartnerApi\PartnerApiAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * AG60 — Tests the OAuth2 client_credentials grant.
 */
class OAuthTokenTest extends TestCase
{
    use DatabaseTransactions;

    private int $partnerId;
    private string $clientId;
    private string $clientSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partnerId = (int) DB::table('api_partners')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Test Bank',
            'slug' => 'test-bank-' . uniqid(),
            'status' => 'active',
            'is_sandbox' => true,
            'allowed_scopes' => json_encode(['users.read', 'wallet.read']),
            'allowed_ip_cidrs' => json_encode([]),
            'rate_limit_per_minute' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creds = PartnerApiAuthService::issueClientCredentials($this->partnerId);
        $this->clientId = $creds['client_id'];
        $this->clientSecret = $creds['client_secret'];
    }

    public function test_client_credentials_grant_returns_access_token(): void
    {
        $response = $this->postJson('/api/partner/v1/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'scope',
        ]);
        $this->assertSame('bearer', $response->json('token_type'));
        $this->assertGreaterThan(0, (int) $response->json('expires_in'));
    }

    public function test_invalid_client_secret_returns_401(): void
    {
        $response = $this->postJson('/api/partner/v1/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => 'sk_wrong_secret',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('errors.0.code', 'invalid_client');
    }

    public function test_unsupported_grant_type_returns_400(): void
    {
        $response = $this->postJson('/api/partner/v1/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('errors.0.code', 'unsupported_grant_type');
    }
}
