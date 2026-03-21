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
 * Feature tests for AdminCommunityAnalyticsController.
 *
 * Covers index (aggregated analytics), export (CSV), and geography.
 */
class AdminCommunityAnalyticsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/community-analytics
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/community-analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'overview',
                'monthly_trends',
                'weekly_trends',
                'top_earners',
                'top_spenders',
                'category_demand',
                'engagement_rate',
            ],
        ]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/community-analytics');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/community-analytics');

        $response->assertStatus(401);
    }

    // ================================================================
    // EXPORT — GET /v2/admin/community-analytics/export
    // ================================================================

    public function test_export_returns_200_csv_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/community-analytics/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/community-analytics/export');

        $response->assertStatus(403);
    }

    // ================================================================
    // GEOGRAPHY — GET /v2/admin/community-analytics/geography
    // ================================================================

    public function test_geography_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/community-analytics/geography');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'member_locations',
                'total_with_location',
                'total_members',
                'coverage_percentage',
                'top_areas',
            ],
        ]);
    }

    public function test_geography_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/community-analytics/geography');

        $response->assertStatus(403);
    }

    public function test_geography_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/community-analytics/geography');

        $response->assertStatus(401);
    }
}
