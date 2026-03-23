<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\JobVacancy;
use App\Models\Notification;
use App\Services\JobInterviewService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class JobInterviewServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Helper: build a mock application with a vacancy ───────────────

    private function makeMockApplication(array $overrides = []): object
    {
        $tenantId = TenantContext::getId();

        $vacancy = Mockery::mock(JobVacancy::class)->makePartial();
        $vacancy->tenant_id = $overrides['vacancy_tenant_id'] ?? $tenantId;
        $vacancy->user_id = $overrides['vacancy_user_id'] ?? 100;
        $vacancy->id = $overrides['vacancy_id'] ?? 10;
        $vacancy->title = $overrides['vacancy_title'] ?? 'Software Engineer';
        $vacancy->shouldReceive('getAttribute')->with('tenant_id')->andReturn($vacancy->tenant_id);
        $vacancy->shouldReceive('getAttribute')->with('user_id')->andReturn($vacancy->user_id);
        $vacancy->shouldReceive('getAttribute')->with('id')->andReturn($vacancy->id);
        $vacancy->shouldReceive('getAttribute')->with('title')->andReturn($vacancy->title);

        $application = Mockery::mock(JobApplication::class)->makePartial();
        $application->id = $overrides['application_id'] ?? 1;
        $application->vacancy_id = $overrides['vacancy_id'] ?? 10;
        $application->user_id = $overrides['applicant_user_id'] ?? 200;
        $application->vacancy = $vacancy;
        $application->shouldReceive('getAttribute')->with('vacancy')->andReturn($vacancy);
        $application->shouldReceive('getAttribute')->with('vacancy_id')->andReturn($application->vacancy_id);
        $application->shouldReceive('getAttribute')->with('user_id')->andReturn($application->user_id);
        $application->shouldReceive('getAttribute')->with('id')->andReturn($application->id);

        return $application;
    }

    // ─── Helper: build a mock interview ────────────────────────────────

    private function makeMockInterview(array $overrides = []): object
    {
        $tenantId = TenantContext::getId();
        $application = $this->makeMockApplication($overrides);

        $interview = Mockery::mock(JobInterview::class)->makePartial();
        $interview->id = $overrides['interview_id'] ?? 60;
        $interview->tenant_id = $overrides['interview_tenant_id'] ?? $tenantId;
        $interview->vacancy_id = $overrides['vacancy_id'] ?? 10;
        $interview->application_id = $overrides['application_id'] ?? 1;
        $interview->proposed_by = $overrides['proposed_by'] ?? 100;
        $interview->status = $overrides['interview_status'] ?? 'proposed';
        $interview->application = $application;
        $interview->shouldReceive('update')->andReturn(true);
        $interview->shouldReceive('toArray')->andReturn([
            'id' => $interview->id,
            'tenant_id' => $interview->tenant_id,
            'vacancy_id' => $interview->vacancy_id,
            'application_id' => $interview->application_id,
            'proposed_by' => $interview->proposed_by,
            'status' => $interview->status,
        ]);
        $interview->shouldReceive('getAttribute')->with('tenant_id')->andReturn($interview->tenant_id);
        $interview->shouldReceive('getAttribute')->with('status')->andReturn($interview->status);
        $interview->shouldReceive('getAttribute')->with('application')->andReturn($application);
        $interview->shouldReceive('getAttribute')->with('application_id')->andReturn($interview->application_id);
        $interview->shouldReceive('getAttribute')->with('vacancy_id')->andReturn($interview->vacancy_id);
        $interview->shouldReceive('getAttribute')->with('proposed_by')->andReturn($interview->proposed_by);
        $interview->shouldReceive('getAttribute')->with('id')->andReturn($interview->id);

        return $interview;
    }

    // ====================================================================
    // propose()
    // ====================================================================

    public function test_propose_returns_interview_array_on_success(): void
    {
        $application = $this->makeMockApplication();
        $tenantId = TenantContext::getId();

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $createdInterview = Mockery::mock();
        $createdInterview->shouldReceive('toArray')->andReturn([
            'id' => 60,
            'tenant_id' => $tenantId,
            'vacancy_id' => 10,
            'application_id' => 1,
            'proposed_by' => 100,
            'interview_type' => 'video',
            'scheduled_at' => '2026-04-01 10:00:00',
            'duration_mins' => 60,
            'status' => 'proposed',
        ]);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('create')->once()->andReturn($createdInterview);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobInterviewService::propose(1, 100, [
            'interview_type' => 'video',
            'scheduled_at' => '2026-04-01 10:00:00',
            'duration_mins' => 60,
            'location_notes' => 'Zoom link will be sent',
        ]);

        $this->assertIsArray($result);
        $this->assertSame(60, $result['id']);
        $this->assertSame('proposed', $result['status']);
        $this->assertSame('video', $result['interview_type']);
    }

    public function test_propose_returns_false_when_application_not_found(): void
    {
        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(999)->andReturn(null);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $result = JobInterviewService::propose(999, 100, [
            'scheduled_at' => '2026-04-01 10:00:00',
        ]);

        $this->assertFalse($result);
    }

    public function test_propose_returns_false_when_vacancy_wrong_tenant(): void
    {
        $application = $this->makeMockApplication(['vacancy_tenant_id' => 999]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $result = JobInterviewService::propose(1, 100, [
            'scheduled_at' => '2026-04-01 10:00:00',
        ]);

        $this->assertFalse($result);
    }

    public function test_propose_returns_false_when_non_owner_proposes(): void
    {
        $application = $this->makeMockApplication(['vacancy_user_id' => 100]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        // User 999 is NOT the vacancy owner (100)
        $result = JobInterviewService::propose(1, 999, [
            'scheduled_at' => '2026-04-01 10:00:00',
        ]);

        $this->assertFalse($result);
    }

    public function test_propose_returns_false_when_scheduled_at_missing(): void
    {
        $application = $this->makeMockApplication();

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $result = JobInterviewService::propose(1, 100, [
            'interview_type' => 'in_person',
        ]);

        $this->assertFalse($result);
    }

    public function test_propose_defaults_to_video_type_and_60_mins(): void
    {
        $application = $this->makeMockApplication();
        $tenantId = TenantContext::getId();

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('find')->with(1)->andReturn($application);
        $appMock = Mockery::mock('alias:' . JobApplication::class);
        $appMock->shouldReceive('with')->with(['vacancy'])->andReturn($appQuery);

        $createdInterview = Mockery::mock();
        $createdInterview->shouldReceive('toArray')->andReturn([
            'id' => 60,
            'interview_type' => 'video',
            'duration_mins' => 60,
            'status' => 'proposed',
        ]);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('create')
            ->once()
            ->withArgs(function ($args) {
                return $args['interview_type'] === 'video' && $args['duration_mins'] === 60;
            })
            ->andReturn($createdInterview);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobInterviewService::propose(1, 100, [
            'scheduled_at' => '2026-04-01 10:00:00',
            // No interview_type or duration_mins — expect defaults
        ]);

        $this->assertIsArray($result);
        $this->assertSame('video', $result['interview_type']);
        $this->assertSame(60, $result['duration_mins']);
    }

    // ====================================================================
    // accept()
    // ====================================================================

    public function test_accept_returns_true_on_success(): void
    {
        $interview = $this->makeMockInterview(['applicant_user_id' => 200]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobInterviewService::accept(60, 200);

        $this->assertTrue($result);
    }

    public function test_accept_returns_false_when_interview_not_found(): void
    {
        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(999)->andReturn(null);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::accept(999, 200);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_wrong_tenant(): void
    {
        $interview = $this->makeMockInterview(['interview_tenant_id' => 999]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::accept(60, 200);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_wrong_user(): void
    {
        $interview = $this->makeMockInterview(['applicant_user_id' => 200]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        // User 300 is NOT the applicant (200)
        $result = JobInterviewService::accept(60, 300);

        $this->assertFalse($result);
    }

    public function test_accept_returns_false_when_status_not_proposed(): void
    {
        $interview = $this->makeMockInterview([
            'applicant_user_id' => 200,
            'interview_status' => 'accepted',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::accept(60, 200);

        $this->assertFalse($result);
    }

    // ====================================================================
    // decline()
    // ====================================================================

    public function test_decline_returns_true_on_success(): void
    {
        $interview = $this->makeMockInterview(['applicant_user_id' => 200]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobInterviewService::decline(60, 200, 'Schedule conflict');

        $this->assertTrue($result);
    }

    public function test_decline_returns_false_when_interview_not_found(): void
    {
        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(999)->andReturn(null);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::decline(999, 200);

        $this->assertFalse($result);
    }

    public function test_decline_returns_false_when_wrong_user(): void
    {
        $interview = $this->makeMockInterview(['applicant_user_id' => 200]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::decline(60, 300);

        $this->assertFalse($result);
    }

    public function test_decline_returns_false_when_status_not_proposed(): void
    {
        $interview = $this->makeMockInterview([
            'applicant_user_id' => 200,
            'interview_status' => 'declined',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::decline(60, 200);

        $this->assertFalse($result);
    }

    // ====================================================================
    // getForVacancy()
    // ====================================================================

    public function test_getForVacancy_returns_interviews_array(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('vacancy_id', 10)->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('scheduled_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([
            ['id' => 60, 'status' => 'proposed'],
            ['id' => 61, 'status' => 'accepted'],
        ]);
        $query->shouldReceive('get')->andReturn($collection);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')
            ->with(['application.applicant:id,first_name,last_name,avatar_url'])
            ->andReturn($query);

        $result = JobInterviewService::getForVacancy(10);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_getForVacancy_returns_empty_array_when_no_interviews(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('where')->with('vacancy_id', 10)->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('scheduled_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([]);
        $query->shouldReceive('get')->andReturn($collection);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')
            ->with(['application.applicant:id,first_name,last_name,avatar_url'])
            ->andReturn($query);

        $result = JobInterviewService::getForVacancy(10);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ====================================================================
    // getForUser()
    // ====================================================================

    public function test_getForUser_returns_interviews_array(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('scheduled_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([
            ['id' => 60, 'status' => 'proposed'],
        ]);
        $query->shouldReceive('get')->andReturn($collection);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')
            ->with(['vacancy:id,title,user_id'])
            ->andReturn($query);

        $result = JobInterviewService::getForUser(200);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_getForUser_returns_empty_array_when_no_interviews(): void
    {
        $tenantId = TenantContext::getId();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('tenant_id', $tenantId)->andReturnSelf();
        $query->shouldReceive('whereHas')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('scheduled_at')->andReturnSelf();
        $collection = Mockery::mock();
        $collection->shouldReceive('toArray')->andReturn([]);
        $query->shouldReceive('get')->andReturn($collection);

        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')
            ->with(['vacancy:id,title,user_id'])
            ->andReturn($query);

        $result = JobInterviewService::getForUser(200);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ====================================================================
    // cancel()
    // ====================================================================

    public function test_cancel_returns_true_on_success(): void
    {
        $interview = $this->makeMockInterview([
            'proposed_by' => 100,
            'interview_status' => 'proposed',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        $result = JobInterviewService::cancel(60, 100);

        $this->assertTrue($result);
    }

    public function test_cancel_works_for_accepted_interview(): void
    {
        $interview = $this->makeMockInterview([
            'proposed_by' => 100,
            'interview_status' => 'accepted',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $notifMock = Mockery::mock('alias:' . Notification::class);
        $notifMock->shouldReceive('createNotification')->andReturn(1);

        // Accepted interviews can still be cancelled
        $result = JobInterviewService::cancel(60, 100);

        $this->assertTrue($result);
    }

    public function test_cancel_returns_false_when_interview_not_found(): void
    {
        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(999)->andReturn(null);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::cancel(999, 100);

        $this->assertFalse($result);
    }

    public function test_cancel_returns_false_when_wrong_tenant(): void
    {
        $interview = $this->makeMockInterview(['interview_tenant_id' => 999]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::cancel(60, 100);

        $this->assertFalse($result);
    }

    public function test_cancel_returns_false_when_wrong_user(): void
    {
        $interview = $this->makeMockInterview(['proposed_by' => 100]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        // User 999 is NOT the proposer (100)
        $result = JobInterviewService::cancel(60, 999);

        $this->assertFalse($result);
    }

    public function test_cancel_returns_false_when_already_completed(): void
    {
        $interview = $this->makeMockInterview([
            'proposed_by' => 100,
            'interview_status' => 'completed',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::cancel(60, 100);

        $this->assertFalse($result);
    }

    public function test_cancel_returns_false_when_already_cancelled(): void
    {
        $interview = $this->makeMockInterview([
            'proposed_by' => 100,
            'interview_status' => 'cancelled',
        ]);

        $interviewQuery = Mockery::mock();
        $interviewQuery->shouldReceive('find')->with(60)->andReturn($interview);
        $interviewMock = Mockery::mock('alias:' . JobInterview::class);
        $interviewMock->shouldReceive('with')->with(['application.vacancy'])->andReturn($interviewQuery);

        $result = JobInterviewService::cancel(60, 100);

        $this->assertFalse($result);
    }
}
