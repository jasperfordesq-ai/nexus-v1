<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * QueueHeartbeatJob — end-to-end proof that queue workers process jobs.
 *
 * The scheduler dispatches this every 5 minutes (bootstrap/app.php) and
 * stamps `queue_heartbeat:dispatched_at`; this job stamps
 * `queue_heartbeat:processed_at` when a worker actually runs it. The
 * `queue:verify-liveness` command compares the two and raises an alert when
 * heartbeats are dispatched but stop coming back.
 *
 * Why: container healthchecks only prove the Horizon MASTER process exists.
 * In the 2026-06-06→11 outage every spawned worker crashed at boot for 5
 * days while Horizon and Docker both reported "healthy". A cheap job that
 * must round-trip through a real worker is the only signal that can't lie.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PROCESSED_AT_KEY = 'queue_heartbeat:processed_at';

    /** Stamps live for a day — long enough to debug, short enough to expire. */
    public const STAMP_TTL_SECONDS = 86400;

    /** A heartbeat is trivial and idempotent — never retry, never linger. */
    public int $tries = 1;

    public int $timeout = 30;

    public function handle(): void
    {
        Cache::put(self::PROCESSED_AT_KEY, now()->getTimestamp(), self::STAMP_TTL_SECONDS);
    }
}
