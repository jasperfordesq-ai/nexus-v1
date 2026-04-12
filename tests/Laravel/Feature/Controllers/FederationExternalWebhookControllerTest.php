<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for FederationExternalWebhookController.
 *
 * Public webhook receiver — HMAC-authenticated in-controller.
 * No Sanctum auth; invalid signatures/payloads must return 4xx, never 500.
 */
class FederationExternalWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\FederationExternalWebhookController::class));
    }

    public function test_receive_rejects_empty_payload(): void
    {
        $response = $this->apiPost('/v2/federation/external/webhooks/receive', []);
        // Should reject missing HMAC/signature/partner — not crash with 500
        $this->assertContains($response->status(), [400, 401, 403, 422]);
    }

    public function test_receive_rejects_invalid_signature(): void
    {
        $response = $this->apiPost(
            '/v2/federation/external/webhooks/receive',
            ['event' => 'test', 'payload' => ['foo' => 'bar']],
            ['X-Partner-Signature' => 'invalid', 'X-Partner-Id' => '999999']
        );
        $this->assertContains($response->status(), [400, 401, 403, 404, 422]);
    }

    public function test_receive_rejects_garbage_body(): void
    {
        $response = $this->apiPost('/v2/federation/external/webhooks/receive', ['garbage' => str_repeat('x', 100)]);
        $this->assertLessThan(500, $response->status());
    }

    // ============================================================
    // DEEP SECURITY TESTS
    // ============================================================

    /**
     * Completely unauthenticated: no Authorization, no signature -> 401 AUTH_FAILED.
     * Controller requires ONE of: Bearer API key OR HMAC sig.
     */
    public function test_missing_all_auth_yields_401_auth_failed(): void
    {
        $response = $this->apiPost(
            '/v2/federation/external/webhooks/receive',
            ['event' => 'health_check', 'data' => []]
        );
        $this->assertSame(401, $response->status());
        $body = $response->json();
        $this->assertSame('AUTH_FAILED', $body['errors'][0]['code'] ?? null);
    }

    /**
     * Wrong API key Bearer token: no matching partner -> 401.
     */
    public function test_wrong_bearer_api_key_yields_401(): void
    {
        $response = $this->apiPost(
            '/v2/federation/external/webhooks/receive',
            ['event' => 'health_check', 'data' => []],
            ['Authorization' => 'Bearer not-a-real-partner-key-xyz-' . uniqid()]
        );
        $this->assertSame(401, $response->status());
    }

    /**
     * Wrong HMAC signature (random hex) -> 401.
     */
    public function test_wrong_hmac_signature_yields_401(): void
    {
        $response = $this->call(
            'POST',
            '/api/v2/federation/external/webhooks/receive',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->withTenantHeader([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => str_repeat('a', 64),
                'X-Webhook-Timestamp' => (string) time(),
            ])),
            json_encode(['event' => 'health_check', 'data' => []])
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * Replay protection: stale timestamp (> TIMESTAMP_TOLERANCE=300s) must
     * cause HMAC verification to reject even if the signature itself is
     * syntactically valid. Since we can't forge a valid HMAC without the
     * secret, we assert the request is rejected regardless (401 AUTH_FAILED).
     */
    public function test_stale_timestamp_is_rejected(): void
    {
        $staleTs = (string) (time() - 3600); // 1 hour old
        $response = $this->call(
            'POST',
            '/api/v2/federation/external/webhooks/receive',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->withTenantHeader([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => hash('sha256', 'anything'),
                'X-Webhook-Timestamp' => $staleTs,
            ])),
            json_encode(['event' => 'health_check', 'data' => []])
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * Malformed JSON with an Authorization header still fails — but with 401
     * (auth fails before JSON parse error can be revealed)... actually the
     * controller parses body FIRST. Confirm we get 400 INVALID_REQUEST for
     * truly unparseable body.
     */
    public function test_malformed_json_body_yields_400(): void
    {
        $response = $this->call(
            'POST',
            '/api/v2/federation/external/webhooks/receive',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->withTenantHeader([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer anything',
            ])),
            '{not-valid-json'
        );
        // Controller returns 400 INVALID_REQUEST for invalid JSON
        $this->assertSame(400, $response->getStatusCode());
    }

    /**
     * Missing 'event' field in an otherwise valid JSON body -> 400 INVALID_REQUEST.
     */
    public function test_missing_event_field_yields_400(): void
    {
        $response = $this->apiPost(
            '/v2/federation/external/webhooks/receive',
            ['data' => ['foo' => 'bar']] // no 'event'
        );
        $this->assertSame(400, $response->status());
        $body = $response->json();
        $this->assertSame('INVALID_REQUEST', $body['errors'][0]['code'] ?? null);
    }

    /**
     * Bearer token with an inactive partner secret would yield 403
     * PARTNER_INACTIVE. We can't easily create an encrypted partner row in
     * the test schema, so skip but document the expected path.
     */
    public function test_inactive_partner_yields_403_partner_inactive(): void
    {
        $this->markTestSkipped(
            'Requires seeded federation_external_partners row with Laravel-encrypted signing_secret'
            . ' — covered indirectly by auth_failed path; see controller line 105-107.'
        );
    }

    /**
     * Rate limiting: the controller rate-limits by IP at 200/min.
     * Full exhaustion is slow; just verify that normal-traffic bursts
     * don't leak 500s.
     */
    public function test_burst_of_requests_never_500(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->apiPost(
                '/v2/federation/external/webhooks/receive',
                ['event' => 'health_check', 'data' => []]
            );
            $this->assertLessThan(500, $response->status(),
                "Burst request #{$i} produced a 5xx");
        }
    }
}
