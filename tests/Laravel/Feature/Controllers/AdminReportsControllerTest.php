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
 * Feature tests for AdminReportsController.
 *
 * Covers index, show, resolve, dismiss, stats.
 */
class AdminReportsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/reports
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reports');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/reports');

        $response->assertStatus(401);
    }

    // ================================================================
    // STATS — GET /v2/admin/reports/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['total', 'pending', 'resolved', 'dismissed'],
        ]);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/reports/stats');

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW — GET /v2/admin/reports/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_report(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/reports/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_200_for_existing_report(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reporter = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        DB::table('reports')->insert([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'reporter_id' => $reporter->id,
            'target_type' => 'listing',
            'target_id' => 1,
            'reason' => 'Inappropriate content',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/reports/1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['id', 'reporter_id', 'content_type', 'target_id', 'reason', 'status'],
        ]);
    }

    // ================================================================
    // RESOLVE — POST /v2/admin/reports/{id}/resolve
    // ================================================================

    public function test_resolve_returns_404_for_nonexistent_report(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/reports/99999/resolve');

        $response->assertStatus(404);
    }

    public function test_resolve_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/reports/1/resolve');

        $response->assertStatus(403);
    }

    // ================================================================
    // DISMISS — POST /v2/admin/reports/{id}/dismiss
    // ================================================================

    public function test_dismiss_returns_404_for_nonexistent_report(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/reports/99999/dismiss');

        $response->assertStatus(404);
    }

    public function test_dismiss_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/reports/1/dismiss');

        $response->assertStatus(403);
    }

    public function test_dismiss_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/reports/1/dismiss');

        $response->assertStatus(401);
    }

    // ================================================================
    // TARGET ENRICHMENT — index()/show() resolve target metadata
    // ================================================================

    public function test_index_enriches_user_target_with_name(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reporter = User::factory()->forTenant($this->testTenantId)->create();
        $reported = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        DB::table('reports')->insert([
            'tenant_id' => $this->testTenantId,
            'reporter_id' => $reporter->id,
            'target_type' => 'user',
            'target_id' => $reported->id,
            'reason' => 'Harassment',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/reports');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'content_type' => 'user',
            'target_id' => (int) $reported->id,
            'target_label' => $reported->name,
            'target_author_id' => (int) $reported->id,
            'target_exists' => true,
        ]);
    }

    public function test_show_enriches_listing_target_with_title_and_author(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reporter = User::factory()->forTenant($this->testTenantId)->create();
        $author = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $listingId = DB::table('listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $author->id,
            'title' => 'Handmade bookshelf',
            'type' => 'offer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = DB::table('reports')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'reporter_id' => $reporter->id,
            'target_type' => 'listing',
            'target_id' => $listingId,
            'reason' => 'Prohibited item',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $response = $this->apiGet("/v2/admin/reports/{$reportId}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.content_type', 'listing');
        $response->assertJsonPath('data.target_label', 'Handmade bookshelf');
        $response->assertJsonPath('data.target_author_name', $author->name);
        $response->assertJsonPath('data.target_exists', true);
    }

    public function test_index_marks_deleted_target_as_not_existing(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $reporter = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        DB::table('reports')->insert([
            'tenant_id' => $this->testTenantId,
            'reporter_id' => $reporter->id,
            'target_type' => 'post',
            'target_id' => 987654, // no such feed_post
            'reason' => 'Spam',
            'status' => 'open',
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/admin/reports');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'content_type' => 'post',
            'target_id' => 987654,
            'target_exists' => false,
            'target_label' => null,
        ]);
    }
}
