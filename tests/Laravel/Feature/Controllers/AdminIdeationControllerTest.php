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
 * Feature tests for AdminIdeationController.
 *
 * Covers index, show, destroy, updateStatus.
 */
class AdminIdeationControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/ideation
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/ideation');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/ideation');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/ideation');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/ideation/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_challenge(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/ideation/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/ideation/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // DESTROY — DELETE /v2/admin/ideation/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_challenge(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/ideation/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/ideation/1');

        $response->assertStatus(401);
    }

    // ================================================================
    // UPDATE STATUS — POST /v2/admin/ideation/{id}/status
    // ================================================================

    public function test_update_status_returns_400_for_invalid_status(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/ideation/1/status', [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(400);
    }

    public function test_update_status_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/ideation/1/status', [
            'status' => 'open',
        ]);

        $response->assertStatus(403);
    }
}
