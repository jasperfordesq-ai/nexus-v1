<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
