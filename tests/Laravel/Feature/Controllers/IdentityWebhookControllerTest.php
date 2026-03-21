<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests for IdentityWebhookController — identity verification provider webhooks (public).
 */
class IdentityWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  POST /v2/webhooks/identity/{provider_slug} (PUBLIC — no auth)
    // ------------------------------------------------------------------

    public function test_webhook_is_public(): void
    {
        $response = $this->apiPost('/v2/webhooks/identity/onfido', [
            'event' => 'check.completed',
            'resource_id' => 'abc123',
        ]);

        // Should NOT return 401 — webhooks are public
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_webhook_handles_unknown_provider(): void
    {
        $response = $this->apiPost('/v2/webhooks/identity/unknown-provider', [
            'event' => 'test',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 400, 404, 422]);
    }
}
