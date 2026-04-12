<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for FederationController — V1 federation API.
 *
 * V1 federation endpoints use Federation API key auth (not Sanctum). The
 * directory index (`/v1/federation`) is public by design so partners can
 * discover endpoints. All protected endpoints require an `X-API-Key` header;
 * without it they respond 401 MISSING_API_KEY.
 */
class FederationControllerTest extends TestCase
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
    //  GET /v1/federation (index) — public directory, no auth required
    // ------------------------------------------------------------------

    public function test_federation_index_is_public_and_returns_api_info(): void
    {
        $response = $this->apiGet('/v1/federation');

        $response->assertStatus(200);
        $response->assertJsonFragment(['api' => 'Federation API']);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/timebanks (requires Federation API key)
    // ------------------------------------------------------------------

    public function test_timebanks_rejects_request_without_api_key(): void
    {
        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    public function test_timebanks_rejects_user_without_federation_api_key(): void
    {
        // Authenticated Sanctum user but no X-API-Key → still 401 MISSING_API_KEY
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/members
    // ------------------------------------------------------------------

    public function test_federation_members_rejects_request_without_api_key(): void
    {
        $response = $this->apiGet('/v1/federation/members');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    public function test_federation_members_rejects_user_without_federation_api_key(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/members');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/listings
    // ------------------------------------------------------------------

    public function test_federation_listings_rejects_request_without_api_key(): void
    {
        $response = $this->apiGet('/v1/federation/listings');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    public function test_federation_listings_rejects_user_without_federation_api_key(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/listings');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }
}
