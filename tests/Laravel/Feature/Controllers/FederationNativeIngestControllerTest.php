<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    public function test_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\FederationNativeIngestController::class));
    }

    public function test_reviews_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/reviews', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_listings_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/listings', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_events_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/events', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_groups_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/groups', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_connections_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/connections', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_volunteering_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/volunteering', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_members_sync_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/members/sync', []);
        $this->assertContains($response->status(), [401, 403, 400, 422]);
    }

    public function test_reviews_rejects_garbage_body(): void
    {
        $response = $this->apiPost('/v2/federation/reviews', ['garbage' => str_repeat('x', 200)]);
        $this->assertLessThan(500, $response->status());
    }
}
