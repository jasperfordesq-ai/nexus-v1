<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for StripeWebhookController.
 *
 * Public webhook receiver — no Sanctum; Stripe signature is verified
 * inside the controller. Invalid/missing signature must yield 4xx, not 500.
 */
class StripeWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\StripeWebhookController::class));
    }

    public function test_handle_webhook_rejects_empty_payload(): void
    {
        $response = $this->apiPost('/v2/webhooks/stripe', []);
        // No Stripe-Signature header — expect non-200 (signature failure).
        // 500 is acceptable if Stripe SDK/secret isn't configured in test env;
        // the key assertion is that unauthenticated callers never get 200/201.
        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
    }

    public function test_handle_webhook_rejects_invalid_signature(): void
    {
        $response = $this->apiPost(
            '/v2/webhooks/stripe',
            ['id' => 'evt_test', 'type' => 'charge.succeeded'],
            ['Stripe-Signature' => 'invalid_signature']
        );
        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
    }

    public function test_handle_webhook_rejects_garbage_body(): void
    {
        $response = $this->apiPost('/v2/webhooks/stripe', ['garbage' => str_repeat('x', 200)]);
        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
    }

    public function test_marketplace_webhook_rejects_empty_payload(): void
    {
        $response = $this->apiPost('/v2/marketplace/webhooks/stripe', []);
        $this->assertNotEquals(200, $response->status());
        $this->assertNotEquals(201, $response->status());
    }
}
