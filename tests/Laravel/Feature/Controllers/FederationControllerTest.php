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
 * Feature tests for FederationController — V1 federation API.
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
    //  GET /v1/federation
    // ------------------------------------------------------------------

    public function test_federation_index_requires_auth(): void
    {
        $response = $this->apiGet('/v1/federation');

        $response->assertStatus(401);
    }

    public function test_federation_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/timebanks
    // ------------------------------------------------------------------

    public function test_timebanks_requires_auth(): void
    {
        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(401);
    }

    public function test_timebanks_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v1/federation/timebanks');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/members
    // ------------------------------------------------------------------

    public function test_federation_members_requires_auth(): void
    {
        $response = $this->apiGet('/v1/federation/members');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v1/federation/listings
    // ------------------------------------------------------------------

    public function test_federation_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v1/federation/listings');

        $response->assertStatus(401);
    }
}
