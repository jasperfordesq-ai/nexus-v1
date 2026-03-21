<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for JobVacanciesController — job listings, applications, alerts.
 */
class JobVacanciesControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/jobs
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs', [
            'title' => 'Community Coordinator',
            'description' => 'Full-time role',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs/{id}
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs/saved
    // ------------------------------------------------------------------

    public function test_saved_jobs_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/saved');

        $response->assertStatus(401);
    }

    public function test_saved_jobs_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/jobs/saved');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs/my-applications
    // ------------------------------------------------------------------

    public function test_my_applications_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-applications');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs/my-postings
    // ------------------------------------------------------------------

    public function test_my_postings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/my-postings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/jobs/alerts
    // ------------------------------------------------------------------

    public function test_alerts_requires_auth(): void
    {
        $response = $this->apiGet('/v2/jobs/alerts');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/jobs/{id}/apply
    // ------------------------------------------------------------------

    public function test_apply_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/apply', [
            'cover_letter' => 'I am interested in this role.',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/jobs/{id}/save
    // ------------------------------------------------------------------

    public function test_save_job_requires_auth(): void
    {
        $response = $this->apiPost('/v2/jobs/1/save');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/jobs/{id}/save
    // ------------------------------------------------------------------

    public function test_unsave_job_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/1/save');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/jobs/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/jobs/1');

        $response->assertStatus(401);
    }
}
