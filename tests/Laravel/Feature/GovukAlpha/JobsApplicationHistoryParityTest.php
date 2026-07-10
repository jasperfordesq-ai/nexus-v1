<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) job application status-history
 * timeline + interview "add to calendar" link.
 */
class JobsApplicationHistoryParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->enableJobsFeature();
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
            'status' => 'active', 'is_approved' => true,
        ], $overrides));
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function createJob(int $userId): JobVacancy
    {
        return JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $userId, 'status' => 'open', 'moderation_status' => 'approved',
        ]);
    }

    private function seedApplication(int $vacancyId, int $userId): JobApplication
    {
        return JobApplication::factory()->forTenant($this->testTenantId)->create([
            'vacancy_id' => $vacancyId, 'user_id' => $userId, 'status' => 'interview', 'stage' => 'interview',
        ]);
    }

    private function seedHistory(int $applicationId, ?string $from, string $to): void
    {
        DB::table('job_application_history')->insert([
            'application_id' => $applicationId,
            'from_status'    => $from,
            'to_status'      => $to,
            'changed_at'     => now(),
        ]);
    }

    public function test_application_history_timeline_renders_for_applicant(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser();
        Sanctum::actingAs($applicant, ['*']);
        $app = $this->seedApplication($job->id, $applicant->id);
        $this->seedHistory($app->id, null, 'applied');
        $this->seedHistory($app->id, 'applied', 'interview');

        $res = $this->get("/{$this->testTenantSlug}/accessible/jobs/applications/{$app->id}/history");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_jobs.history.title'));
        $res->assertSee(__('govuk_alpha.jobs_t2.app_status_interview'));
    }

    public function test_application_history_forbidden_for_unrelated_member(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);
        $applicant = $this->authenticatedUser();
        $app = $this->seedApplication($job->id, $applicant->id);
        $this->seedHistory($app->id, null, 'applied');

        $stranger = $this->authenticatedUser();
        Sanctum::actingAs($stranger, ['*']);

        $this->get("/{$this->testTenantSlug}/accessible/jobs/applications/{$app->id}/history")->assertNotFound();
    }

    public function test_interview_add_to_calendar_link_renders(): void
    {
        $owner = $this->authenticatedUser();
        $job = $this->createJob($owner->id);

        $applicant = $this->authenticatedUser();
        $app = $this->seedApplication($job->id, $applicant->id);

        JobInterview::create([
            'tenant_id'      => $this->testTenantId,
            'vacancy_id'     => $job->id,
            'application_id' => $app->id,
            'proposed_by'    => $owner->id,
            'interview_type' => 'video',
            'scheduled_at'   => now()->addDays(3),
            'duration_mins'  => 45,
            'location_notes' => 'https://meet.example.test/room',
            'status'         => 'proposed',
        ]);

        Sanctum::actingAs($applicant, ['*']);
        $res = $this->get("/{$this->testTenantSlug}/accessible/jobs/responses");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_jobs.responses.add_to_calendar'));
        $res->assertSee('calendar.google.com/calendar/render', false);
    }
}
