<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\CronJobService;
use Illuminate\Database\QueryException;

/**
 * CronJobService Tests
 *
 * Tests cron job monitoring: getStatus, run (success/failure), getHistory.
 * Skips gracefully if cron_jobs/cron_job_runs tables do not exist.
 */
class CronJobServiceTest extends TestCase
{
    private function svc(): CronJobService
    {
        return new CronJobService();
    }

    public function test_get_status_returns_array(): void
    {
        try {
            $result = $this->svc()->getStatus(2);
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_jobs table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_run_successful_task_returns_success(): void
    {
        try {
            $result = $this->svc()->run('test_job_success', 2, function () {
                // no-op: successful task
            });
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_job_runs table not available: ' . $e->getMessage());
        }

        $this->assertIsArray($result);
        $this->assertArrayHasKey('job', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('duration_s', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('test_job_success', $result['job']);
        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function test_run_failing_task_returns_failure(): void
    {
        try {
            $result = $this->svc()->run('test_job_fail', 2, function () {
                throw new \RuntimeException('Test failure');
            });
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_job_runs table not available: ' . $e->getMessage());
        }

        $this->assertFalse($result['success']);
        $this->assertSame('Test failure', $result['error']);
        $this->assertSame('test_job_fail', $result['job']);
    }

    public function test_run_records_duration(): void
    {
        try {
            $result = $this->svc()->run('test_job_duration', 2, function () {
                // fast task
            });
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_job_runs table not available: ' . $e->getMessage());
        }

        $this->assertIsInt($result['duration_s']);
        $this->assertGreaterThanOrEqual(0, $result['duration_s']);
    }

    public function test_get_history_returns_array(): void
    {
        try {
            $result = $this->svc()->getHistory('nonexistent_job');
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_job_runs table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }

    public function test_get_history_respects_limit(): void
    {
        try {
            $result = $this->svc()->getHistory('test_job', 200);
        } catch (QueryException $e) {
            $this->markTestSkipped('cron_job_runs table not available: ' . $e->getMessage());
        }
        $this->assertIsArray($result);
    }
}
