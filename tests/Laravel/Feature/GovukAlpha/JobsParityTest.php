<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — jobs parity routes.
 *
 * Covers the five parity gaps added in JobsParity: J8 analytics dashboard,
 * J3 pipeline board, J5 qualification tool, talent search (+ candidate
 * profile) and the employer brand page. Mirrors the setUp scrubbing + helpers
 * used by GovukAlphaFrontendTest so it runs the same way inside the full
 * suite. Unique test_jobs_ method names throughout.
 */
class JobsParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->enableJobsFeature();
    }

    // =====================================================================
    // Auth gating — every parity route redirects anonymous users to login.
    // =====================================================================

    public function test_jobs_parity_pages_require_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";

        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        foreach ([
            "/jobs/{$job->id}/analytics",
            "/jobs/{$job->id}/pipeline",
            "/jobs/{$job->id}/qualified",
            '/jobs/talent-search',
            "/jobs/talent-search/{$owner->id}",
            "/jobs/employers/{$owner->id}",
        ] as $path) {
            $response = $this->get("/{$this->testTenantSlug}/alpha{$path}");
            $response->assertRedirect();
            $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
        }
    }

    // =====================================================================
    // J8 — Analytics dashboard
    // =====================================================================

    public function test_jobs_analytics_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/analytics");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.analytics.title'));
        $response->assertSee(__('govuk_alpha_jobs.analytics.key_metrics_heading'));
    }

    public function test_jobs_analytics_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        // A different, non-admin member cannot see another member's analytics.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/analytics");
        $response->assertForbidden();
    }

    public function test_jobs_analytics_not_found_for_missing_job(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/99999001/analytics");
        $response->assertNotFound();
    }

    // =====================================================================
    // J3 — Pipeline board
    // =====================================================================

    public function test_jobs_pipeline_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/pipeline");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.pipeline.title'));
        $response->assertSee(__('govuk_alpha_jobs.pipeline.column_applied'));
    }

    public function test_jobs_pipeline_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/pipeline");
        $response->assertForbidden();
    }

    public function test_jobs_pipeline_status_move_persists(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $applicant = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $appId = (int) DB::table('job_vacancy_applications')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $job->id,
            'user_id' => $applicant->id,
            'message' => 'Please consider me.',
            'status' => 'applied',
            'stage' => 'applied',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The pipeline reuses the existing applicants.status route to persist a move.
        $response = $this->post("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/applications/{$appId}/status", [
            'app_status' => 'interview',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('job_vacancy_applications', [
            'id' => $appId,
            'stage' => 'interview',
        ]);
    }

    // =====================================================================
    // J5 — Qualification tool
    // =====================================================================

    public function test_jobs_qualification_renders_for_member(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $owner->id, ['status' => 'open', 'skills_required' => 'gardening, mentoring']);

        // Any member may run the assessment against an open vacancy.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/{$job->id}/qualified");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.qualification.title'));
        $response->assertSee(__('govuk_alpha_jobs.qualification.overall_match'));
    }

    public function test_jobs_qualification_not_found_for_missing_job(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/99999002/qualified");
        $response->assertNotFound();
    }

    // =====================================================================
    // Talent search
    // =====================================================================

    public function test_jobs_talent_search_renders_for_employer(): void
    {
        // Owning a vacancy grants talent-search access (mirrors canSearchTalent).
        $owner = $this->authenticatedUser();
        $this->createJob((int) $owner->id, ['status' => 'open']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/talent-search");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.talent.title'));
        $response->assertSee(__('govuk_alpha_jobs.talent.prompt'));
    }

    public function test_jobs_talent_search_forbidden_for_non_employer(): void
    {
        // A member who owns no vacancy and is not an admin cannot search talent.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/talent-search");
        $response->assertForbidden();
    }

    public function test_jobs_talent_search_returns_searchable_candidate(): void
    {
        $owner = $this->authenticatedUser();
        $this->createJob((int) $owner->id, ['status' => 'open']);

        $candidate = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Searchable',
            'last_name' => 'Candidate',
            'resume_searchable' => 1,
            'skills' => 'gardening, mentoring',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/talent-search?keywords=Searchable");

        $response->assertOk();
        $response->assertSee('Searchable Candidate');
    }

    public function test_jobs_talent_profile_renders_for_searchable_candidate(): void
    {
        $owner = $this->authenticatedUser();
        $this->createJob((int) $owner->id, ['status' => 'open']);

        $candidate = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Profile',
            'last_name' => 'Person',
            'resume_searchable' => 1,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/talent-search/{$candidate->id}");

        $response->assertOk();
        $response->assertSee('Profile Person');
    }

    public function test_jobs_talent_profile_not_found_for_non_searchable(): void
    {
        $owner = $this->authenticatedUser();
        $this->createJob((int) $owner->id, ['status' => 'open']);

        $candidate = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'resume_searchable' => 0,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/talent-search/{$candidate->id}");
        $response->assertNotFound();
    }

    // =====================================================================
    // Employer brand page
    // =====================================================================

    public function test_jobs_employer_brand_renders_with_open_jobs(): void
    {
        $employer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'first_name' => 'Acme',
            'last_name' => 'Employer',
        ]);
        $this->createJob((int) $employer->id, ['status' => 'open', 'title' => 'Community Gardener Role']);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/employers/{$employer->id}");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.employer.open_jobs_heading'));
        $response->assertSee('Community Gardener Role');
    }

    public function test_jobs_employer_brand_not_found_for_cross_tenant_user(): void
    {
        // A user that does not belong to this tenant is invisible (404). Seed a
        // REAL second tenant first (nexus_test only ships tenants 1 and 2), then
        // place the foreign user in it so the users->tenants FK is satisfied and
        // the cross-tenant rejection is genuinely exercised by the handler's
        // tenant_id scope rather than by an FK error.
        $foreignTenantId = DB::table('tenants')->insertGetId([
            'name' => 'Neighbour Timebank',
            'slug' => 'jobs-foreign-' . strtolower(\Illuminate\Support\Str::random(8)),
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'tagline' => 'A neighbouring community.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherTenantUser = User::factory()->forTenant($foreignTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/employers/{$otherTenantUser->id}");
        $response->assertNotFound();
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function enableJobsFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['job_vacancies'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createJob(int $userId, array $overrides = []): JobVacancy
    {
        return JobVacancy::factory()->forTenant($this->testTenantId)->create(array_merge([
            'user_id' => $userId,
            'status' => 'open',
        ], $overrides));
    }
}
