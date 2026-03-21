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
 * Feature tests for AdminCrmController.
 *
 * Covers dashboard, funnel, admins, notes, tasks, tags, timeline, and exports.
 */
class AdminCrmControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // DASHBOARD — GET /v2/admin/crm/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/crm/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/crm/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // FUNNEL — GET /v2/admin/crm/funnel
    // ================================================================

    public function test_funnel_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/funnel');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_funnel_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/crm/funnel');

        $response->assertStatus(403);
    }

    // ================================================================
    // ADMINS LIST — GET /v2/admin/crm/admins
    // ================================================================

    public function test_list_admins_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/admins');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // NOTES — GET /v2/admin/crm/notes
    // ================================================================

    public function test_list_notes_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/notes');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_list_notes_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/crm/notes');

        $response->assertStatus(403);
    }

    // ================================================================
    // CREATE NOTE — POST /v2/admin/crm/notes
    // ================================================================

    public function test_create_note_returns_success_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/crm/notes', [
            'user_id' => $target->id,
            'content' => 'Test CRM note content',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // TASKS — GET /v2/admin/crm/tasks
    // ================================================================

    public function test_list_tasks_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_list_tasks_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/crm/tasks');

        $response->assertStatus(403);
    }

    // ================================================================
    // TAGS — GET /v2/admin/crm/tags
    // ================================================================

    public function test_list_tags_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/tags');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // TIMELINE — GET /v2/admin/crm/timeline
    // ================================================================

    public function test_timeline_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/timeline');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // EXPORT NOTES — GET /v2/admin/crm/export/notes
    // ================================================================

    public function test_export_notes_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/crm/export/notes');

        $response->assertStatus(200);
    }

    public function test_export_notes_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/crm/export/notes');

        $response->assertStatus(403);
    }
}
