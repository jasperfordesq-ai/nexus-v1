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
 * Feature tests for AdminDashboardController.
 *
 * Covers stats, trends, and activity log endpoints.
 */
class AdminDashboardControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // STATS — GET /v2/admin/dashboard/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_correct_data_structure(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        // Create some regular users so stats have data
        User::factory()->forTenant($this->testTenantId)->count(3)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(403);
    }

    public function test_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(401);
    }

    // ================================================================
    // TRENDS — GET /v2/admin/dashboard/trends
    // ================================================================

    public function test_trends_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/dashboard/trends');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_trends_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/dashboard/trends');

        $response->assertStatus(403);
    }

    // ================================================================
    // ACTIVITY — GET /v2/admin/dashboard/activity
    // ================================================================

    public function test_activity_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/dashboard/activity');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_activity_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/dashboard/activity');

        $response->assertStatus(403);
    }

    public function test_activity_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/dashboard/activity');

        $response->assertStatus(401);
    }

    // ================================================================
    // ACTIVITY LOG (alternate route) — GET /v2/admin/system/activity-log
    // ================================================================

    public function test_system_activity_log_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/system/activity-log');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_system_activity_log_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/system/activity-log');

        $response->assertStatus(403);
    }
}
