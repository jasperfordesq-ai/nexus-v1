<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\RetentionPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enforce tenant data retention policies (IT-Data-03).
 *
 * Walks every active tenant and disposes of data past each enabled
 * policy's retention window. Deletion is batched inside the service and
 * each (tenant, type) pass is recorded in tenant_retention_runs, so a
 * partially drained backlog simply continues on the next nightly run.
 *
 * Scheduled: daily via the Laravel scheduler (see bootstrap/app.php).
 */
class EnforceRetentionPolicies extends Command
{
    protected $signature = 'retention:enforce {--tenant= : Specific tenant ID (default: all active)}';
    protected $description = 'Dispose of data past each tenant\'s enabled retention policies';

    public function handle(): int
    {
        $specificTenant = $this->option('tenant');

        if ($specificTenant) {
            $tenantIds = [(int) $specificTenant];
        } else {
            $tenantIds = DB::table('tenants')
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $totalAffected = 0;
        $failures = 0;

        foreach ($tenantIds as $tenantId) {
            try {
                $results = RetentionPolicyService::enforceForTenant($tenantId);
            } catch (\Throwable $e) {
                $failures++;
                Log::warning('[Retention] tenant enforcement crashed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Tenant {$tenantId}: enforcement failed — {$e->getMessage()}");
                continue;
            }

            foreach ($results as $type => $result) {
                $totalAffected += $result['affected'];
                if ($result['status'] === 'failed') {
                    $failures++;
                }
                if ($result['affected'] > 0 || $result['status'] !== 'completed') {
                    $this->line("Tenant {$tenantId} / {$type}: {$result['affected']} rows ({$result['status']})");
                }
            }
        }

        $this->info("Retention enforcement done: {$totalAffected} rows disposed across " . count($tenantIds) . ' tenant(s).');

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
