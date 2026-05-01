<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\CaringCommunity\SafeguardingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Escalates safeguarding reports that have breached their SLA window.
 *
 * Iterates every active tenant where the `caring_community` feature is
 * enabled, sets tenant context, and finds reports that are still open
 * (status NOT IN resolved/dismissed), not already escalated, and whose
 * `review_due_at` is in the past. Each match is escalated via
 * `SafeguardingService::escalateReport()`, producing the same audit-log
 * action as a coordinator-driven escalation.
 *
 * Idempotent — once a row is marked escalated=1 it is skipped on subsequent
 * runs. Designed to run every 15 minutes.
 */
class SafeguardingSlaEscalateCommand extends Command
{
    protected $signature = 'safeguarding:sla-escalate {--dry-run : Report counts without writing changes}';
    protected $description = 'Escalates safeguarding reports that have breached their SLA window.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!Schema::hasTable('safeguarding_reports') || !Schema::hasTable('tenants')) {
            $this->info('Skipping — required tables missing.');
            return self::SUCCESS;
        }

        $service = app(SafeguardingService::class);

        $tenantIds = DB::table('tenants')
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $totalEscalated = 0;
        $totalChecked = 0;
        $tenantsProcessed = 0;
        $tenantsSkipped = 0;

        $previousTenantId = TenantContext::getId();

        foreach ($tenantIds as $tenantId) {
            if ($tenantId <= 0) {
                continue;
            }

            try {
                TenantContext::setById($tenantId);
                if (!TenantContext::hasFeature('caring_community')) {
                    $tenantsSkipped++;
                    continue;
                }

                $rows = DB::table('safeguarding_reports')
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['resolved', 'dismissed'])
                    ->where(function ($q) {
                        $q->where('escalated', 0)->orWhereNull('escalated');
                    })
                    ->whereNotNull('review_due_at')
                    ->where('review_due_at', '<', now())
                    ->get(['id']);

                $totalChecked += $rows->count();
                if ($rows->isEmpty()) {
                    $tenantsProcessed++;
                    continue;
                }

                foreach ($rows as $row) {
                    if ($dryRun) {
                        $totalEscalated++;
                        continue;
                    }

                    try {
                        // Actor 0 represents "system" — the audit row records
                        // the escalation as automated. The escalateReport()
                        // method only validates the report exists; it does
                        // not require the actor row to exist.
                        $service->escalateReport(
                            (int) $row->id,
                            0,
                            'Auto-escalated: SLA breached'
                        );
                        $totalEscalated++;
                    } catch (Throwable $e) {
                        Log::warning('[SafeguardingSlaEscalate] escalate failed', [
                            'tenant_id' => $tenantId,
                            'report_id' => (int) $row->id,
                            'error'     => $e->getMessage(),
                        ]);
                    }
                }

                $tenantsProcessed++;
            } catch (Throwable $e) {
                Log::error('[SafeguardingSlaEscalate] tenant failure', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Restore previous tenant context (best-effort).
        if ($previousTenantId !== null) {
            try {
                TenantContext::setById((int) $previousTenantId);
            } catch (Throwable) {
                // ignore
            }
        }

        $this->info(sprintf(
            '%s: tenants=%d/%d skipped=%d checked=%d escalated=%d',
            $dryRun ? 'DRY RUN' : 'Done',
            $tenantsProcessed,
            count($tenantIds),
            $tenantsSkipped,
            $totalChecked,
            $totalEscalated,
        ));

        Log::info('[SafeguardingSlaEscalate] run complete', [
            'dry_run'           => $dryRun,
            'tenants_processed' => $tenantsProcessed,
            'tenants_skipped'   => $tenantsSkipped,
            'reports_checked'   => $totalChecked,
            'reports_escalated' => $totalEscalated,
        ]);

        return self::SUCCESS;
    }
}
