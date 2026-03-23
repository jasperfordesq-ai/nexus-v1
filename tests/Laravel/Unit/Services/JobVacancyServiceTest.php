<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\JobAlert;
use App\Models\JobApplication;
use App\Models\JobApplicationHistory;
use App\Models\JobVacancy;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\JobModerationService;
use App\Services\JobSpamDetectionService;
use App\Services\JobVacancyService;
use App\Services\WebhookDispatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JobVacancyServiceTest extends TestCase
{
    private JobVacancyService $service;
    private $mockVacancy;
    private $userAlias;
    private $jobApplicationAlias;
    private $jobApplicationHistoryAlias;
    private $savedJobAlias;
    private $jobAlertAlias;
    private $spamDetectionAlias;
    private $moderationAlias;
    private $webhookAlias;

    protected function setUp(): void
    {
        parent::setUp();

        // Create alias mocks for statically-called services (never need instance mocks)
        $this->userAlias = Mockery::mock('alias:' . User::class);
        $this->savedJobAlias = Mockery::mock('alias:' . SavedJob::class);
        $this->spamDetectionAlias = Mockery::mock('alias:' . JobSpamDetectionService::class);
        $this->moderationAlias = Mockery::mock('alias:' . JobModerationService::class);
        $this->webhookAlias = Mockery::mock('alias:' . WebhookDispatchService::class);

        // For models that need BOTH static mocking AND typed instance mocking,
        // create alias mocks lazily via helper methods.
        // Do NOT create aliases here for: JobApplication, JobApplicationHistory, JobAlert

        $this->mockVacancy = Mockery::mock(JobVacancy::class)->makePartial();
        $this->service = new JobVacancyService($this->mockVacancy);
    }

    /**
     * Get or create the JobApplication alias mock (lazy initialization).
     * Call this in tests that need static JobApplication methods.
     * Do NOT call in tests that need Mockery::mock(JobApplication::class)->makePartial().
     */
    private function getJobApplicationAlias()
    {
        if (!$this->jobApplicationAlias) {
            $this->jobApplicationAlias = Mockery::mock('alias:' . JobApplication::class);
        }
        return $this->jobApplicationAlias;
    }

    /**
     * Get or create the JobApplicationHistory alias mock (lazy initialization).
     */
    private function getJobApplicationHistoryAlias()
    {
        if (!$this->jobApplicationHistoryAlias) {
            $this->jobApplicationHistoryAlias = Mockery::mock('alias:' . JobApplicationHistory::class);
        }
        return $this->jobApplicationHistoryAlias;
    }

    /**
     * Get or create the JobAlert alias mock (lazy initialization).
     */
    private function getJobAlertAlias()
    {
        if (!$this->jobAlertAlias) {
            $this->jobAlertAlias = Mockery::mock('alias:' . JobAlert::class);
        }
        return $this->jobAlertAlias;
    }

    // =========================================================================
    // getErrors()
    // =========================================================================

    public function test_getErrors_initially_empty(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    // =========================================================================
    // getAll() — basic structure and pagination
    // =========================================================================

    public function test_getAll_returns_paginated_structure(): void
    {
        $query = $this->buildGetAllQuery();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
        $this->assertNull($result['cursor']);
        $this->assertFalse($result['has_more']);
    }

    public function test_getAll_with_status_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['status' => 'active']);
        $this->assertIsArray($result);
        $this->assertFalse($result['has_more']);
    }

    public function test_getAll_with_type_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['type' => 'full_time']);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_commitment_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['commitment' => 'part_time']);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_category_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['category' => 'tech']);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_search_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['search' => 'developer']);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_user_id_filter(): void
    {
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['user_id' => 5]);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_featured_filter(): void
    {
        $query = $this->buildGetAllQuery(['where', 'whereNull', 'orWhere']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['featured' => true]);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_geo_filter(): void
    {
        $query = $this->buildGetAllQuery(['where', 'whereNotNull', 'whereRaw']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll([
            'latitude'  => 53.3498,
            'longitude' => -6.2603,
            'radius_km' => 25,
        ]);
        $this->assertIsArray($result);
    }

    public function test_getAll_with_cursor_pagination(): void
    {
        $cursor = base64_encode('100');
        $query = $this->buildGetAllQuery(['where']);
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['cursor' => $cursor]);
        $this->assertFalse($result['has_more']);
    }

    public function test_getAll_has_more_when_extra_items(): void
    {
        // Simulate 21 items returned (limit 20 + 1 sentinel), meaning has_more=true
        $items = collect();
        for ($i = 21; $i >= 1; $i--) {
            $mockItem = Mockery::mock();
            $mockItem->shouldReceive('toArray')->andReturn([
                'id' => $i, 'tenant_id' => 2, 'user_id' => 1,
                'title' => "Job {$i}", 'status' => 'open',
                'views_count' => 0, 'applications_count' => 0,
                'is_remote' => false, 'is_featured' => false,
                'salary_negotiable' => false, 'renewal_count' => 0,
                'blind_hiring' => false, 'skills_required' => '',
            ]);
            $mockItem->id = $i;
            $items->push($mockItem);
        }

        $query = $this->buildGetAllQuery();
        $query->shouldReceive('get')->andReturn($items);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock DB::table for enrichVacancy's has_applied/is_saved checks
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getAll();

        $this->assertTrue($result['has_more']);
        $this->assertCount(20, $result['items']);
        $this->assertNotNull($result['cursor']);
    }

    public function test_getAll_limits_to_100_max(): void
    {
        $query = $this->buildGetAllQuery();
        $query->shouldReceive('get')->andReturn(collect([]));
        // With limit 500 requested, should be capped to 100, so limit(101)
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['limit' => 500]);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getById()
    // =========================================================================

    public function test_getById_returns_null_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getById(999);
        $this->assertNull($result);
    }

    public function test_getById_returns_enriched_data_when_found(): void
    {
        $mockJob = $this->makeMockVacancyRow(42);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock the count query for applications_count
        $mockAppQuery = Mockery::mock();
        $mockAppQuery->shouldReceive('where')->andReturnSelf();
        $mockAppQuery->shouldReceive('count')->andReturn(5);
        $this->getJobApplicationAlias()->shouldReceive('where')->andReturn($mockAppQuery);

        $result = $this->service->getById(42);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['id']);
        $this->assertEquals(5, $result['applications_count']);
    }

    // =========================================================================
    // create()
    // =========================================================================

    public function test_create_returns_vacancy_id_on_success(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 10;
        $mockCreatedVacancy->title = 'Test Job';
        $mockCreatedVacancy->type = 'volunteer';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock spam detection
        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 0, 'flags' => [], 'action' => 'allow',
        ]);

        // Mock moderation check
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(false);

        // Mock webhook dispatch
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        // Mock User::find for event dispatch
        $this->userAlias->shouldReceive('find')->andReturn(null);

        $result = $this->service->create(1, [
            'title' => 'Test Job',
            'description' => 'A great opportunity',
            'type' => 'volunteer',
        ]);

        $this->assertEquals(10, $result);
        $this->assertEmpty($this->service->getErrors());
    }

    public function test_create_requires_salary_for_paid_jobs(): void
    {
        $result = $this->service->create(1, [
            'title' => 'Paid Job',
            'description' => 'Needs salary',
            'type' => 'paid',
            'salary_negotiable' => false,
        ]);

        $this->assertEquals(0, $result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_SALARY_REQUIRED', $errors[0]['code']);
    }

    public function test_create_allows_paid_job_when_salary_negotiable(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 11;
        $mockCreatedVacancy->title = 'Paid Negotiable Job';
        $mockCreatedVacancy->type = 'paid';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 0, 'flags' => [], 'action' => 'allow',
        ]);
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(false);
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();
        $this->userAlias->shouldReceive('find')->andReturn(null);

        $result = $this->service->create(1, [
            'title' => 'Paid Negotiable Job',
            'description' => 'Salary is flexible',
            'type' => 'paid',
            'salary_negotiable' => true,
        ]);

        $this->assertGreaterThan(0, $result);
    }

    public function test_create_allows_paid_job_when_salary_range_provided(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 12;
        $mockCreatedVacancy->title = 'Paid Job With Salary';
        $mockCreatedVacancy->type = 'paid';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 0, 'flags' => [], 'action' => 'allow',
        ]);
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(false);
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();
        $this->userAlias->shouldReceive('find')->andReturn(null);

        $result = $this->service->create(1, [
            'title' => 'Paid Job With Salary',
            'description' => 'Good pay',
            'type' => 'paid',
            'salary_min' => 30000,
            'salary_max' => 50000,
        ]);

        $this->assertGreaterThan(0, $result);
    }

    public function test_create_blocked_by_spam_detection(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 13;
        $mockCreatedVacancy->title = 'Spammy Job';
        $mockCreatedVacancy->type = 'volunteer';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 90, 'flags' => ['suspicious_links'], 'action' => 'block',
        ]);
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(false);
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();
        $this->userAlias->shouldReceive('find')->andReturn(null);
        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $result = $this->service->create(1, [
            'title' => 'Spammy Job',
            'description' => 'Click bit.ly for money',
            'type' => 'volunteer',
        ]);

        // Job is still created, but with status=closed, moderation_status=rejected
        $this->assertEquals(13, $result);
    }

    public function test_create_flagged_for_moderation(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 14;
        $mockCreatedVacancy->title = 'Flagged Job';
        $mockCreatedVacancy->type = 'volunteer';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 55, 'flags' => ['new_account'], 'action' => 'flag',
        ]);
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(false);
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();
        $this->userAlias->shouldReceive('find')->andReturn(null);
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        $result = $this->service->create(1, [
            'title' => 'Flagged Job',
            'description' => 'Seems suspicious',
            'type' => 'volunteer',
        ]);

        $this->assertEquals(14, $result);
    }

    public function test_create_moderation_enabled_sets_pending_review(): void
    {
        $mockCreatedVacancy = Mockery::mock();
        $mockCreatedVacancy->id = 15;
        $mockCreatedVacancy->title = 'Moderated Job';
        $mockCreatedVacancy->type = 'volunteer';

        $query = Mockery::mock();
        $query->shouldReceive('create')->andReturn($mockCreatedVacancy);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $this->spamDetectionAlias->shouldReceive('analyzeJob')->andReturn([
            'score' => 0, 'flags' => [], 'action' => 'allow',
        ]);
        $this->moderationAlias->shouldReceive('isModerationEnabled')->andReturn(true);
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();
        $this->userAlias->shouldReceive('find')->andReturn(null);

        $result = $this->service->create(1, [
            'title' => 'Moderated Job',
            'description' => 'Needs review',
            'type' => 'volunteer',
        ]);

        $this->assertGreaterThan(0, $result);
    }

    // =========================================================================
    // update()
    // =========================================================================

    public function test_update_returns_true_on_success(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->type = 'volunteer';
        $mockJob->salary_negotiable = false;
        $mockJob->salary_min = null;
        $mockJob->salary_max = null;
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->update(5, 1, ['title' => 'Updated Title']);
        $this->assertTrue($result);
        $this->assertEmpty($this->service->getErrors());
    }

    public function test_update_returns_false_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->update(999, 1, ['title' => 'No']);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_update_forbidden_for_non_owner_non_admin(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock user lookup - regular user, not admin
        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->update(5, 99, ['title' => 'Hacked']);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_update_allowed_for_admin(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->type = 'volunteer';
        $mockJob->salary_negotiable = false;
        $mockJob->salary_min = null;
        $mockJob->salary_max = null;
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->update(5, 99, ['title' => 'Admin Edit']);
        $this->assertTrue($result);
    }

    public function test_update_returns_true_when_no_fields_to_update(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Pass non-allowed field names
        $result = $this->service->update(5, 1, ['nonexistent_field' => 'value']);
        $this->assertTrue($result);
    }

    public function test_update_salary_validation_for_paid_type(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->type = 'paid';
        $mockJob->salary_negotiable = false;
        $mockJob->salary_min = null;
        $mockJob->salary_max = null;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->update(5, 1, ['title' => 'Updated']);
        $this->assertFalse($result);
        $this->assertEquals('VALIDATION_SALARY_REQUIRED', $this->service->getErrors()[0]['code']);
    }

    public function test_update_salary_validation_passes_when_negotiable(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->type = 'paid';
        $mockJob->salary_negotiable = true;
        $mockJob->salary_min = null;
        $mockJob->salary_max = null;
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->update(5, 1, ['title' => 'Updated']);
        $this->assertTrue($result);
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function test_delete_returns_true_on_success(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->shouldReceive('delete')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockAppQuery = Mockery::mock();
        $mockAppQuery->shouldReceive('delete')->andReturn(0);
        $this->getJobApplicationAlias()->shouldReceive('where')->with('vacancy_id', 5)->andReturn($mockAppQuery);

        $result = $this->service->delete(5, 1);
        $this->assertTrue($result);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->delete(999, 1);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_delete_forbidden_for_non_owner_non_admin(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->delete(5, 99);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    // =========================================================================
    // apply()
    // =========================================================================

    public function test_apply_returns_application_id_on_success(): void
    {
        // Not already applied
        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(false);
        $this->getJobApplicationAlias()->shouldReceive('where')->with('vacancy_id', 5)->andReturn($existsQuery);

        // Create application
        $mockApp = Mockery::mock();
        $mockApp->id = 100;
        $this->getJobApplicationAlias()->shouldReceive('create')->andReturn($mockApp);

        // logApplicationHistory
        $this->getJobApplicationHistoryAlias()->shouldReceive('create')->andReturn(Mockery::mock());

        // Increment applications_count
        $incQuery = Mockery::mock();
        $incQuery->shouldReceive('where')->with('id', 5)->andReturnSelf();
        $incQuery->shouldReceive('increment')->with('applications_count')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($incQuery);

        // Webhook
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        $result = $this->service->apply(5, 10, ['cover_letter' => 'I am interested']);
        $this->assertEquals(100, $result);
    }

    public function test_apply_returns_null_when_already_applied(): void
    {
        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(true);
        $this->getJobApplicationAlias()->shouldReceive('where')->with('vacancy_id', 5)->andReturn($existsQuery);

        $result = $this->service->apply(5, 10);
        $this->assertNull($result);
    }

    public function test_apply_with_cv_data(): void
    {
        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(false);
        $this->getJobApplicationAlias()->shouldReceive('where')->with('vacancy_id', 5)->andReturn($existsQuery);

        $mockApp = Mockery::mock();
        $mockApp->id = 101;
        $this->getJobApplicationAlias()->shouldReceive('create')->with(Mockery::on(function ($data) {
            return $data['cv_path'] === '/uploads/cv.pdf'
                && $data['cv_filename'] === 'cv.pdf'
                && $data['cv_size'] === 1024;
        }))->andReturn($mockApp);

        $this->getJobApplicationHistoryAlias()->shouldReceive('create')->andReturn(Mockery::mock());

        $incQuery = Mockery::mock();
        $incQuery->shouldReceive('where')->andReturnSelf();
        $incQuery->shouldReceive('increment')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($incQuery);

        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        $result = $this->service->apply(5, 10, [
            'cover_letter' => 'Here is my CV',
            'cv_path' => '/uploads/cv.pdf',
            'cv_filename' => 'cv.pdf',
            'cv_size' => 1024,
        ]);

        $this->assertEquals(101, $result);
    }

    // =========================================================================
    // incrementViews()
    // =========================================================================

    public function test_incrementViews_increments_and_logs_view(): void
    {
        $incQuery = Mockery::mock();
        $incQuery->shouldReceive('where')->with('id', 5)->andReturnSelf();
        $incQuery->shouldReceive('increment')->with('views_count')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($incQuery);

        DB::shouldReceive('table')->with('job_vacancy_views')->andReturnSelf();
        DB::shouldReceive('insert')->andReturn(true);

        // Should not throw
        $this->service->incrementViews(5, 10);
        $this->assertTrue(true);
    }

    public function test_incrementViews_without_user_id(): void
    {
        $incQuery = Mockery::mock();
        $incQuery->shouldReceive('where')->with('id', 5)->andReturnSelf();
        $incQuery->shouldReceive('increment')->with('views_count')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($incQuery);

        DB::shouldReceive('table')->with('job_vacancy_views')->andReturnSelf();
        DB::shouldReceive('insert')->andReturn(true);

        $this->service->incrementViews(5, null);
        $this->assertTrue(true);
    }

    // =========================================================================
    // featureJob() / unfeatureJob()
    // =========================================================================

    public function test_featureJob_returns_true_for_admin(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($mockUserQuery);

        $mockJob = Mockery::mock();
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->featureJob(5, 1, 7);
        $this->assertTrue($result);
    }

    public function test_featureJob_forbidden_for_non_admin(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->featureJob(5, 99);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_featureJob_not_found(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($mockUserQuery);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->featureJob(999, 1);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_featureJob_clamps_days_between_1_and_90(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'super_admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($mockUserQuery);

        $mockJob = Mockery::mock();
        $mockJob->shouldReceive('update')->with(Mockery::on(function ($data) {
            // Verify days are clamped: 200 -> 90
            return $data['is_featured'] === true && $data['featured_until'] !== null;
        }))->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->featureJob(5, 1, 200);
        $this->assertTrue($result);
    }

    public function test_unfeatureJob_returns_true_for_admin(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($mockUserQuery);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('id', 5)->andReturnSelf();
        $query->shouldReceive('update')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->unfeatureJob(5, 1);
        $this->assertTrue($result);
    }

    public function test_unfeatureJob_forbidden_for_non_admin(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->unfeatureJob(5, 99);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    // =========================================================================
    // getApplications()
    // =========================================================================

    public function test_getApplications_returns_null_when_vacancy_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getApplications(999, 1);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getApplications_forbidden_for_non_owner_non_admin(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->blind_hiring = false;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->getApplications(5, 99);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_getApplications_returns_applications_for_owner(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->blind_hiring = false;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock applications query
        $mockApplicant = Mockery::mock();
        $mockApplicant->first_name = 'John';
        $mockApplicant->last_name = 'Doe';
        $mockApplicant->avatar_url = 'avatar.jpg';
        $mockApplicant->email = 'john@example.com';

        $mockApp = Mockery::mock();
        $mockApp->user_id = 10;
        $mockApp->applicant = $mockApplicant;
        $mockApp->shouldReceive('getAttribute')->with('applicant')->andReturn($mockApplicant);
        $mockApp->shouldReceive('getAttribute')->with('user_id')->andReturn(10);
        $mockApp->shouldReceive('toArray')->andReturn([
            'id' => 100, 'vacancy_id' => 5, 'user_id' => 10,
            'status' => 'pending', 'stage' => 'applied',
        ]);

        $appsCollection = collect([$mockApp]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('where')->with('vacancy_id', 5)->andReturnSelf();
        $appQuery->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $appQuery->shouldReceive('get')->andReturn($appsCollection);
        $this->getJobApplicationAlias()->shouldReceive('with')->andReturn($appQuery);

        $result = $this->service->getApplications(5, 1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('John Doe', $result[0]['applicant']['name']);
        $this->assertEquals('john@example.com', $result[0]['applicant']['email']);
    }

    public function test_getApplications_anonymizes_for_blind_hiring(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->blind_hiring = true;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockApplicant = Mockery::mock();
        $mockApplicant->first_name = 'Jane';
        $mockApplicant->last_name = 'Smith';
        $mockApplicant->avatar_url = 'avatar.jpg';
        $mockApplicant->email = 'jane@example.com';

        $mockApp = Mockery::mock();
        $mockApp->user_id = 10;
        $mockApp->applicant = $mockApplicant;
        $mockApp->shouldReceive('getAttribute')->with('applicant')->andReturn($mockApplicant);
        $mockApp->shouldReceive('getAttribute')->with('user_id')->andReturn(10);
        $mockApp->shouldReceive('toArray')->andReturn([
            'id' => 100, 'vacancy_id' => 5, 'user_id' => 10,
            'status' => 'pending', 'stage' => 'applied',
        ]);

        $appsCollection = collect([$mockApp]);

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('where')->with('vacancy_id', 5)->andReturnSelf();
        $appQuery->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $appQuery->shouldReceive('get')->andReturn($appsCollection);
        $this->getJobApplicationAlias()->shouldReceive('with')->andReturn($appQuery);

        $result = $this->service->getApplications(5, 1);

        $this->assertIsArray($result);
        $this->assertEquals('Candidate #1', $result[0]['applicant']['name']);
        $this->assertNull($result[0]['applicant']['avatar_url']);
        $this->assertNull($result[0]['applicant']['email']);
    }

    // =========================================================================
    // updateApplicationStatus()
    // =========================================================================

    public function test_updateApplicationStatus_returns_false_for_invalid_status(): void
    {
        $result = $this->service->updateApplicationStatus(1, 1, 'invalid_status');
        $this->assertFalse($result);
        $this->assertEquals('VALIDATION_INVALID_VALUE', $this->service->getErrors()[0]['code']);
    }

    public function test_updateApplicationStatus_returns_false_when_application_not_found(): void
    {
        $this->getJobApplicationAlias()->shouldReceive('with')->andReturnSelf();
        $this->getJobApplicationAlias()->shouldReceive('find')->with(999)->andReturnNull();

        $result = $this->service->updateApplicationStatus(999, 1, 'reviewed');
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_updateApplicationStatus_succeeds_for_owner(): void
    {
        $mockVacancy = Mockery::mock();
        $mockVacancy->user_id = 1;
        $mockVacancy->tenant_id = 2;

        $mockApp = Mockery::mock();
        $mockApp->vacancy = $mockVacancy;
        $mockApp->user_id = 10;
        $mockApp->stage = 'applied';
        $mockApp->status = 'applied';
        $mockApp->vacancy_id = 5;
        $mockApp->shouldReceive('getAttribute')->with('vacancy')->andReturn($mockVacancy);
        $mockApp->shouldReceive('getAttribute')->with('user_id')->andReturn(10);
        $mockApp->shouldReceive('getAttribute')->with('stage')->andReturn('applied');
        $mockApp->shouldReceive('getAttribute')->with('status')->andReturn('applied');
        $mockApp->shouldReceive('getAttribute')->with('vacancy_id')->andReturn(5);
        $mockApp->shouldReceive('update')->andReturn(true);

        $appWithQuery = Mockery::mock();
        $appWithQuery->shouldReceive('find')->with(100)->andReturn($mockApp);
        $this->getJobApplicationAlias()->shouldReceive('with')->with(['vacancy'])->andReturn($appWithQuery);

        // logApplicationHistory
        $this->getJobApplicationHistoryAlias()->shouldReceive('create')->andReturn(Mockery::mock());

        // Webhook
        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        $result = $this->service->updateApplicationStatus(100, 1, 'reviewed', 'Looks good');
        $this->assertTrue($result);
    }

    public function test_updateApplicationStatus_forbidden_for_non_owner(): void
    {
        $mockVacancy = Mockery::mock();
        $mockVacancy->user_id = 1;
        $mockVacancy->tenant_id = 2;

        $mockApp = Mockery::mock();
        $mockApp->vacancy = $mockVacancy;
        $mockApp->shouldReceive('getAttribute')->with('vacancy')->andReturn($mockVacancy);

        $appWithQuery = Mockery::mock();
        $appWithQuery->shouldReceive('find')->with(100)->andReturn($mockApp);
        $this->getJobApplicationAlias()->shouldReceive('with')->with(['vacancy'])->andReturn($appWithQuery);

        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->updateApplicationStatus(100, 99, 'reviewed');
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    // =========================================================================
    // saveJob() / unsaveJob() / getSavedJobs()
    // =========================================================================

    public function test_saveJob_returns_true_when_saved(): void
    {
        $mockJob = Mockery::mock();
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(false);
        $this->savedJobAlias->shouldReceive('where')->with('job_id', 5)->andReturn($existsQuery);

        $this->savedJobAlias->shouldReceive('create')->andReturn(Mockery::mock());

        $result = $this->service->saveJob(5, 10);
        $this->assertTrue($result);
    }

    public function test_saveJob_returns_false_when_job_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->saveJob(999, 10);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_saveJob_idempotent_when_already_saved(): void
    {
        $mockJob = Mockery::mock();
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(true);
        $this->savedJobAlias->shouldReceive('where')->with('job_id', 5)->andReturn($existsQuery);

        $result = $this->service->saveJob(5, 10);
        $this->assertTrue($result);
    }

    public function test_unsaveJob_calls_delete(): void
    {
        $deleteQuery = Mockery::mock();
        $deleteQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $deleteQuery->shouldReceive('delete')->once()->andReturn(1);
        $this->savedJobAlias->shouldReceive('where')->with('job_id', 5)->andReturn($deleteQuery);

        $this->service->unsaveJob(5, 10);
        $this->assertTrue(true); // No exception means success
    }

    public function test_getSavedJobs_returns_paginated_structure(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('join')->andReturnSelf();
        $mockQuery->shouldReceive('leftJoin')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $this->savedJobAlias->shouldReceive('where')->with('saved_jobs.user_id', 10)->andReturn($mockQuery);

        $result = $this->service->getSavedJobs(10);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
    }

    // =========================================================================
    // getMyApplications()
    // =========================================================================

    public function test_getMyApplications_returns_paginated_structure(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $this->getJobApplicationAlias()->shouldReceive('join')->andReturn($mockQuery);

        $result = $this->service->getMyApplications(10);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
    }

    public function test_getMyApplications_with_status_filter(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $this->getJobApplicationAlias()->shouldReceive('join')->andReturn($mockQuery);

        $result = $this->service->getMyApplications(10, ['status' => 'rejected']);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // getMyPostings()
    // =========================================================================

    public function test_getMyPostings_returns_paginated_structure(): void
    {
        $query = $this->buildGetAllQuery();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getMyPostings(1, 2);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    // =========================================================================
    // ALERTS: getAlerts() / subscribeAlert() / deleteAlert() / unsubscribeAlert() / resubscribeAlert()
    // =========================================================================

    public function test_getAlerts_returns_formatted_array(): void
    {
        $mockAlert = Mockery::mock();
        $mockAlert->shouldReceive('toArray')->andReturn([
            'id' => 1, 'user_id' => 10, 'keywords' => 'developer',
            'is_active' => 1, 'is_remote_only' => 0,
        ]);

        $alertQuery = Mockery::mock();
        $alertQuery->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $alertQuery->shouldReceive('get')->andReturn(collect([$mockAlert]));
        $this->getJobAlertAlias()->shouldReceive('where')->with('user_id', 10)->andReturn($alertQuery);

        $result = $this->service->getAlerts(10);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['id']);
        $this->assertTrue($result[0]['is_active']);
        $this->assertFalse($result[0]['is_remote_only']);
    }

    public function test_getAlerts_returns_empty_array_when_none(): void
    {
        $alertQuery = Mockery::mock();
        $alertQuery->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $alertQuery->shouldReceive('get')->andReturn(collect([]));
        $this->getJobAlertAlias()->shouldReceive('where')->with('user_id', 10)->andReturn($alertQuery);

        $result = $this->service->getAlerts(10);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_subscribeAlert_returns_alert_id(): void
    {
        $mockAlert = Mockery::mock();
        $mockAlert->id = 5;
        $this->getJobAlertAlias()->shouldReceive('create')->andReturn($mockAlert);

        $result = $this->service->subscribeAlert(10, [
            'keywords' => 'php developer',
            'type' => 'paid',
            'commitment' => 'full_time',
            'location' => 'Dublin',
            'is_remote_only' => true,
        ]);

        $this->assertEquals(5, $result);
    }

    public function test_subscribeAlert_returns_null_on_failure(): void
    {
        $this->getJobAlertAlias()->shouldReceive('create')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->subscribeAlert(10, ['keywords' => 'test']);
        $this->assertNull($result);
    }

    public function test_subscribeAlert_truncates_long_keywords(): void
    {
        $longKeywords = str_repeat('a', 600);
        $mockAlert = Mockery::mock();
        $mockAlert->id = 6;
        $this->getJobAlertAlias()->shouldReceive('create')->with(Mockery::on(function ($data) {
            return strlen($data['keywords']) <= 500;
        }))->andReturn($mockAlert);

        $result = $this->service->subscribeAlert(10, ['keywords' => $longKeywords]);
        $this->assertEquals(6, $result);
    }

    public function test_subscribeAlert_validates_type_values(): void
    {
        $mockAlert = Mockery::mock();
        $mockAlert->id = 7;
        $this->getJobAlertAlias()->shouldReceive('create')->with(Mockery::on(function ($data) {
            return $data['type'] === null; // Invalid type should be null
        }))->andReturn($mockAlert);

        $result = $this->service->subscribeAlert(10, ['type' => 'invalid_type']);
        $this->assertEquals(7, $result);
    }

    public function test_deleteAlert_calls_delete(): void
    {
        $deleteQuery = Mockery::mock();
        $deleteQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $deleteQuery->shouldReceive('delete')->once()->andReturn(1);
        $this->getJobAlertAlias()->shouldReceive('where')->with('id', 5)->andReturn($deleteQuery);

        $this->service->deleteAlert(5, 10);
        $this->assertTrue(true);
    }

    public function test_unsubscribeAlert_sets_inactive(): void
    {
        $updateQuery = Mockery::mock();
        $updateQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $updateQuery->shouldReceive('update')->with(['is_active' => false])->once()->andReturn(1);
        $this->getJobAlertAlias()->shouldReceive('where')->with('id', 5)->andReturn($updateQuery);

        $this->service->unsubscribeAlert(5, 10);
        $this->assertTrue(true);
    }

    public function test_resubscribeAlert_sets_active(): void
    {
        $updateQuery = Mockery::mock();
        $updateQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $updateQuery->shouldReceive('update')->with(['is_active' => true])->once()->andReturn(1);
        $this->getJobAlertAlias()->shouldReceive('where')->with('id', 5)->andReturn($updateQuery);

        $this->service->resubscribeAlert(5, 10);
        $this->assertTrue(true);
    }

    // =========================================================================
    // calculateMatchPercentage()
    // =========================================================================

    public function test_calculateMatchPercentage_full_match(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'php, javascript, react';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        $mockJob->skills_required = 'php, javascript, react';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);

        $this->assertEquals(100, $result['percentage']);
        $this->assertCount(3, $result['matched']);
        $this->assertEmpty($result['missing']);
    }

    public function test_calculateMatchPercentage_no_match(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'painting, cooking, gardening';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        $mockJob->skills_required = 'php, javascript, react';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);

        $this->assertEquals(0, $result['percentage']);
        $this->assertEmpty($result['matched']);
        $this->assertCount(3, $result['missing']);
    }

    public function test_calculateMatchPercentage_partial_match(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'php, python';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        $mockJob->skills_required = 'php, javascript, react';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);

        $this->assertEquals(33, $result['percentage']); // 1/3 = 33%
        $this->assertCount(1, $result['matched']);
        $this->assertCount(2, $result['missing']);
    }

    public function test_calculateMatchPercentage_returns_100_when_no_skills_required(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'php, javascript';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        $mockJob->skills_required = '';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);
        $this->assertEquals(100, $result['percentage']);
    }

    public function test_calculateMatchPercentage_returns_0_when_no_user_skills(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = '';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        $mockJob->skills_required = 'php, javascript';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);
        $this->assertEquals(0, $result['percentage']);
        $this->assertCount(2, $result['missing']);
    }

    public function test_calculateMatchPercentage_fuzzy_matching(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'javascript';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $mockJob = Mockery::mock();
        // 'javascript developer' contains 'javascript', so it should match via str_contains
        $mockJob->skills_required = 'javascript developer';
        $jobQuery = Mockery::mock();
        $jobQuery->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($jobQuery);

        $result = $this->service->calculateMatchPercentage(10, 5);
        $this->assertEquals(100, $result['percentage']);
    }

    // =========================================================================
    // getQualificationAssessment()
    // =========================================================================

    public function test_getQualificationAssessment_returns_null_when_job_not_found(): void
    {
        // legacyGetById returns null
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getQualificationAssessment(10, 999);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getQualificationAssessment_returns_assessment_structure(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, [
            'title' => 'PHP Developer',
            'skills_required' => 'php, laravel',
            'commitment' => 'full_time',
            'is_remote' => true,
            'salary_min' => 40000,
            'salary_negotiable' => false,
        ]);

        // First call: legacyGetById
        $query1 = Mockery::mock();
        $query1->shouldReceive('leftJoin')->andReturnSelf();
        $query1->shouldReceive('select')->andReturnSelf();
        $query1->shouldReceive('where')->andReturnSelf();
        $query1->shouldReceive('first')->andReturn($mockJob);

        // Second call: calculateMatchPercentage -> find job skills
        $mockJobForSkills = Mockery::mock();
        $mockJobForSkills->skills_required = 'php, laravel';
        $query2 = Mockery::mock();
        $query2->shouldReceive('find')->with(5, ['id', 'skills_required'])->andReturn($mockJobForSkills);

        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query1, $query2);

        // Mock User for enrichVacancy and calculateMatchPercentage
        $mockUser = Mockery::mock();
        $mockUser->skills = 'php, laravel, javascript';
        $mockUser->latitude = null;
        $mockUser->longitude = null;
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->andReturnSelf();
        $this->userAlias->shouldReceive('first')->andReturn($mockUser);

        // Mock DB for enrichVacancy has_applied/is_saved
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $this->savedJobAlias->shouldReceive('where')->andReturnSelf();
        $this->savedJobAlias->shouldReceive('exists')->andReturn(false);

        $result = $this->service->getQualificationAssessment(10, 5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('dimensions', $result);
        $this->assertArrayHasKey('ai_summary', $result);
        $this->assertEquals(5, $result['job_id']);
    }

    // =========================================================================
    // getApplicationHistory()
    // =========================================================================

    public function test_getApplicationHistory_returns_null_when_not_found(): void
    {
        $this->getJobApplicationAlias()->shouldReceive('with')->with(['vacancy'])->andReturnSelf();
        $this->getJobApplicationAlias()->shouldReceive('find')->with(999)->andReturnNull();

        $result = $this->service->getApplicationHistory(999, 10);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getApplicationHistory_forbidden_for_non_participant(): void
    {
        $mockVacancy = Mockery::mock();
        $mockVacancy->user_id = 1;
        $mockVacancy->tenant_id = 2;

        $mockApp = Mockery::mock();
        $mockApp->vacancy = $mockVacancy;
        $mockApp->user_id = 10;
        $mockApp->shouldReceive('getAttribute')->with('vacancy')->andReturn($mockVacancy);
        $mockApp->shouldReceive('getAttribute')->with('user_id')->andReturn(10);

        $appWithQuery = Mockery::mock();
        $appWithQuery->shouldReceive('find')->with(100)->andReturn($mockApp);
        $this->getJobApplicationAlias()->shouldReceive('with')->with(['vacancy'])->andReturn($appWithQuery);

        // User 99 is not applicant (10), not owner (1), and not admin
        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->getApplicationHistory(100, 99);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_getApplicationHistory_allowed_for_applicant(): void
    {
        $mockVacancy = Mockery::mock();
        $mockVacancy->user_id = 1;
        $mockVacancy->tenant_id = 2;

        $mockApp = Mockery::mock();
        $mockApp->vacancy = $mockVacancy;
        $mockApp->user_id = 10;
        $mockApp->shouldReceive('getAttribute')->with('vacancy')->andReturn($mockVacancy);
        $mockApp->shouldReceive('getAttribute')->with('user_id')->andReturn(10);

        $appWithQuery = Mockery::mock();
        $appWithQuery->shouldReceive('find')->with(100)->andReturn($mockApp);
        $this->getJobApplicationAlias()->shouldReceive('with')->with(['vacancy'])->andReturn($appWithQuery);

        // Mock the history query
        $mockHistory = Mockery::mock();
        $mockHistory->shouldReceive('toArray')->andReturn([
            'id' => 1, 'application_id' => 100,
            'from_status' => null, 'to_status' => 'applied',
        ]);
        $mockChanger = Mockery::mock();
        $mockChanger->first_name = 'System';
        $mockChanger->last_name = '';
        $mockHistory->changer = $mockChanger;
        $mockHistory->shouldReceive('getAttribute')->with('changer')->andReturn($mockChanger);

        $historyQuery = Mockery::mock();
        $historyQuery->shouldReceive('where')->with('application_id', 100)->andReturnSelf();
        $historyQuery->shouldReceive('orderBy')->with('changed_at')->andReturnSelf();
        $historyQuery->shouldReceive('get')->andReturn(collect([$mockHistory]));
        $this->getJobApplicationHistoryAlias()->shouldReceive('with')->with(['changer:id,first_name,last_name'])->andReturn($historyQuery);

        $result = $this->service->getApplicationHistory(100, 10);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // =========================================================================
    // getAnalytics()
    // =========================================================================

    public function test_getAnalytics_returns_null_when_not_found(): void
    {
        // legacyGetById returns null
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAnalytics(999, 1);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_getAnalytics_forbidden_for_non_owner(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->getAnalytics(5, 99);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    // =========================================================================
    // renewJob()
    // =========================================================================

    public function test_renewJob_returns_true_on_success(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->deadline = null;
        $mockJob->renewal_count = 0;
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->renewJob(5, 1, 30);
        $this->assertTrue($result);
    }

    public function test_renewJob_returns_false_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->renewJob(999, 1);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_renewJob_forbidden_for_non_owner(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'member';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->renewJob(5, 99);
        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_renewJob_allowed_for_admin(): void
    {
        $mockJob = Mockery::mock();
        $mockJob->user_id = 1;
        $mockJob->deadline = null;
        $mockJob->renewal_count = 2;
        $mockJob->shouldReceive('update')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('find')->with(5)->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $mockUser = Mockery::mock();
        $mockUser->role = 'super_admin';
        $mockUserQuery = Mockery::mock();
        $mockUserQuery->shouldReceive('first')->andReturn($mockUser);
        $this->userAlias->shouldReceive('where')->with('id', 99)->andReturn($mockUserQuery);

        $result = $this->service->renewJob(5, 99, 15);
        $this->assertTrue($result);
    }

    // =========================================================================
    // getRecommended()
    // =========================================================================

    public function test_getRecommended_returns_empty_when_no_jobs(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = 'php, laravel';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        // Mock applied IDs query
        $appliedQuery = Mockery::mock();
        $appliedQuery->shouldReceive('pluck')->with('vacancy_id')->andReturn(collect([]));
        $this->getJobApplicationAlias()->shouldReceive('where')->with('user_id', 10)->andReturn($appliedQuery);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->with(200)->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getRecommended(10);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_getRecommended_limits_between_1_and_20(): void
    {
        $mockUser = Mockery::mock();
        $mockUser->skills = '';
        $this->userAlias->shouldReceive('find')->with(10, ['id', 'skills'])->andReturn($mockUser);

        $appliedQuery = Mockery::mock();
        $appliedQuery->shouldReceive('pluck')->with('vacancy_id')->andReturn(collect([]));
        $this->getJobApplicationAlias()->shouldReceive('where')->with('user_id', 10)->andReturn($appliedQuery);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->with(200)->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getRecommended(10, 100); // 100 > 20, should be clamped
        $this->assertIsArray($result);
    }

    // =========================================================================
    // exportApplicationsCsv()
    // =========================================================================

    public function test_exportApplicationsCsv_returns_null_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->exportApplicationsCsv(999, 1);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_exportApplicationsCsv_forbidden_for_non_owner(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->exportApplicationsCsv(5, 99);
        $this->assertNull($result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_exportApplicationsCsv_returns_csv_string(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock enrichVacancy dependencies
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();
        $this->savedJobAlias->shouldReceive('where')->andReturnSelf();
        $this->savedJobAlias->shouldReceive('exists')->andReturn(false);

        // Mock the applications query for CSV
        $mockApplicant = Mockery::mock();
        $mockApplicant->first_name = 'John';
        $mockApplicant->last_name = 'Doe';
        $mockApplicant->email = 'john@example.com';

        $mockApp = Mockery::mock();
        $mockApp->id = 100;
        $mockApp->applicant = $mockApplicant;
        $mockApp->status = 'pending';
        $mockApp->stage = 'applied';
        $mockApp->created_at = now();
        $mockApp->updated_at = now();

        $appQuery = Mockery::mock();
        $appQuery->shouldReceive('where')->andReturnSelf();
        $appQuery->shouldReceive('orderBy')->with('created_at')->andReturnSelf();
        $appQuery->shouldReceive('get')->andReturn(collect([$mockApp]));
        $this->getJobApplicationAlias()->shouldReceive('with')->andReturn($appQuery);

        $result = $this->service->exportApplicationsCsv(5, 1);

        $this->assertIsString($result);
        $this->assertStringContainsString('ID', $result);
        $this->assertStringContainsString('Name', $result);
        $this->assertStringContainsString('Email', $result);
        $this->assertStringContainsString('John Doe', $result);
    }

    // =========================================================================
    // bulkUpdateApplicationStatus()
    // =========================================================================

    public function test_bulkUpdateApplicationStatus_returns_0_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->bulkUpdateApplicationStatus(999, 1, [1, 2, 3], 'rejected');
        $this->assertEquals(0, $result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_bulkUpdateApplicationStatus_forbidden_for_non_owner(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->bulkUpdateApplicationStatus(5, 99, [1, 2], 'rejected');
        $this->assertEquals(0, $result);
        $this->assertEquals('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_bulkUpdateApplicationStatus_rejects_invalid_status(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock enrichVacancy deps
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();
        $this->savedJobAlias->shouldReceive('where')->andReturnSelf();
        $this->savedJobAlias->shouldReceive('exists')->andReturn(false);

        $result = $this->service->bulkUpdateApplicationStatus(5, 1, [1, 2], 'invalid_status');
        $this->assertEquals(0, $result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_bulkUpdateApplicationStatus_returns_update_count(): void
    {
        $mockJob = $this->makeMockVacancyRow(5, ['user_id' => 1]);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock enrichVacancy deps
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();
        $this->savedJobAlias->shouldReceive('where')->andReturnSelf();
        $this->savedJobAlias->shouldReceive('exists')->andReturn(false);

        // Mock the bulk update
        $bulkQuery = Mockery::mock();
        $bulkQuery->shouldReceive('where')->andReturnSelf();
        $bulkQuery->shouldReceive('whereIn')->andReturnSelf();
        $bulkQuery->shouldReceive('update')->andReturn(3);
        $this->getJobApplicationAlias()->shouldReceive('where')->andReturn($bulkQuery);

        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        $result = $this->service->bulkUpdateApplicationStatus(5, 1, [1, 2, 3], 'rejected');
        $this->assertEquals(3, $result);
    }

    // =========================================================================
    // findSimilarJobs()
    // =========================================================================

    public function test_findSimilarJobs_returns_empty_for_short_words(): void
    {
        // Title with only short words (<3 chars) should return empty
        $result = $this->service->findSimilarJobs('a b c', null, 2);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_findSimilarJobs_returns_similar_results(): void
    {
        $candidate1 = (object) [
            'id' => 1, 'title' => 'Senior PHP Developer', 'status' => 'open', 'created_at' => '2026-03-01',
        ];
        $candidate2 = (object) [
            'id' => 2, 'title' => 'Junior Python Developer', 'status' => 'open', 'created_at' => '2026-03-02',
        ];

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('whereIn')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->with(20)->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([$candidate1, $candidate2]));
        DB::shouldReceive('table')->with('job_vacancies')->andReturn($mockQuery);

        $result = $this->service->findSimilarJobs('Senior PHP Developer Needed', null, 2);

        $this->assertIsArray($result);
        // candidate1 should match highly (3/4 words match: senior, php, developer)
        // candidate2 should match partially (1/4 words: developer)
        $this->assertNotEmpty($result);
        // First result should have highest similarity
        if (count($result) > 1) {
            $this->assertGreaterThanOrEqual($result[1]['similarity'], $result[0]['similarity']);
        }
    }

    public function test_findSimilarJobs_filters_by_organization(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('whereIn')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('table')->with('job_vacancies')->andReturn($mockQuery);

        $result = $this->service->findSimilarJobs('Developer Position', 5, 2);
        $this->assertIsArray($result);
    }

    public function test_findSimilarJobs_limits_to_5_results(): void
    {
        $candidates = collect();
        for ($i = 1; $i <= 10; $i++) {
            $candidates->push((object) [
                'id' => $i,
                'title' => "Senior Developer Position #{$i}",
                'status' => 'open',
                'created_at' => '2026-03-01',
            ]);
        }

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('whereIn')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('orWhere')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn($candidates);
        DB::shouldReceive('table')->with('job_vacancies')->andReturn($mockQuery);

        $result = $this->service->findSimilarJobs('Senior Developer Position', null, 2);
        $this->assertLessThanOrEqual(5, count($result));
    }

    // =========================================================================
    // parseBooleanQuery() — static method
    // =========================================================================

    public function test_parseBooleanQuery_simple_terms(): void
    {
        $method = new \ReflectionMethod(JobVacancyService::class, 'parseBooleanQuery');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'php developer');
        $this->assertEquals(['php', 'developer'], $result['must']);
        $this->assertEmpty($result['should']);
        $this->assertEmpty($result['not']);
    }

    public function test_parseBooleanQuery_or_terms(): void
    {
        $method = new \ReflectionMethod(JobVacancyService::class, 'parseBooleanQuery');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'php | python');
        $this->assertEmpty($result['must']);
        $this->assertEquals(['php', 'python'], $result['should']);
        $this->assertEmpty($result['not']);
    }

    public function test_parseBooleanQuery_not_terms(): void
    {
        $method = new \ReflectionMethod(JobVacancyService::class, 'parseBooleanQuery');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'developer -junior');
        $this->assertEquals(['developer'], $result['must']);
        $this->assertEmpty($result['should']);
        $this->assertEquals(['junior'], $result['not']);
    }

    public function test_parseBooleanQuery_mixed_operators(): void
    {
        $method = new \ReflectionMethod(JobVacancyService::class, 'parseBooleanQuery');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'php | python -junior');
        $this->assertEmpty($result['must']);
        $this->assertContains('php', $result['should']);
        $this->assertContains('python', $result['should']);
        $this->assertEquals(['junior'], $result['not']);
    }

    public function test_parseBooleanQuery_empty_string(): void
    {
        $method = new \ReflectionMethod(JobVacancyService::class, 'parseBooleanQuery');
        $method->setAccessible(true);

        $result = $method->invoke(null, '');
        $this->assertEmpty($result['must']);
        $this->assertEmpty($result['should']);
        $this->assertEmpty($result['not']);
    }

    // =========================================================================
    // legacyApply()
    // =========================================================================

    public function test_legacyApply_delegates_to_apply(): void
    {
        // This should call apply() with cover_letter from message
        $existsQuery = Mockery::mock();
        $existsQuery->shouldReceive('where')->with('user_id', 10)->andReturnSelf();
        $existsQuery->shouldReceive('exists')->andReturn(false);
        $this->getJobApplicationAlias()->shouldReceive('where')->with('vacancy_id', 5)->andReturn($existsQuery);

        $mockApp = Mockery::mock();
        $mockApp->id = 200;
        $this->getJobApplicationAlias()->shouldReceive('create')->with(Mockery::on(function ($data) {
            return $data['message'] === 'My message';
        }))->andReturn($mockApp);

        $this->getJobApplicationHistoryAlias()->shouldReceive('create')->andReturn(Mockery::mock());

        $incQuery = Mockery::mock();
        $incQuery->shouldReceive('where')->andReturnSelf();
        $incQuery->shouldReceive('increment')->andReturn(1);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($incQuery);

        $this->webhookAlias->shouldReceive('dispatch')->andReturnNull();

        $result = $this->service->legacyApply(5, 10, 'My message');
        $this->assertEquals(200, $result);
    }

    // =========================================================================
    // legacyGetById()
    // =========================================================================

    public function test_legacyGetById_returns_null_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturnNull();
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->legacyGetById(999, 10);
        $this->assertNull($result);
    }

    public function test_legacyGetById_includes_user_enrichment(): void
    {
        $mockJob = $this->makeMockVacancyRow(5);

        $query = Mockery::mock();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($mockJob);
        $this->mockVacancy->shouldReceive('newQuery')->andReturn($query);

        // Mock enrichVacancy DB calls for user context
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $this->savedJobAlias->shouldReceive('where')->andReturnSelf();
        $this->savedJobAlias->shouldReceive('exists')->andReturn(true);

        $result = $this->service->legacyGetById(5, 10);

        $this->assertIsArray($result);
        $this->assertEquals(5, $result['id']);
        $this->assertFalse($result['has_applied']);
        $this->assertTrue($result['is_saved']);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Build a mock query builder for getAll() with common chained method expectations.
     *
     * @param array $extraMethods Additional methods beyond the base set that may be called
     */
    private function buildGetAllQuery(array $extraMethods = []): Mockery\MockInterface
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('leftJoin')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('orderByRaw')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();

        foreach ($extraMethods as $method) {
            $query->shouldReceive($method)->andReturnSelf();
        }

        return $query;
    }

    /**
     * Create a mock vacancy model that returns a standard array from toArray().
     */
    private function makeMockVacancyRow(int $id, array $overrides = []): Mockery\MockInterface
    {
        $defaults = [
            'id' => $id,
            'tenant_id' => 2,
            'user_id' => 1,
            'organization_id' => null,
            'title' => "Test Vacancy #{$id}",
            'description' => 'A test job vacancy',
            'location' => 'Dublin',
            'is_remote' => false,
            'type' => 'volunteer',
            'commitment' => 'flexible',
            'category' => 'general',
            'skills_required' => 'php, laravel',
            'status' => 'open',
            'views_count' => 10,
            'applications_count' => 3,
            'is_featured' => false,
            'featured_until' => null,
            'salary_min' => null,
            'salary_max' => null,
            'salary_negotiable' => false,
            'renewal_count' => 0,
            'blind_hiring' => false,
            'created_at' => '2026-03-01 00:00:00',
            'updated_at' => '2026-03-01 00:00:00',
        ];

        $data = array_merge($defaults, $overrides);

        $mock = Mockery::mock();
        $mock->shouldReceive('toArray')->andReturn($data);

        // Make properties accessible
        foreach ($data as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }
}
