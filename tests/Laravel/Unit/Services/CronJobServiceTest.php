<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CronJobService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CronJobServiceTest extends TestCase
{
    private CronJobService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CronJobService();
    }

    public function test_getStatus_returns_array(): void
    {
        DB::shouldReceive('table')->with('cron_jobs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orWhereNull')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getStatus(2);
        $this->assertIsArray($result);
    }

    public function test_run_records_successful_execution(): void
    {
        DB::shouldReceive('table')->with('cron_job_runs')->andReturnSelf();
        DB::shouldReceive('insert')->once();

        $result = $this->service->run('test_job', 2, function () {
            // no-op task
        });

        $this->assertTrue($result['success']);
        $this->assertSame('test_job', $result['job']);
        $this->assertNull($result['error']);
    }

    public function test_run_records_failed_execution(): void
    {
        DB::shouldReceive('table')->with('cron_job_runs')->andReturnSelf();
        DB::shouldReceive('insert')->once();
        Log::shouldReceive('error')->once();

        $result = $this->service->run('failing_job', 2, function () {
            throw new \RuntimeException('Job failed');
        });

        $this->assertFalse($result['success']);
        $this->assertSame('Job failed', $result['error']);
    }

    public function test_getHistory_returns_array(): void
    {
        DB::shouldReceive('table')->with('cron_job_runs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getHistory('test_job');
        $this->assertIsArray($result);
    }

    public function test_getHistory_clamps_limit_to_100(): void
    {
        DB::shouldReceive('table')->with('cron_job_runs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->with(100)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $this->service->getHistory('test_job', 500);
        $this->assertTrue(true);
    }
}
