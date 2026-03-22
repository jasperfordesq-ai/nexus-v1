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
 * V1 federation endpoints require TWO layers of auth:
 *   1. Laravel Sanctum (user session) — handled by global API middleware
 *   2. Federation API key — handled by FederationApiMiddleware inside the controller
 *
 * Without Sanctum token: 401 with code "auth_required"
 * With Sanctum token but no Federation API key: 401 with code "MISSING_API_KEY"
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
    //  GET /v1/federation (index)
    // ------------------------------------------------------------------

    public function test_federation_index_requires_sanctum_auth(): void
    {
        $response = $this->apiGet('/v1/federation');

        $response->assertStatus(401);
    }

    public function test_federation_index_returns_api_info_when_authenticated(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/timebanks (requires Federation API key)
    // ------------------------------------------------------------------

    public function test_timebanks_requires_sanctum_auth(): void
    {
        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(401);
    }

    public function test_timebanks_rejects_user_without_federation_api_key(): void
    {
        // Passes Sanctum but fails Federation API key check → 401 MISSING_API_KEY
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/members
    // ------------------------------------------------------------------

    public function test_federation_members_requires_sanctum_auth(): void
    {
        $response = $this->apiGet('/v1/federation/members');

        $response->assertStatus(401);
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

    public function test_federation_listings_requires_sanctum_auth(): void
    {
        $response = $this->apiGet('/v1/federation/listings');

        $response->assertStatus(401);
    }

    public function test_federation_listings_rejects_user_without_federation_api_key(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/listings');

        $response->assertStatus(401);
        $response->assertJsonFragment(['code' => 'MISSING_API_KEY']);
    }
}
