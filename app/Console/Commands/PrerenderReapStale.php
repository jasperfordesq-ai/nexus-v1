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

        $stuckClaimed = DB::table('prerender_jobs')
            ->where('status', 'claimed')
            ->where('claimed_at', '<', $claimedCutoff)
            ->get(['id', 'tenant_id', 'routes', 'claimed_at', 'claimed_by']);

        $stuckRunning = DB::table('prerender_jobs')
            ->where('status', 'running')
            ->where('started_at', '<', $runningCutoff)
            ->get(['id', 'tenant_id', 'routes', 'started_at', 'claimed_by']);

        $total = $stuckClaimed->count() + $stuckRunning->count();
        if ($total === 0) {
            $this->info('No stuck prerender jobs.');
            return self::SUCCESS;
        }

        foreach ($stuckClaimed as $row) {
            $this->line(sprintf(
                'CLAIMED #%d tenant=%s claimed_at=%s by=%s',
                $row->id, $row->tenant_id ?? 'all', $row->claimed_at, $row->claimed_by ?? '?'
            ));
        }
        foreach ($stuckRunning as $row) {
            $this->line(sprintf(
                'RUNNING #%d tenant=%s started_at=%s by=%s',
                $row->id, $row->tenant_id ?? 'all', $row->started_at, $row->claimed_by ?? '?'
            ));
        }

        if ($dryRun) {
            $this->info("Dry run: {$total} row(s) would be reaped.");
            return self::SUCCESS;
        }

        // To avoid an infinite reap-requeue-die loop on a poison job, only
        // requeue rows that haven't already been requeued (heuristic: empty
        // error_message). Anything that's been touched before goes to failed.
        $reapedCount = 0;
        foreach ([$stuckClaimed, $stuckRunning] as $set) {
            foreach ($set as $row) {
                if ($requeue) {
                    $existing = DB::table('prerender_jobs')
                        ->where('id', $row->id)
                        ->value('error_message');
                    if ($existing === null || $existing === '') {
                        DB::table('prerender_jobs')->where('id', $row->id)->update([
                            'status'        => 'queued',
                            'claimed_at'    => null,
                            'claimed_by'    => null,
                            'started_at'    => null,
                            'error_message' => 'reaped: requeued once after stuck',
                            'updated_at'    => $now,
                        ]);
                        $reapedCount++;
                        continue;
                    }
                }
                DB::table('prerender_jobs')->where('id', $row->id)->update([
                    'status'        => 'failed',
                    'finished_at'   => $now,
                    'error_message' => 'reaped: worker did not finalise within timeout',
                    'updated_at'    => $now,
                ]);
                $reapedCount++;
                $this->service->broadcastJob((int) $row->id);
            }
        }

        $this->info("Reaped {$reapedCount} row(s).");
        return self::SUCCESS;
    }
}
