<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Console\Commands\VerifyQueueLiveness;
use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

class VerifyQueueLivenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(VerifyQueueLiveness::DISPATCHED_AT_KEY);
        Cache::forget(QueueHeartbeatJob::PROCESSED_AT_KEY);
        Cache::forget(VerifyQueueLiveness::STATUS_KEY);
        Cache::forget('queue_heartbeat:alerted');
    }

    public function test_succeeds_when_no_heartbeat_dispatched_yet(): void
    {
        $this->artisan('queue:verify-liveness')->assertExitCode(0);
    }

    public function test_succeeds_when_heartbeat_round_trips(): void
    {
        Cache::put(VerifyQueueLiveness::DISPATCHED_AT_KEY, now()->getTimestamp() - 60);
        Cache::put(QueueHeartbeatJob::PROCESSED_AT_KEY, now()->getTimestamp() - 30);

        $this->artisan('queue:verify-liveness')->assertExitCode(0);

        $this->assertSame('ok', Cache::get(VerifyQueueLiveness::STATUS_KEY)['state']);
    }

    public function test_fails_when_heartbeats_dispatched_but_never_processed(): void
    {
        Cache::put(VerifyQueueLiveness::DISPATCHED_AT_KEY, now()->getTimestamp() - 120);

        $this->artisan('queue:verify-liveness')->assertExitCode(1);

        $this->assertSame('failed', Cache::get(VerifyQueueLiveness::STATUS_KEY)['state']);
    }

    public function test_fails_when_processing_stamp_is_stale(): void
    {
        Cache::put(VerifyQueueLiveness::DISPATCHED_AT_KEY, now()->getTimestamp() - 60);
        Cache::put(QueueHeartbeatJob::PROCESSED_AT_KEY, now()->getTimestamp() - 3600);

        $this->artisan('queue:verify-liveness')->assertExitCode(1);
    }

    public function test_no_false_alarm_when_dispatch_side_itself_is_stale(): void
    {
        // Scheduler stopped dispatching (e.g. container stopped intentionally)
        // — that is a different failure; don't blame the workers.
        Cache::put(VerifyQueueLiveness::DISPATCHED_AT_KEY, now()->getTimestamp() - 7200);

        $this->artisan('queue:verify-liveness')->assertExitCode(0);
    }

    public function test_heartbeat_job_writes_processing_stamp(): void
    {
        (new QueueHeartbeatJob())->handle();

        $this->assertEqualsWithDelta(
            now()->getTimestamp(),
            Cache::get(QueueHeartbeatJob::PROCESSED_AT_KEY),
            5
        );
    }
}
