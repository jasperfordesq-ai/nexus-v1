<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Jobs;

use App\Core\TenantContext;
use App\Services\CronJobRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RunAdminCronJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /**
     * Fail fast rather than letting redis re-deliver mid-flight. The queue's
     * retry_after is 90s but the timeout is 300s, so a long-running cron method
     * would be re-delivered to a second worker and run concurrently. Killing
     * after one attempt (and failing on timeout) plus the lock in handle()
     * keeps one trigger → one run.
     */
    public int $tries = 1;
    public bool $failOnTimeout = true;

    public function __construct(private readonly string $method) {}

    public function handle(CronJobRunner $runner): void
    {
        // Acquire exclusive lock — prevents a re-delivered copy (retry_after=90s
        // < timeout=300s) from running the same cron method concurrently.
        // Keyed on (method, date-hour) so a re-delivery within the hour no-ops.
        $lock = Cache::lock('admin-cron:' . $this->method . ':' . date('Y-m-d-H'), 600);
        if (!$lock->get()) {
            Log::info('RunAdminCronJob: duplicate delivery suppressed', ['method' => $this->method]);
            return;
        }

        try {
            $method = $this->method;
            $runner->$method();
        } finally {
            $lock->release();
            TenantContext::reset();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunAdminCronJob failed permanently', [
            'method' => $this->method,
            'error'  => $e->getMessage(),
        ]);
    }
}
