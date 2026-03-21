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
 * Feature tests for AdminImpactReportController.
 *
 * Covers index (full report) and updateConfig.
 */
class AdminImpactReportControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/impact-report
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/impact-report');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['sroi', 'health', 'timeline', 'config'],
        ]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/impact-report');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/impact-report');

        $response->assertStatus(401);
    }

    public function test_index_accepts_months_parameter(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/impact-report?months=6');

        $response->assertStatus(200);
    }

    // ================================================================
    // UPDATE CONFIG — PUT /v2/admin/impact-report/config
    // ================================================================

    public function test_update_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/impact-report/config', [
            'hourly_value' => 20.0,
            'social_multiplier' => 4.0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_config_rejects_invalid_hourly_value(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/impact-report/config', [
            'hourly_value' => -5,
            'social_multiplier' => 3.5,
        ]);

        $response->assertStatus(400);
    }

    public function test_update_config_rejects_invalid_social_multiplier(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/impact-report/config', [
            'hourly_value' => 15,
            'social_multiplier' => 200,
        ]);

        $response->assertStatus(400);
    }

    public function test_update_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/impact-report/config', [
            'hourly_value' => 20.0,
            'social_multiplier' => 4.0,
        ]);

        $response->assertStatus(403);
    }
}
