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
 * Feature tests for AdminSafeguardingController.
 *
 * Covers dashboard, flaggedMessages, assignments, reviewMessage,
 * createAssignment, deleteAssignment.
 * Tables may not exist, so the controller gracefully handles missing tables.
 */
class AdminSafeguardingControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // DASHBOARD — GET /v2/admin/safeguarding/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/safeguarding/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['total_flagged', 'pending_review', 'active_assignments', 'resolved'],
        ]);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/safeguarding/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/safeguarding/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // FLAGGED MESSAGES — GET /v2/admin/safeguarding/flagged-messages
    // ================================================================

    public function test_flagged_messages_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/safeguarding/flagged-messages');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_flagged_messages_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/safeguarding/flagged-messages');

        $response->assertStatus(403);
    }

    // ================================================================
    // ASSIGNMENTS — GET /v2/admin/safeguarding/assignments
    // ================================================================

    public function test_assignments_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/safeguarding/assignments');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_assignments_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/safeguarding/assignments');

        $response->assertStatus(403);
    }

    // ================================================================
    // CREATE ASSIGNMENT — POST /v2/admin/safeguarding/assignments
    // ================================================================

    public function test_create_assignment_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/safeguarding/assignments', [
            'user_id' => 1,
            'assignee_id' => 2,
            'type' => 'dbs_check',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_assignment_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/safeguarding/assignments', [
            'user_id' => 1,
            'assignee_id' => 2,
            'type' => 'dbs_check',
        ]);

        $response->assertStatus(401);
    }

    // ================================================================
    // DELETE ASSIGNMENT — DELETE /v2/admin/safeguarding/assignments/{id}
    // ================================================================

    public function test_delete_assignment_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiDelete('/v2/admin/safeguarding/assignments/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // REVIEW MESSAGE — POST /v2/admin/safeguarding/flagged-messages/{id}/review
    // ================================================================

    public function test_review_message_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/safeguarding/flagged-messages/1/review', [
            'action' => 'resolved',
            'notes' => 'Reviewed and resolved',
        ]);

        $response->assertStatus(403);
    }
}
