<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CronJobService — Laravel DI-based service for cron job monitoring.
 *
 * Tracks scheduled job execution status and history for admin dashboards.
 */
class CronJobService
{
    /**
     * Get the status of all registered cron jobs.
     */
    public function getStatus(int $tenantId): array
    {
        $jobs = DB::table('cron_jobs')
            ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            ->orderBy('name')
            ->get()
            ->map(fn ($j) => (array) $j)
            ->all();

        return $jobs;
    }

    /**
     * Record a cron job execution.
     */
    public function run(string $jobName, int $tenantId, callable $task): array
    {
        $startedAt = now();
        $success = false;
        $errorMsg = null;

        try {
            $task();
            $success = true;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("CronJobService: {$jobName} failed", ['error' => $errorMsg]);
        }

        $duration = now()->diffInSeconds($startedAt);

        DB::table('cron_job_runs')->insert([
            'job_name'   => $jobName,
            'tenant_id'  => $tenantId,
            'started_at' => $startedAt,
            'duration_s' => $duration,
            'success'    => $success,
            'error'      => $errorMsg,
            'created_at' => now(),
        ]);

        return ['job' => $jobName, 'success' => $success, 'duration_s' => $duration, 'error' => $errorMsg];
    }

    /**
     * Get recent run history for a specific cron job.
     */
    public function getHistory(string $jobName, int $limit = 20): array
    {
        return DB::table('cron_job_runs')
            ->where('job_name', $jobName)
            ->orderByDesc('created_at')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
