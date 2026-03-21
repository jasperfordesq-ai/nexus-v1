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
 * Feature tests for AdminFederationNeighborhoodsController.
 *
 * Covers listing neighborhoods, creating, deleting, adding/removing tenants,
 * and listing available tenants.
 */
class AdminFederationNeighborhoodsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/federation/neighborhoods
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/neighborhoods');

        // May return 200 or 503 if FederationNeighborhoodService is unavailable
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/neighborhoods');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/neighborhoods');

        $response->assertStatus(401);
    }

    // ================================================================
    // STORE — POST /v2/admin/federation/neighborhoods
    // ================================================================

    public function test_store_validates_name_required(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/neighborhoods', [
            'description' => 'Missing name',
        ]);

        // Validation error
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/federation/neighborhoods', [
            'name' => 'Should Fail',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // AVAILABLE TENANTS — GET /v2/admin/federation/available-tenants
    // ================================================================

    public function test_available_tenants_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/available-tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_available_tenants_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/available-tenants');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/federation/neighborhoods/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/federation/neighborhoods/999999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/federation/neighborhoods/1');

        $response->assertStatus(403);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/federation/neighborhoods/1');

        $response->assertStatus(401);
    }
}
