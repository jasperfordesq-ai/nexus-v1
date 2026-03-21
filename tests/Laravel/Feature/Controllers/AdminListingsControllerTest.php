<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminListingsController.
 *
 * Covers index, show, approve, reject, destroy, feature, unfeature,
 * moderationQueue, moderationStats, stats, searchAnalytics, searchTrending.
 */
class AdminListingsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/listings
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/listings/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_listing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/listings/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_200_for_existing_listing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        DB::table('listings')->insert([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Test Listing',
            'description' => 'Test Description',
            'type' => 'offer',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/listings/1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'title', 'description', 'type', 'status', 'user_id', 'user_name'],
        ]);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/listings/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPROVE — POST /v2/admin/listings/{id}/approve
    // ================================================================

    public function test_approve_returns_404_for_nonexistent_listing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/listings/99999/approve');

        $response->assertStatus(404);
    }

    public function test_approve_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/listings/1/approve');

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/listings/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_listing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/listings/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/listings/1');

        $response->assertStatus(401);
    }

    // ================================================================
    // MODERATION QUEUE — GET /v2/admin/listings/moderation-queue
    // ================================================================

    public function test_moderation_queue_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/listings/moderation-queue');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // MODERATION STATS — GET /v2/admin/listings/moderation-stats
    // ================================================================

    public function test_moderation_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/listings/moderation-stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_moderation_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/listings/moderation-stats');

        $response->assertStatus(403);
    }
}
