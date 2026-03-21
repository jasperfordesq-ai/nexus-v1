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
 * Feature tests for AdminReviewsController.
 *
 * Covers index, show, flag, hide, destroy.
 */
class AdminReviewsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/reviews
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reviews');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reviews');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/reviews');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/reviews/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reviews/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_200_for_existing_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reviewer = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        DB::table('reviews')->insert([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'reviewer_id' => $reviewer->id,
            'receiver_id' => $receiver->id,
            'rating' => 5,
            'comment' => 'Great service',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/reviews/1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'reviewer_id', 'receiver_id', 'rating', 'comment', 'status'],
        ]);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reviews/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // FLAG — POST /v2/admin/reviews/{id}/flag
    // ================================================================

    public function test_flag_returns_404_for_nonexistent_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/reviews/99999/flag');

        $response->assertStatus(404);
    }

    public function test_flag_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/reviews/1/flag');

        $response->assertStatus(403);
    }

    // ================================================================
    // HIDE — POST /v2/admin/reviews/{id}/hide
    // ================================================================

    public function test_hide_returns_404_for_nonexistent_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/reviews/99999/hide');

        $response->assertStatus(404);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/reviews/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/reviews/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/reviews/1');

        $response->assertStatus(403);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/reviews/1');

        $response->assertStatus(401);
    }
}
