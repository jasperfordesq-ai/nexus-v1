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
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            $runner = app(CronJobRunner::class);
            $runner->runAll();
        })
            ->everyMinute()
            ->withoutOverlapping(10)
            ->name('nexus:run-all')
            ->runInBackground();

        $schedule->call(function () {
            JobExpiryNotificationService::notifyExpiringSoon();
        })
            ->dailyAt('08:00')
            ->name('job-expiry-notifications')
            ->withoutOverlapping();

        $schedule->command('safeguarding:clear-expired-monitoring')
            ->daily()
            ->withoutOverlapping()
            ->name('safeguarding-clear-expired-monitoring');

        $schedule->command('safeguarding:purge-message-copies')
            ->weekly()
            ->withoutOverlapping()
            ->name('safeguarding-purge-message-copies');

        $schedule->command('federation:purge-external-logs')
            ->daily()
            ->withoutOverlapping()
            ->name('federation-purge-external-logs');

        $schedule->command('sitemap:generate')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->name('sitemap-generate');

        $schedule->call(function () {
            app(FeedService::class)->publishScheduledPosts();
        })
            ->everyMinute()
            ->withoutOverlapping(5)
            ->name('feed:publish-scheduled-posts');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
