<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for FederationV2Controller — user-facing federation endpoints.
 */
class FederationV2ControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/status
    // ------------------------------------------------------------------

    public function test_federation_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/status');

        $response->assertStatus(401);
    }

    public function test_federation_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/federation/status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/federation/opt-in
    // ------------------------------------------------------------------

    public function test_opt_in_requires_auth(): void
    {
        $response = $this->apiPost('/v2/federation/opt-in');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/federation/opt-out
    // ------------------------------------------------------------------

    public function test_opt_out_requires_auth(): void
    {
        $response = $this->apiPost('/v2/federation/opt-out');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/partners
    // ------------------------------------------------------------------

    public function test_partners_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/partners');

        $response->assertStatus(401);
    }

    public function test_partners_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/federation/partners');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/activity
    // ------------------------------------------------------------------

    public function test_activity_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/activity');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/settings
    // ------------------------------------------------------------------

    public function test_get_settings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/settings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/connections
    // ------------------------------------------------------------------

    public function test_connections_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/connections');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/members
    // ------------------------------------------------------------------

    public function test_federation_members_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/members');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/listings
    // ------------------------------------------------------------------

    public function test_federation_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/listings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/federation/events
    // ------------------------------------------------------------------

    public function test_federation_events_requires_auth(): void
    {
        $response = $this->apiGet('/v2/federation/events');

        $response->assertStatus(401);
    }
}
