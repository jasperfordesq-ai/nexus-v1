<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PrerenderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Reaps prerender_jobs rows whose worker died mid-flight.
 *
 * Failure modes this handles:
 *   - Host reboot mid-render
 *   - prerender-tenants.sh OOM-killed
 *   - A newer deploy SIGTERMed the worker but the finalise step never ran
 *   - The bash job processor crashed between claim and finalise
 *
 * Without this, those rows stay 'claimed' or 'running' forever, distorting
 * dashboard metrics and blocking operator confidence in the queue.
 */
class PrerenderReapStale extends Command
{
    protected $signature = 'prerender:reap-stale '
        . '{--claimed-minutes=10  : Reap claimed rows older than N minutes} '
        . '{--running-minutes=45  : Reap running rows older than N minutes} '
        . '{--dry-run             : Print what would be reaped without changing anything} '
        . '{--requeue             : Reset to queued instead of failing (single retry per row)}';

    protected $description = 'Recover prerender jobs stuck in claimed/running.';

    public function __construct(private readonly PrerenderService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $claimedMinutes = max(1, (int) $this->option('claimed-minutes'));
        $runningMinutes = max(1, (int) $this->option('running-minutes'));
        $dryRun  = (bool) $this->option('dry-run');
        $requeue = (bool) $this->option('requeue');

        $now = now();
        $claimedCutoff = $now->copy()->subMinutes($claimedMinutes);
        $runningCutoff = $now->copy()->subMinutes($runningMinutes);
        $hasHeartbeat = Schema::hasColumn('prerender_jobs', 'heartbeat_at');

        $stuckClaimed = DB::table('prerender_jobs')
            ->where('status', 'claimed')
            ->where('claimed_at', '<', $claimedCutoff)
            ->get(['id', 'tenant_id', 'routes', 'claimed_at', 'claimed_by', 'error_message']);

        $runningColumns = ['id', 'tenant_id', 'routes', 'started_at', 'claimed_by', 'error_message'];
        if ($hasHeartbeat) $runningColumns[] = 'heartbeat_at';
        $stuckRunning = DB::table('prerender_jobs')
            ->where('status', 'running')
            ->where(function ($lease) use ($runningCutoff, $hasHeartbeat): void {
                if ($hasHeartbeat) {
                    $lease->whereRaw(
                        'COALESCE(heartbeat_at, started_at) < ?',
                        [$runningCutoff]
                    )->orWhere(function ($missing): void {
                        $missing->whereNull('heartbeat_at')->whereNull('started_at');
                    });
                } else {
                    $lease->where('started_at', '<', $runningCutoff)
                        ->orWhereNull('started_at');
                }
            })
            ->get($runningColumns);

        $total = $stuckClaimed->count() + $stuckRunning->count();
        if ($total === 0) {
            $this->info('No stuck prerender jobs.');
            return $this->successfulRun();
        }

        foreach ($stuckClaimed as $row) {
            $this->line(sprintf(
                'CLAIMED #%d tenant=%s claimed_at=%s by=%s',
                $row->id, $row->tenant_id ?? 'all', $row->claimed_at, $row->claimed_by ?? '?'
            ));
        }
        foreach ($stuckRunning as $row) {
            $leaseAt = $hasHeartbeat ? ($row->heartbeat_at ?? $row->started_at) : $row->started_at;
            $this->line(sprintf(
                'RUNNING #%d tenant=%s started_at=%s lease_at=%s by=%s',
                $row->id,
                $row->tenant_id ?? 'all',
                $row->started_at ?? '?',
                $leaseAt ?? '?',
                $row->claimed_by ?? '?'
            ));
        }

        if ($dryRun) {
            $this->info("Dry run: {$total} row(s) would be reaped.");
            return $this->successfulRun();
        }

        // To avoid an infinite reap-requeue-die loop on a poison job, only
        // requeue rows that haven't already been requeued (heuristic: empty
        // error_message). Anything that's been touched before goes to failed.
        $reapedCount = 0;
        foreach ([$stuckClaimed, $stuckRunning] as $set) {
            foreach ($set as $row) {
                $isRunning = property_exists($row, 'started_at');
                $leaseColumn = $isRunning && $hasHeartbeat ? 'heartbeat_at' : ($isRunning ? 'started_at' : 'claimed_at');
                $leaseValue = $row->{$leaseColumn} ?? null;
                if ($requeue) {
                    if ($row->error_message === null || $row->error_message === '') {
                        $query = DB::table('prerender_jobs')
                            ->where('id', $row->id)
                            ->where('status', $isRunning ? 'running' : 'claimed')
                            ->where('claimed_by', $row->claimed_by);
                        $leaseValue === null
                            ? $query->whereNull($leaseColumn)
                            : $query->where($leaseColumn, $leaseValue);
                        $values = [
                            'status'        => 'queued',
                            'claimed_at'    => null,
                            'claimed_by'    => null,
                            'started_at'    => null,
                            'error_message' => 'reaped: requeued once after stuck',
                        ];
                        if ($hasHeartbeat) $values['heartbeat_at'] = null;
                        $updated = $query->update($values);
                        if ($updated > 0) {
                            $reapedCount++;
                            $this->service->releaseJobLease((int) $row->id, (string) $row->claimed_by);
                            $this->service->broadcastJob((int) $row->id);
                        }
                        continue;
                    }
                }
                $query = DB::table('prerender_jobs')
                    ->where('id', $row->id)
                    ->where('status', $isRunning ? 'running' : 'claimed')
                    ->where('claimed_by', $row->claimed_by);
                $leaseValue === null
                    ? $query->whereNull($leaseColumn)
                    : $query->where($leaseColumn, $leaseValue);
                $updated = $query->update([
                    'status'        => 'failed',
                    'finished_at'   => $now,
                    'error_message' => 'reaped: worker did not finalise within timeout',
                ]);
                if ($updated > 0) {
                    $reapedCount++;
                    $this->service->releaseJobLease((int) $row->id, (string) $row->claimed_by);
                    $this->service->broadcastJob((int) $row->id);
                }
            }
        }

        $this->info("Reaped {$reapedCount} row(s).");
        return $this->successfulRun();
    }

    private function successfulRun(): int
    {
        // The stale-job reaper runs from host cron, not the in-container
        // Laravel scheduler. Stamp its own liveness contract on every clean
        // completion so the health panel reflects the actual runtime path.
        Cache::put('prerender:sched:prerender-reap-stale:last_ok_at', time(), 86400);
        return self::SUCCESS;
    }
}
