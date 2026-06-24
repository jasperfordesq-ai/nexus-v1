<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Jobs;

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * QueueHeartbeatJobTest
 *
 * Verifies that QueueHeartbeatJob stamps the processed_at cache key with the
 * current Unix timestamp and respects the declared TTL. This job is the
 * end-to-end proof that queue workers are actually processing jobs — the test
 * confirms the observable side-effect: a cache entry at PROCESSED_AT_KEY.
 */
class QueueHeartbeatJobTest extends TestCase
{
    // No DatabaseTransactions — job is pure cache, no DB.

    protected function setUp(): void
    {
        parent::setUp();
        // Wipe any stale key from a prior test run.
        Cache::forget(QueueHeartbeatJob::PROCESSED_AT_KEY);
    }

    // ── tests ──────────────────────────────────────────────────────────────────

    /** Happy path: handle() writes the processed_at cache key. */
    public function test_handle_stamps_processed_at_cache_key(): void
    {
        $before = now()->getTimestamp();

        $job = new QueueHeartbeatJob();
        $job->handle();

        $this->assertTrue(
            Cache::has(QueueHeartbeatJob::PROCESSED_AT_KEY),
            'processed_at key must exist in cache after handle()'
        );

        $stamp = Cache::get(QueueHeartbeatJob::PROCESSED_AT_KEY);
        $after  = now()->getTimestamp();

        $this->assertIsInt((int) $stamp, 'stamp must be an integer (Unix timestamp)');
        $this->assertGreaterThanOrEqual($before, (int) $stamp, 'stamp must be >= time before handle()');
        $this->assertLessThanOrEqual($after, (int) $stamp, 'stamp must be <= time after handle()');
    }

    /** The cached value is a Unix timestamp (integer seconds). */
    public function test_handle_stores_unix_timestamp(): void
    {
        $job = new QueueHeartbeatJob();
        $job->handle();

        $stamp = Cache::get(QueueHeartbeatJob::PROCESSED_AT_KEY);

        // A Unix timestamp for "now" is a 10-digit integer around 1.7 billion.
        $this->assertGreaterThan(1_700_000_000, (int) $stamp, 'stamp looks like a real Unix timestamp');
    }

    /** Calling handle() twice overwrites the previous stamp. */
    public function test_handle_overwrites_existing_stamp(): void
    {
        // Seed a stale-looking stamp.
        Cache::put(QueueHeartbeatJob::PROCESSED_AT_KEY, 1000, 3600);

        $job = new QueueHeartbeatJob();
        $job->handle();

        $stamp = (int) Cache::get(QueueHeartbeatJob::PROCESSED_AT_KEY);
        $this->assertGreaterThan(1000, $stamp, 'second handle() must overwrite the stale stamp');
    }

    /** The stamp TTL constant must be one day (86 400 seconds). */
    public function test_stamp_ttl_constant_is_one_day(): void
    {
        $this->assertSame(86400, QueueHeartbeatJob::STAMP_TTL_SECONDS);
    }

    /** The PROCESSED_AT_KEY constant has the expected value. */
    public function test_processed_at_key_constant(): void
    {
        $this->assertSame('queue_heartbeat:processed_at', QueueHeartbeatJob::PROCESSED_AT_KEY);
    }

    /** Job declares tries=1 so it never retries. */
    public function test_job_declares_one_try(): void
    {
        $job = new QueueHeartbeatJob();
        $this->assertSame(1, $job->tries);
    }

    /** Job declares a 30-second timeout — trivial job, must not linger. */
    public function test_job_declares_thirty_second_timeout(): void
    {
        $job = new QueueHeartbeatJob();
        $this->assertSame(30, $job->timeout);
    }
}
