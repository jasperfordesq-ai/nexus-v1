<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\JobOffer;
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
        $loginPath = "/{$this->testTenantSlug}/accessible/login";

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
            $response = $this->get("/{$this->testTenantSlug}/accessible{$path}");
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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/analytics");

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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/analytics");
        $response->assertForbidden();
    }

    public function test_jobs_analytics_not_found_for_missing_job(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/99999001/analytics");
        $response->assertNotFound();
    }

    // =====================================================================
    // J3 — Pipeline board
    // =====================================================================

    public function test_jobs_pipeline_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/pipeline");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.pipeline.title'));
        $response->assertSee(__('govuk_alpha_jobs.pipeline.column_applied'));
    }

    public function test_jobs_pipeline_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $owner->id, ['status' => 'open']);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/pipeline");
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
        $response = $this->post("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/applications/{$appId}/status", [
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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/{$job->id}/qualified");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.qualification.title'));
        $response->assertSee(__('govuk_alpha_jobs.qualification.overall_match'));
    }

    public function test_jobs_qualification_not_found_for_missing_job(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/99999002/qualified");
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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/talent-search");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.talent.title'));
        $response->assertSee(__('govuk_alpha_jobs.talent.prompt'));
    }

    public function test_jobs_talent_search_forbidden_for_non_employer(): void
    {
        // A member who owns no vacancy and is not an admin cannot search talent.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/talent-search");
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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/talent-search?keywords=Searchable");

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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/talent-search/{$candidate->id}");

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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/talent-search/{$candidate->id}");
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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/employers/{$employer->id}");

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

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/employers/{$otherTenantUser->id}");
        $response->assertNotFound();
    }

    // =====================================================================
    // Employer onboarding
    // =====================================================================

    public function test_jobs_onboarding_renders_for_member(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/employer-onboarding");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.onboarding.title'));
        $response->assertSee(__('govuk_alpha_jobs.onboarding.start_button'));
    }

    public function test_jobs_onboarding_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/employer-onboarding");
        $response->assertRedirect();
        $this->assertStringContainsString(
            "/{$this->testTenantSlug}/accessible/login",
            $response->headers->get('Location') ?? ''
        );
    }

    // =====================================================================
    // Responses inbox (interviews & offers)
    // =====================================================================

    public function test_jobs_responses_renders_with_interview_and_offer(): void
    {
        $candidate = $this->authenticatedUser();
        $poster = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $poster->id, ['status' => 'open', 'title' => 'Allotment Helper Role']);
        $application = $this->jobsSeedApplication((int) $job->id, (int) $candidate->id);
        $this->createInterview((int) $job->id, (int) $application->id, (int) $poster->id, 'proposed');
        $this->createOffer((int) $job->id, (int) $application->id, (int) $candidate->id, 'pending');

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/responses");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.responses.title'));
        $response->assertSee(__('govuk_alpha_jobs.responses.accept_interview'));
        $response->assertSee(__('govuk_alpha_jobs.responses.accept_offer'));
        $response->assertSee('Allotment Helper Role');
    }

    public function test_jobs_responses_empty_state(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/responses");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.responses.no_interviews'));
        $response->assertSee(__('govuk_alpha_jobs.responses.no_offers'));
    }

    public function test_jobs_responses_requires_authentication(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/accessible/jobs/responses");
        $response->assertRedirect();
        $this->assertStringContainsString(
            "/{$this->testTenantSlug}/accessible/login",
            $response->headers->get('Location') ?? ''
        );
    }

    public function test_jobs_accept_interview_persists_for_candidate(): void
    {
        $candidate = $this->authenticatedUser();
        $poster = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $poster->id, ['status' => 'open']);
        $application = $this->jobsSeedApplication((int) $job->id, (int) $candidate->id);
        $interview = $this->createInterview((int) $job->id, (int) $application->id, (int) $poster->id, 'proposed');

        $response = $this->post("/{$this->testTenantSlug}/accessible/jobs/interviews/{$interview->id}/accept", ['note' => '']);

        $response->assertRedirect();
        $this->assertStringContainsString('status=interview-accepted', $response->headers->get('Location') ?? '');
        $this->assertSame('accepted', JobInterview::find($interview->id)->status);
    }

    public function test_jobs_decline_interview_persists_for_candidate(): void
    {
        $candidate = $this->authenticatedUser();
        $poster = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $poster->id, ['status' => 'open']);
        $application = $this->jobsSeedApplication((int) $job->id, (int) $candidate->id);
        $interview = $this->createInterview((int) $job->id, (int) $application->id, (int) $poster->id, 'proposed');

        $response = $this->post("/{$this->testTenantSlug}/accessible/jobs/interviews/{$interview->id}/decline", ['note' => '']);

        $response->assertRedirect();
        $this->assertSame('declined', JobInterview::find($interview->id)->status);
    }

    public function test_jobs_accept_interview_blocked_for_non_owner(): void
    {
        // An interview that belongs to a different candidate cannot be accepted —
        // the service returns false and we redirect with the failure flash.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $poster = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $poster->id, ['status' => 'open']);
        $application = $this->jobsSeedApplication((int) $job->id, (int) $other->id);
        $interview = $this->createInterview((int) $job->id, (int) $application->id, (int) $poster->id, 'proposed');

        // Authenticate as a DIFFERENT member (not the applicant).
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/accessible/jobs/interviews/{$interview->id}/accept", ['note' => '']);

        $response->assertRedirect();
        $this->assertStringContainsString('status=interview-failed', $response->headers->get('Location') ?? '');
        $this->assertSame('proposed', JobInterview::find($interview->id)->status);
    }

    public function test_jobs_reject_offer_persists_for_candidate(): void
    {
        $candidate = $this->authenticatedUser();
        $poster = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $job = $this->createJob((int) $poster->id, ['status' => 'open', 'type' => 'volunteer']);
        $application = $this->jobsSeedApplication((int) $job->id, (int) $candidate->id);
        $offer = $this->createOffer((int) $job->id, (int) $application->id, (int) $candidate->id, 'pending');

        $response = $this->post("/{$this->testTenantSlug}/accessible/jobs/offers/{$offer->id}/reject");

        $response->assertRedirect();
        $this->assertSame('rejected', JobOffer::find($offer->id)->status);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function jobsSeedApplication(int $vacancyId, int $userId): JobApplication
    {
        return JobApplication::factory()->forTenant($this->testTenantId)->create([
            'vacancy_id' => $vacancyId,
            'user_id' => $userId,
            'status' => 'interview',
            'stage' => 'interview',
        ]);
    }

    private function createInterview(int $vacancyId, int $applicationId, int $proposedBy, string $status): JobInterview
    {
        return JobInterview::create([
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $vacancyId,
            'application_id' => $applicationId,
            'proposed_by' => $proposedBy,
            'interview_type' => 'video',
            'scheduled_at' => now()->addDays(3),
            'duration_mins' => 30,
            'location_notes' => 'https://meet.example.test/room',
            'status' => $status,
        ]);
    }

    private function createOffer(int $vacancyId, int $applicationId, int $userId, string $status): JobOffer
    {
        return JobOffer::create([
            'tenant_id' => $this->testTenantId,
            'vacancy_id' => $vacancyId,
            'application_id' => $applicationId,
            'user_id' => $userId,
            'status' => $status,
            'salary_offered' => 0,
            'expires_at' => now()->addDays(7),
        ]);
    }

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
