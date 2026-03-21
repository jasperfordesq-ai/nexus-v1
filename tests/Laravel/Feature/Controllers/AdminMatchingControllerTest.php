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
 * Feature tests for AdminMatchingController.
 *
 * Covers index, approvalStats, show, approve, reject, getConfig, updateConfig,
 * clearCache, getStats.
 */
class AdminMatchingControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // CONFIG — GET /v2/admin/matching/config
    // ================================================================

    public function test_get_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/matching/config');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'category_weight',
                'skill_weight',
                'proximity_weight',
                'enabled',
                'min_match_score',
            ],
        ]);
    }

    public function test_get_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/matching/config');

        $response->assertStatus(403);
    }

    public function test_get_config_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/matching/config');

        $response->assertStatus(401);
    }

    // ================================================================
    // UPDATE CONFIG — PUT /v2/admin/matching/config
    // ================================================================

    public function test_update_config_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/matching/config', [
            'enabled' => true,
            'min_match_score' => 50,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_update_config_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/matching/config', [
            'enabled' => false,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // STATS — GET /v2/admin/matching/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/matching/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/matching/stats');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVALS INDEX — GET /v2/admin/matching/approvals
    // ================================================================

    public function test_approvals_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/matching/approvals');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // APPROVAL STATS — GET /v2/admin/matching/approvals/stats
    // ================================================================

    public function test_approval_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/matching/approvals/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SHOW — GET /v2/admin/matching/approvals/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_match(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/matching/approvals/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // REJECT — POST /v2/admin/matching/approvals/{id}/reject
    // ================================================================

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/matching/approvals/1/reject', []);

        $response->assertStatus(422);
    }

    // ================================================================
    // CLEAR CACHE — POST /v2/admin/matching/cache/clear
    // ================================================================

    public function test_clear_cache_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/matching/cache/clear');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_clear_cache_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/matching/cache/clear');

        $response->assertStatus(403);
    }
}
