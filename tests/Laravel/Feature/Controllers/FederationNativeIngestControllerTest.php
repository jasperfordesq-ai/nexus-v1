<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\FederationApiMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for FederationNativeIngestController.
 *
 * Inbound entity push endpoints (Nexus native protocol) — gated by
 * federation.api middleware, not Sanctum. Invalid credentials/payload
 * must produce a 4xx, never 500.
 */
class FederationNativeIngestControllerTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    protected function setUp(): void
    {
        parent::setUp();
        FederationApiMiddleware::reset();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        FederationApiMiddleware::reset();
        parent::tearDown();
    }

    private function createFederationApiKey(array $permissions = ['*']): string
    {
        $this->enableFederationForTenant($this->testTenantId);

        $apiKey = 'native-ingest-' . bin2hex(random_bytes(8));

        try {
            DB::table('federation_api_keys')->insert([
                'tenant_id' => $this->testTenantId,
                'name' => 'Native Ingest Test Key',
                'key_hash' => hash('sha256', $apiKey),
                'key_prefix' => substr($apiKey, 0, 8),
                'platform_id' => 'native-ingest-' . bin2hex(random_bytes(4)),
                'permissions' => json_encode($permissions),
                'rate_limit' => 1000,
                'status' => 'active',
                'signing_enabled' => 0,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'hourly_request_count' => 0,
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('federation_api_keys not available: ' . $e->getMessage());
        }

        return $apiKey;
    }

    private function nativeAuthHeaders(string $apiKey, object $externalPartner): array
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $apiKey;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v2/federation/ingest';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'X-Federation-Partner-ID' => (string) $externalPartner->id,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function test_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\FederationNativeIngestController::class));
    }

    public function test_reviews_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/reviews', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_listings_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/listings', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_events_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/events', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_groups_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/groups', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_connections_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/connections', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_volunteering_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/volunteering', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_members_sync_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/members/sync', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_reviews_rejects_garbage_body(): void
    {
        $response = $this->apiPost('/v2/federation/ingest/reviews', ['garbage' => str_repeat('x', 200)]);
        $this->assertLessThan(500, $response->status());
    }

    // ============================================================
    // DEEP SECURITY TESTS
    // ============================================================

    /**
     * Every ingest endpoint MUST reject unauthenticated traffic with 4xx.
     * Iterated across all seven endpoints so we detect a regression
     * that accidentally removes federation.api middleware from any route.
     */
    public function test_all_ingest_endpoints_reject_unauthenticated(): void
    {
        $endpoints = [
            '/v2/federation/ingest/reviews',
            '/v2/federation/ingest/listings',
            '/v2/federation/ingest/events',
            '/v2/federation/ingest/groups',
            '/v2/federation/ingest/connections',
            '/v2/federation/ingest/volunteering',
            '/v2/federation/ingest/members/sync',
        ];
        foreach ($endpoints as $path) {
            $response = $this->apiPost($path, ['external_id' => 'x', 'rating' => 5]);
            $this->assertContains(
                $response->status(),
                [400, 401, 403, 404, 422],
                "Endpoint {$path} returned {$response->status()} — must be 4xx when unauthenticated"
            );
            $this->assertNotEquals(200, $response->status());
            $this->assertNotEquals(202, $response->status());
        }
    }

    /**
     * Bogus Bearer tokens must not be accepted as valid federation partners.
     */
    public function test_bogus_bearer_token_rejected(): void
    {
        $response = $this->apiPost(
            '/v2/federation/ingest/reviews',
            ['external_id' => 'r1', 'rating' => 5],
            ['Authorization' => 'Bearer totally-fake-' . uniqid()]
        );
        $this->assertContains($response->status(), [401, 403]);
    }

    public function test_disabled_external_partner_permission_rejects_native_listing_push_without_shadow_write(): void
    {
        $apiKey = $this->createFederationApiKey();
        $externalPartner = $this->setupPartner('nexus', $this->testTenantId);
        DB::table('federation_external_partners')
            ->where('id', $externalPartner->id)
            ->update(['allow_listing_search' => 0]);

        $externalId = 'native-denied-listing-' . uniqid();
        $response = $this->apiPost(
            '/v2/federation/ingest/listings',
            [
                'external_id' => $externalId,
                'title' => 'Native denied listing',
                'description' => 'This should not be mirrored.',
            ],
            $this->nativeAuthHeaders($apiKey, $externalPartner)
        );

        $response->assertOk();
        $this->assertSame('rejected', $response->json('data.result.status'));
        $this->assertDatabaseMissing('federation_listings', [
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $externalPartner->id,
            'external_id' => $externalId,
        ]);
    }

    public function test_native_ingest_log_redacts_sensitive_request_fields(): void
    {
        $apiKey = $this->createFederationApiKey();
        $externalPartner = $this->setupPartner('nexus', $this->testTenantId);
        $externalId = 'native-redacted-member-' . uniqid();

        $response = $this->apiPost(
            '/v2/federation/ingest/members/sync',
            [
                'external_id' => $externalId,
                'display_name' => 'Visible Name',
                'bio' => 'native private biography',
                'avatar_url' => 'https://example.test/native-private-avatar.png',
                'password' => 'native-secret-password',
                'metadata' => ['token' => 'native-nested-token'],
            ],
            $this->nativeAuthHeaders($apiKey, $externalPartner)
        );

        $response->assertOk();

        $log = DB::table('federation_external_partner_logs')
            ->where('partner_id', $externalPartner->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($log);
        $body = (string) $log->request_body;
        $this->assertStringContainsString('[REDACTED]', $body);
        $this->assertStringNotContainsString('native private biography', $body);
        $this->assertStringNotContainsString('native-private-avatar.png', $body);
        $this->assertStringNotContainsString('native-secret-password', $body);
        $this->assertStringNotContainsString('native-nested-token', $body);
        $this->assertStringNotContainsString('native private biography', (string) $log->response_body);
        $this->assertStringNotContainsString('native private biography', (string) $log->error_message);
    }

    /**
     * Unknown HMAC signature rejected — sanity that HMAC path also protects us.
     */
    public function test_bogus_hmac_signature_rejected(): void
    {
        $response = $this->call(
            'POST',
            '/api/v2/federation/ingest/reviews',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->withTenantHeader([
                'Content-Type' => 'application/json',
                'X-Federation-Signature' => str_repeat('b', 64),
                'X-Federation-Timestamp' => (string) time(),
            ])),
            json_encode(['external_id' => 'r1', 'rating' => 5])
        );
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    /**
     * Malformed JSON must not 500 — middleware or controller catches it first.
     */
    public function test_malformed_json_never_500_across_endpoints(): void
    {
        $endpoints = [
            '/v2/federation/ingest/reviews',
            '/v2/federation/ingest/listings',
            '/v2/federation/ingest/events',
            '/v2/federation/ingest/members/sync',
        ];
        foreach ($endpoints as $path) {
            $response = $this->call(
                'POST',
                '/api' . $path,
                [],
                [],
                [],
                $this->transformHeadersToServerVars($this->withTenantHeader([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer anything',
                ])),
                '{malformed'
            );
            $this->assertLessThan(500, $response->getStatusCode(),
                "Endpoint {$path} 5xx'd on malformed JSON");
        }
    }

    /**
     * Tenant isolation: a request with X-Tenant-ID pointing to tenant B but
     * (hypothetically) authenticated for tenant A must NOT be able to write
     * to tenant B. Since we can't mint a valid partner in the test schema,
     * we at least verify that without valid partner credentials, even a
     * mismatched X-Tenant-ID header does not permit ingestion — the 4xx
     * rejection is the isolation boundary.
     */
    public function test_tenant_header_cannot_bypass_partner_auth(): void
    {
        $response = $this->postJson(
            '/api/v2/federation/ingest/reviews',
            ['external_id' => 'r1', 'rating' => 5, 'tenant_id' => 999],
            ['X-Tenant-ID' => '999', 'Accept' => 'application/json']
        );
        $this->assertContains($response->status(), [400, 401, 403, 422]);
    }

    /**
     * Validation happens BEFORE any DB writes: unauthenticated POST to an
     * ingest endpoint must produce ZERO new rows in federation_external_partner_logs.
     */
    public function test_unauthenticated_ingest_writes_no_logs(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('federation_external_partner_logs')) {
            $this->markTestSkipped('federation_external_partner_logs table not in test schema');
        }
        $before = \Illuminate\Support\Facades\DB::table('federation_external_partner_logs')->count();
        $this->apiPost('/v2/federation/ingest/reviews', ['external_id' => 'x', 'rating' => 5]);
        $after = \Illuminate\Support\Facades\DB::table('federation_external_partner_logs')->count();
        $this->assertSame($before, $after,
            'Unauthenticated request wrote to partner_logs — auth is enforced AFTER logging (SEC BUG)');
    }

    /**
     * Valid-auth partner injection: constructing a working partner row
     * requires encrypting a signing_secret with Laravel's app key and
     * matching tenant seeding — out of scope for smoke tests.
     */
    public function test_valid_partner_happy_path(): void
    {
        $this->markTestSkipped(
            'Valid-partner ingest requires full federation_api_keys + partner fixtures;'
            . ' covered by integration tests, not unit/feature smoke.'
        );
    }
}
