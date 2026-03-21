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
 * Feature tests for AdminGroupsController.
 *
 * Covers index, analytics, approvals, moderation, group types, members,
 * recommendations, featured groups, and group CRUD.
 */
class AdminGroupsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/groups
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/groups');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/groups');

        $response->assertStatus(401);
    }

    // ================================================================
    // ANALYTICS — GET /v2/admin/groups/analytics
    // ================================================================

    public function test_analytics_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_analytics_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/groups/analytics');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVALS — GET /v2/admin/groups/approvals
    // ================================================================

    public function test_approvals_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/approvals');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MODERATION — GET /v2/admin/groups/moderation
    // ================================================================

    public function test_moderation_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/moderation');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // GROUP TYPES — GET /v2/admin/groups/types
    // ================================================================

    public function test_group_types_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/types');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_group_types_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/groups/types');

        $response->assertStatus(403);
    }

    // ================================================================
    // FEATURED — GET /v2/admin/groups/featured
    // ================================================================

    public function test_featured_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/featured');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // RECOMMENDATIONS — GET /v2/admin/groups/recommendations
    // ================================================================

    public function test_recommendations_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/recommendations');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SHOW — GET /v2/admin/groups/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_group(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/groups/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/groups/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/groups/{id}
    // ================================================================

    public function test_delete_returns_404_for_nonexistent_group(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/groups/99999');

        $response->assertStatus(404);
    }

    public function test_delete_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/groups/1');

        $response->assertStatus(401);
    }
}
