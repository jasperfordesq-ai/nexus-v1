<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Services\JobModerationService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

class JobModerationServiceTest extends TestCase
{
    // ── isModerationEnabled ──────────────────────────────────────

    public function test_isModerationEnabled_returns_false_when_setting_not_set(): void
    {
        $this->setTenantConfig([]);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertFalse($result);
    }

    public function test_isModerationEnabled_returns_true_when_setting_is_boolean_true(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => true]);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertTrue($result);
    }

    public function test_isModerationEnabled_returns_false_when_setting_is_boolean_false(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => false]);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertFalse($result);
    }

    public function test_isModerationEnabled_returns_true_when_setting_is_string_true(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => 'true']);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertTrue($result);
    }

    public function test_isModerationEnabled_returns_true_when_setting_is_string_1(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => '1']);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertTrue($result);
    }

    public function test_isModerationEnabled_returns_true_when_setting_is_string_yes(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => 'yes']);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertTrue($result);
    }

    public function test_isModerationEnabled_returns_false_when_setting_is_string_no(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => 'no']);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertFalse($result);
    }

    public function test_isModerationEnabled_returns_false_when_setting_is_string_false(): void
    {
        $this->setTenantConfig(['jobs_require_moderation' => 'false']);

        $result = JobModerationService::isModerationEnabled(2);

        $this->assertFalse($result);
    }

    // ── getPendingJobs ───────────────────────────────────────────

    public function test_getPendingJobs_returns_items_and_total_keys(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        $queryMock->shouldReceive('where')->with('moderation_status', 'pending_review')->andReturnSelf();
        $queryMock->shouldReceive('with')->andReturnSelf();
        $queryMock->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $queryMock->shouldReceive('count')->andReturn(0);
        $queryMock->shouldReceive('offset')->with(0)->andReturnSelf();
        $queryMock->shouldReceive('limit')->with(50)->andReturnSelf();
        $queryMock->shouldReceive('get')->andReturn(collect([]));

        JobVacancy::shouldReceive('where')->with('tenant_id', 2)->andReturn($queryMock);

        $result = JobModerationService::getPendingJobs(2);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['items']);
    }

    public function test_getPendingJobs_respects_limit_and_offset(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        $queryMock->shouldReceive('where')->with('moderation_status', 'pending_review')->andReturnSelf();
        $queryMock->shouldReceive('with')->andReturnSelf();
        $queryMock->shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        $queryMock->shouldReceive('count')->andReturn(5);
        $queryMock->shouldReceive('offset')->with(10)->andReturnSelf();
        $queryMock->shouldReceive('limit')->with(25)->andReturnSelf();
        $queryMock->shouldReceive('get')->andReturn(collect([]));

        JobVacancy::shouldReceive('where')->with('tenant_id', 2)->andReturn($queryMock);

        $result = JobModerationService::getPendingJobs(2, 25, 10);

        $this->assertSame(5, $result['total']);
    }

    public function test_getPendingJobs_maps_job_fields_correctly(): void
    {
        $creator = (object) [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ];
        $job = (object) [
            'id' => 42,
            'title' => 'Test Job',
            'description' => 'A test description',
            'type' => 'full_time',
            'category' => 'tech',
            'location' => 'Remote',
            'status' => 'draft',
            'moderation_status' => 'pending_review',
            'moderation_notes' => null,
            'spam_score' => 10,
            'spam_flags' => '[]',
            'created_at' => now(),
            'creator' => $creator,
            'user_id' => 7,
        ];

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('with')->andReturnSelf();
        $queryMock->shouldReceive('orderByDesc')->andReturnSelf();
        $queryMock->shouldReceive('count')->andReturn(1);
        $queryMock->shouldReceive('offset')->andReturnSelf();
        $queryMock->shouldReceive('limit')->andReturnSelf();
        $queryMock->shouldReceive('get')->andReturn(collect([$job]));

        JobVacancy::shouldReceive('where')->with('tenant_id', 2)->andReturn($queryMock);

        $result = JobModerationService::getPendingJobs(2);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame(42, $item['id']);
        $this->assertSame('Test Job', $item['title']);
        $this->assertSame('John Doe', $item['poster_name']);
        $this->assertSame('https://example.com/avatar.jpg', $item['poster_avatar']);
        $this->assertSame(7, $item['user_id']);
    }

    // ── approveJob ───────────────────────────────────────────────

    public function test_approveJob_returns_true_on_success(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['moderation_status'] === 'approved'
                && $data['status'] === 'open'
                && array_key_exists('moderated_by', $data)
                && array_key_exists('moderated_at', $data);
        }))->andReturn(true);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('info')->once();

        $result = JobModerationService::approveJob(42, 5);

        $this->assertTrue($result);
    }

    public function test_approveJob_stores_notes(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['moderation_notes'] === 'Looks good to me';
        }))->andReturn(true);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('info')->once();

        $result = JobModerationService::approveJob(42, 5, 'Looks good to me');

        $this->assertTrue($result);
    }

    public function test_approveJob_returns_false_when_job_not_found(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn(null);

        JobVacancy::shouldReceive('where')->with('id', 999)->andReturn($queryMock);

        $result = JobModerationService::approveJob(999, 5);

        $this->assertFalse($result);
    }

    public function test_approveJob_returns_false_on_exception(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->andThrow(new \RuntimeException('DB error'));

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('error')->once();

        $result = JobModerationService::approveJob(42, 5);

        $this->assertFalse($result);
    }

    // ── rejectJob ────────────────────────────────────────────────

    public function test_rejectJob_returns_true_on_success(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['moderation_status'] === 'rejected'
                && $data['status'] === 'closed'
                && $data['moderation_notes'] === 'Violates policy'
                && array_key_exists('moderated_by', $data)
                && array_key_exists('moderated_at', $data);
        }))->andReturn(true);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('info')->once();

        $result = JobModerationService::rejectJob(42, 5, 'Violates policy');

        $this->assertTrue($result);
    }

    public function test_rejectJob_returns_false_when_job_not_found(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn(null);

        JobVacancy::shouldReceive('where')->with('id', 999)->andReturn($queryMock);

        $result = JobModerationService::rejectJob(999, 5, 'spam');

        $this->assertFalse($result);
    }

    public function test_rejectJob_returns_false_on_exception(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->andThrow(new \RuntimeException('DB error'));

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('error')->once();

        $result = JobModerationService::rejectJob(42, 5, 'Violates policy');

        $this->assertFalse($result);
    }

    // ── flagJob ──────────────────────────────────────────────────

    public function test_flagJob_returns_true_on_success(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->once()->with(Mockery::on(function ($data) {
            return $data['moderation_status'] === 'flagged'
                && $data['moderation_notes'] === 'Suspicious content'
                && array_key_exists('moderated_by', $data)
                && array_key_exists('moderated_at', $data)
                && !array_key_exists('status', $data);
        }))->andReturn(true);

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('info')->once();

        $result = JobModerationService::flagJob(42, 5, 'Suspicious content');

        $this->assertTrue($result);
    }

    public function test_flagJob_returns_false_when_job_not_found(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn(null);

        JobVacancy::shouldReceive('where')->with('id', 999)->andReturn($queryMock);

        $result = JobModerationService::flagJob(999, 5, 'Suspicious');

        $this->assertFalse($result);
    }

    public function test_flagJob_returns_false_on_exception(): void
    {
        $jobMock = Mockery::mock(JobVacancy::class)->makePartial();
        $jobMock->shouldReceive('update')->andThrow(new \RuntimeException('DB error'));

        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        $queryMock->shouldReceive('first')->andReturn($jobMock);

        JobVacancy::shouldReceive('where')->with('id', 42)->andReturn($queryMock);
        Log::shouldReceive('error')->once();

        $result = JobModerationService::flagJob(42, 5, 'Suspicious');

        $this->assertFalse($result);
    }

    // ── getModerationStats ───────────────────────────────────────

    public function test_getModerationStats_returns_all_expected_keys(): void
    {
        // Each call to JobVacancy::where() returns a fresh query mock
        // The service calls where('tenant_id', ...) five times for five counts.
        $pendingQuery = Mockery::mock();
        $pendingQuery->shouldReceive('where')->with('moderation_status', 'pending_review')->andReturnSelf();
        $pendingQuery->shouldReceive('count')->andReturn(3);

        $approvedQuery = Mockery::mock();
        $approvedQuery->shouldReceive('where')->with('moderation_status', 'approved')->andReturnSelf();
        $approvedQuery->shouldReceive('where')->with('moderated_at', '>=', Mockery::any())->andReturnSelf();
        $approvedQuery->shouldReceive('count')->andReturn(5);

        $rejectedQuery = Mockery::mock();
        $rejectedQuery->shouldReceive('where')->with('moderation_status', 'rejected')->andReturnSelf();
        $rejectedQuery->shouldReceive('where')->with('moderated_at', '>=', Mockery::any())->andReturnSelf();
        $rejectedQuery->shouldReceive('count')->andReturn(2);

        $flaggedQuery = Mockery::mock();
        $flaggedQuery->shouldReceive('where')->with('moderation_status', 'flagged')->andReturnSelf();
        $flaggedQuery->shouldReceive('count')->andReturn(1);

        $totalQuery = Mockery::mock();
        $totalQuery->shouldReceive('whereNotNull')->with('moderated_at')->andReturnSelf();
        $totalQuery->shouldReceive('count')->andReturn(8);

        JobVacancy::shouldReceive('where')
            ->with('tenant_id', 2)
            ->andReturn($pendingQuery, $approvedQuery, $rejectedQuery, $flaggedQuery, $totalQuery);

        $result = JobModerationService::getModerationStats(2);

        $this->assertSame(3, $result['pending']);
        $this->assertSame(5, $result['approved_today']);
        $this->assertSame(2, $result['rejected_today']);
        $this->assertSame(1, $result['flagged']);
        $this->assertSame(8, $result['total_reviewed']);
    }

    public function test_getModerationStats_returns_zeros_when_no_jobs(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('whereNotNull')->andReturnSelf();
        $queryMock->shouldReceive('count')->andReturn(0);

        JobVacancy::shouldReceive('where')->with('tenant_id', 2)->andReturn($queryMock);

        $result = JobModerationService::getModerationStats(2);

        $this->assertSame(0, $result['pending']);
        $this->assertSame(0, $result['approved_today']);
        $this->assertSame(0, $result['rejected_today']);
        $this->assertSame(0, $result['flagged']);
        $this->assertSame(0, $result['total_reviewed']);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Set the tenant context with specific configuration for testing TenantContext::getSetting.
     */
    private function setTenantConfig(array $config): void
    {
        $ref = new \ReflectionClass(TenantContext::class);
        $prop = $ref->getProperty('tenant');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => $this->testTenantId,
            'name' => 'Hour Timebank',
            'slug' => $this->testTenantSlug,
            'domain' => null,
            'is_active' => true,
            'features' => '{}',
            'configuration' => json_encode($config),
        ]);
    }
}
