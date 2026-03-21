<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests for SendGridWebhookController — SendGrid event webhooks (public).
 */
class SendGridWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  POST /webhooks/sendgrid/events (PUBLIC — no auth)
    // ------------------------------------------------------------------

    public function test_events_webhook_is_public(): void
    {
        $response = $this->apiPost('/webhooks/sendgrid/events', [
            [
                'email' => 'test@example.com',
                'event' => 'delivered',
                'timestamp' => time(),
            ],
        ]);

        // Should NOT return 401 — this is a public webhook
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_events_webhook_accepts_payload(): void
    {
        $response = $this->apiPost('/webhooks/sendgrid/events', [
            [
                'email' => 'user@example.com',
                'event' => 'open',
                'timestamp' => time(),
                'sg_message_id' => 'abc123',
            ],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }
}
