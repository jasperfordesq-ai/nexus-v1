<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\FederationApiMiddleware;
use App\Models\User;
use App\Services\SafeguardingInteractionPolicy;
use App\Support\SafeguardingInteractionDecision;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
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

    /**
     * @param int|null $externalPartnerId Server-side partner binding — since the
     *                                    2026-07-10 M3 fix this link (not any
     *                                    client header) decides which partner
     *                                    the key acts as.
     */
    private function createFederationApiKey(array $permissions = ['*'], ?int $externalPartnerId = null): string
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
                'external_partner_id' => $externalPartnerId,
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

    /**
     * @param object|null $claimedPartner When set, sends the (now-untrusted)
     *                                    X-Federation-Partner-ID header claiming
     *                                    to act as that partner.
     */
    private function nativeAuthHeaders(string $apiKey, ?object $claimedPartner = null): array
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $apiKey;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v2/federation/ingest';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if ($claimedPartner !== null) {
            $headers['X-Federation-Partner-ID'] = (string) $claimedPartner->id;
        }

        return $headers;
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

    public function test_native_review_policy_unavailable_returns_retryable_503_without_write(): void
    {
        $externalPartner = $this->setupPartner('nexus', $this->testTenantId);
        $apiKey = $this->createFederationApiKey(['*'], (int) $externalPartner->id);
        $receiver = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $externalId = 'native-review-policy-unavailable-' . uniqid();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('evaluateExternalContact')
            ->once()
            ->with(
                (int) $receiver->id,
                $this->testTenantId,
                "partner:{$externalPartner->id}:sender:701",
                'external_federated_review',
            )
            ->andReturn(new SafeguardingInteractionDecision(
                status: SafeguardingInteractionDecision::UNAVAILABLE,
                code: 'SAFEGUARDING_POLICY_UNAVAILABLE',
                recipientTenantId: $this->testTenantId,
                purposeCode: 'safeguarded_member_contact',
                scopeType: 'tenant',
                scopeIdentifier: '',
                canRequestCoordinator: true,
            ));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/federation/ingest/reviews', [
            'external_id' => $externalId,
            'rating' => 5,
            'receiver_id' => $receiver->id,
            'reviewer_external_id' => 701,
            'reviewer_tenant_id' => 999,
        ], $this->nativeAuthHeaders($apiKey));

        $response->assertStatus(503);
        $response->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertDatabaseMissing('reviews', [
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $externalPartner->id,
            'external_id' => $externalId,
        ]);
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
        $externalPartner = $this->setupPartner('nexus', $this->testTenantId);
        $apiKey = $this->createFederationApiKey(['*'], (int) $externalPartner->id);
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
            $this->nativeAuthHeaders($apiKey)
        );

        $response->assertOk();
        $this->assertSame('rejected', $response->json('data.result.status'));
        $this->assertDatabaseMissing('federation_listings', [
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $externalPartner->id,
            'external_id' => $externalId,
        ]);
    }

    /**
     * 2026-07-10 audit M3: the acting partner used to be chosen from the
     * client-supplied X-Federation-Partner-ID header (constrained only to the
     * same tenant), so a key issued for partner A could impersonate partner B
     * and inherit B's allow_* permissions. The header must now be ignored:
     * identity comes only from the key row's external_partner_id binding.
     */
    public function test_partner_id_header_cannot_impersonate_another_partner(): void
    {
        $partnerA = $this->setupPartner('nexus', $this->testTenantId);
        $partnerB = $this->setupPartner('nexus', $this->testTenantId);
        // Partner A may not push listings; partner B may.
        DB::table('federation_external_partners')
            ->where('id', $partnerA->id)
            ->update(['allow_listing_search' => 0]);

        // Key bound to partner A, header claims partner B.
        $apiKey = $this->createFederationApiKey(['*'], (int) $partnerA->id);
        $externalId = 'native-impersonation-' . uniqid();
        $response = $this->apiPost(
            '/v2/federation/ingest/listings',
            [
                'external_id' => $externalId,
                'title' => 'Impersonated listing',
                'description' => 'Must be rejected under partner A permissions.',
            ],
            $this->nativeAuthHeaders($apiKey, $partnerB)
        );

        $response->assertOk();
        $this->assertSame('rejected', $response->json('data.result.status'),
            'Key bound to partner A must act as A (listings disallowed) regardless of the header');
        foreach ([$partnerA->id, $partnerB->id] as $pid) {
            $this->assertDatabaseMissing('federation_listings', [
                'tenant_id' => $this->testTenantId,
                'external_partner_id' => $pid,
                'external_id' => $externalId,
            ]);
        }
    }

    public function test_unlinked_key_cannot_claim_partner_via_header(): void
    {
        $partnerB = $this->setupPartner('nexus', $this->testTenantId);

        // Key with NO partner binding, header claims partner B (all-allowed).
        $apiKey = $this->createFederationApiKey(['*'], null);
        $externalId = 'native-unlinked-claim-' . uniqid();
        $response = $this->apiPost(
            '/v2/federation/ingest/listings',
            [
                'external_id' => $externalId,
                'title' => 'Unlinked-key listing',
                'description' => 'Must fail closed: no partner, no allow flags.',
            ],
            $this->nativeAuthHeaders($apiKey, $partnerB)
        );

        $response->assertOk();
        $this->assertSame('rejected', $response->json('data.result.status'),
            'An unlinked key resolves no partner and must fail closed');
        $this->assertDatabaseMissing('federation_listings', [
            'tenant_id' => $this->testTenantId,
            'external_partner_id' => $partnerB->id,
            'external_id' => $externalId,
        ]);
    }

    public function test_native_ingest_log_redacts_sensitive_request_fields(): void
    {
        $externalPartner = $this->setupPartner('nexus', $this->testTenantId);
        $apiKey = $this->createFederationApiKey(['*'], (int) $externalPartner->id);
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
            $this->nativeAuthHeaders($apiKey)
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
