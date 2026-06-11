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
    // AUTHOR-DELETED REVIEWS — excluded from moderation entirely
    // ================================================================

    /**
     * Insert a review row directly; returns the new ID.
     */
    private function insertReview(array $overrides = []): int
    {
        $reviewer = User::factory()->forTenant($this->testTenantId)->create();
        $receiver = User::factory()->forTenant($this->testTenantId)->create();

        return (int) DB::table('reviews')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'reviewer_id' => $reviewer->id,
            'receiver_id' => $receiver->id,
            'rating' => 4,
            'comment' => 'Moderation test review',
            'status' => 'rejected',
            'deleted_by_author_at' => null,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_rejected_queue_excludes_author_deleted_reviews(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $moderatorRejectedId = $this->insertReview(['comment' => 'Moderator rejected']);
        $authorDeletedId = $this->insertReview([
            'comment' => 'Author deleted',
            'deleted_by_author_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/reviews?status=rejected&limit=100');

        $response->assertStatus(200);
        $ids = array_column($response->json('data') ?? [], 'id');
        $this->assertContains($moderatorRejectedId, $ids, 'Moderator-rejected review must stay in the rejected queue');
        $this->assertNotContains($authorDeletedId, $ids, 'Author-deleted review must not appear in the rejected queue');
    }

    public function test_flag_refuses_author_deleted_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $authorDeletedId = $this->insertReview(['deleted_by_author_at' => now()]);

        $response = $this->apiPost("/v2/admin/reviews/{$authorDeletedId}/flag");

        $response->assertStatus(404);

        $row = DB::table('reviews')->where('id', $authorDeletedId)->first();
        $this->assertSame('rejected', $row->status, 'Author-deleted review must not be resurrected to pending');
        $this->assertNotNull($row->deleted_by_author_at);
    }

    public function test_flag_still_works_for_moderator_rejected_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $moderatorRejectedId = $this->insertReview();

        $response = $this->apiPost("/v2/admin/reviews/{$moderatorRejectedId}/flag");

        $response->assertStatus(200);

        $row = DB::table('reviews')->where('id', $moderatorRejectedId)->first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->deleted_by_author_at);
    }

    public function test_hide_refuses_author_deleted_review(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $authorDeletedId = $this->insertReview([
            'status' => 'approved',
            'deleted_by_author_at' => now(),
        ]);

        $response = $this->apiPost("/v2/admin/reviews/{$authorDeletedId}/hide");

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
