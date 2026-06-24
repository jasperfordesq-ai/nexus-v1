<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\RunAdminCronJob;
use App\Services\CronJobRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * RunAdminCronJobTest
 *
 * RunAdminCronJob:
 *   1. Acquires a Cache lock keyed on (method, date-hour).
 *   2. Calls $runner->$method() while holding the lock.
 *   3. Releases the lock in a finally block and resets TenantContext.
 *
 * Tests use a mock CronJobRunner so no actual cron logic runs.
 * Cache is backed by the 'array' driver in test (APP_ENV=testing) so locking
 * works without Redis.
 */
class RunAdminCronJobTest extends TestCase
{
    // ── tests ─────────────────────────────────────────────────────────────────

    /** Job exposes the expected $timeout, $tries, and $failOnTimeout values. */
    public function test_job_has_correct_configuration(): void
    {
        $job = new RunAdminCronJob('processNewsletters');
        $this->assertSame(300, $job->timeout);
        $this->assertSame(1, $job->tries);
        $this->assertTrue($job->failOnTimeout);
    }

    /**
     * handle() invokes the named method on the CronJobRunner when the lock is free.
     */
    public function test_handle_calls_named_method_on_runner(): void
    {
        // Ensure the lock is free by clearing the cache.
        Cache::flush();

        $runner = \Mockery::mock(CronJobRunner::class);
        $runner->shouldReceive('processNewsletters')
            ->once();

        $job = new RunAdminCronJob('processNewsletters');
        $job->handle($runner);
        $this->assertTrue(true);
    }

    /**
     * handle() with a different method name calls that method, not 'processNewsletters'.
     */
    public function test_handle_calls_correct_method_by_name(): void
    {
        Cache::flush();

        $runner = \Mockery::mock(CronJobRunner::class);
        $runner->shouldReceive('cleanup')->once();
        $runner->shouldNotReceive('processNewsletters');

        $job = new RunAdminCronJob('cleanup');
        $job->handle($runner);
        $this->assertTrue(true);
    }

    /**
     * handle() no-ops (does NOT call the runner) when the lock is already held.
     * This simulates a re-delivered duplicate copy of the job arriving during
     * the same hour.
     */
    public function test_handle_suppresses_duplicate_when_lock_already_held(): void
    {
        Cache::flush();

        $method   = 'processNewsletters';
        $lockKey  = 'admin-cron:' . $method . ':' . date('Y-m-d-H');

        // Acquire the lock externally so the job can't get it.
        $lock = Cache::lock($lockKey, 600);
        $lock->get();

        try {
            $runner = \Mockery::mock(CronJobRunner::class);
            $runner->shouldNotReceive($method);

            $job = new RunAdminCronJob($method);
            $job->handle($runner);
            $this->assertTrue(true);
        } finally {
            $lock->release();
        }
    }

    /**
     * handle() still releases the lock even when the runner method throws.
     * After handle() the lock must be acquirable again.
     */
    public function test_handle_releases_lock_even_when_runner_throws(): void
    {
        Cache::flush();

        $runner = \Mockery::mock(CronJobRunner::class);
        $runner->shouldReceive('processNewsletters')
            ->once()
            ->andThrow(new \RuntimeException('simulated cron failure'));

        $job = new RunAdminCronJob('processNewsletters');

        try {
            $job->handle($runner);
        } catch (\Throwable) {
            // Expected — the job re-throws non-caught exceptions.
        }

        // The lock must have been released in the finally block, so we can acquire it.
        $lockKey = 'admin-cron:processNewsletters:' . date('Y-m-d-H');
        $lock    = Cache::lock($lockKey, 5);
        $acquired = $lock->get();
        if ($acquired) {
            $lock->release();
        }
        $this->assertTrue($acquired, 'Lock must be released after handle() even on exception');
    }

    /**
     * failed() logs an error-level entry (observable via Log spy).
     */
    public function test_failed_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('RunAdminCronJob failed permanently', \Mockery::on(fn ($ctx) =>
                isset($ctx['method'], $ctx['error'])
                && $ctx['method'] === 'dailyDigest'
            ));

        $job = new RunAdminCronJob('dailyDigest');
        $job->failed(new \RuntimeException('boom'));
        $this->assertTrue(true);
    }

    /**
     * Each unique method name gets a separate lock key.
     * Demonstrate that locking 'cleanup' doesn't block 'processNewsletters'.
     */
    public function test_lock_is_scoped_per_method_name(): void
    {
        Cache::flush();

        // Acquire lock for 'cleanup'.
        $cleanupKey  = 'admin-cron:cleanup:' . date('Y-m-d-H');
        $cleanupLock = Cache::lock($cleanupKey, 600);
        $cleanupLock->get();

        try {
            // 'processNewsletters' must still be able to run.
            $runner = \Mockery::mock(CronJobRunner::class);
            $runner->shouldReceive('processNewsletters')->once();

            $job = new RunAdminCronJob('processNewsletters');
            $job->handle($runner);
            $this->assertTrue(true);
        } finally {
            $cleanupLock->release();
        }
    }
}
