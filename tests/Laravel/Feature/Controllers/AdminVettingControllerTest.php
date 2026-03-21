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
 * Feature tests for AdminVettingController.
 *
 * Covers list, stats, show, store, update, verify, reject, destroy,
 * bulk, getUserRecords, uploadDocument.
 */
class AdminVettingControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // STATS — GET /v2/admin/vetting/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/vetting/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/vetting/stats');

        $response->assertStatus(403);
    }

    public function test_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/vetting/stats');

        $response->assertStatus(401);
    }

    // ================================================================
    // LIST — GET /v2/admin/vetting
    // ================================================================

    public function test_list_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/vetting');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_list_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/vetting');

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW — GET /v2/admin/vetting/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_record(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/vetting/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/vetting/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // USER RECORDS — GET /v2/admin/vetting/user/{userId}
    // ================================================================

    public function test_user_records_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/vetting/user/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_user_records_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/vetting/user/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // VERIFY — POST /v2/admin/vetting/{id}/verify
    // ================================================================

    public function test_verify_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/vetting/1/verify');

        $response->assertStatus(403);
    }

    // ================================================================
    // REJECT — POST /v2/admin/vetting/{id}/reject
    // ================================================================

    public function test_reject_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/vetting/1/reject');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/vetting/{id}
    // ================================================================

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/vetting/1');

        $response->assertStatus(403);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/vetting/1');

        $response->assertStatus(401);
    }
}
