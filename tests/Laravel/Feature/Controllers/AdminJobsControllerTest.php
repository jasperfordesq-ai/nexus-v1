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
 * Feature tests for AdminJobsController.
 *
 * Covers index, show, destroy, feature, unfeature, getApplications, updateApplicationStatus.
 */
class AdminJobsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/jobs
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/jobs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/jobs');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/jobs');

        $response->assertStatus(401);
    }

    // ================================================================
    // SHOW — GET /v2/admin/jobs/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_job(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/jobs/99999');

        $response->assertStatus(404);
    }

    public function test_show_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/jobs/1');

        $response->assertStatus(403);
    }

    // ================================================================
    // DESTROY — DELETE /v2/admin/jobs/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_job(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/jobs/99999');

        $response->assertStatus(404);
    }

    public function test_destroy_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiDelete('/v2/admin/jobs/1');

        $response->assertStatus(401);
    }

    // ================================================================
    // FEATURE — POST /v2/admin/jobs/{id}/feature
    // ================================================================

    public function test_feature_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/jobs/1/feature', [
            'duration_days' => 7,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // UNFEATURE — POST /v2/admin/jobs/{id}/unfeature
    // ================================================================

    public function test_unfeature_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/jobs/1/unfeature');

        $response->assertStatus(403);
    }

    // ================================================================
    // APPLICATIONS — GET /v2/admin/jobs/{id}/applications
    // ================================================================

    public function test_applications_returns_404_for_nonexistent_job(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/jobs/99999/applications');

        $response->assertStatus(404);
    }

    public function test_applications_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/jobs/1/applications');

        $response->assertStatus(403);
    }

    // ================================================================
    // UPDATE APPLICATION STATUS — PUT /v2/admin/jobs/applications/{id}
    // ================================================================

    public function test_update_application_status_requires_status(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPut('/v2/admin/jobs/applications/1', []);

        $response->assertStatus(422);
    }

    public function test_update_application_status_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPut('/v2/admin/jobs/applications/1', [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }
}
