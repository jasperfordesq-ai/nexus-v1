<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console;

use App\Services\CronJobRunner;
use App\Services\FeedService;
use App\Services\JobExpiryNotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * CronJobRunner::runAll() already contains internal time-checking logic that
     * determines which tasks to execute based on the current minute, hour, and
     * day of week. We schedule it to run every minute and let its internal
     * scheduling handle the rest. This is the simplest, safest migration path
     * from the legacy cron setup.
     *
     * Individual public methods on CronJobRunner remain callable via the admin
     * panel HTTP endpoints for manual triggering.
     */
    /**
     * Schedule is defined in bootstrap/app.php via withSchedule() — single source of truth.
     * Do NOT add tasks here to avoid double registration.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Intentionally empty — see bootstrap/app.php
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
