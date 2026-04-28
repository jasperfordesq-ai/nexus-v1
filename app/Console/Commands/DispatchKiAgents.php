<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\KiAgentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * AG61 — KI-Agenten Autonomous Agent Framework dispatcher.
 *
 * Runs all enabled KI-Agenten for one or all tenants.
 */
class DispatchKiAgents extends Command
{
    protected $signature = 'agents:dispatch
                            {--tenant=all : Tenant ID or "all" for all enabled tenants}
                            {--dry-run : Log what would be proposed without persisting to DB}';

    protected $description = 'Run all KI-Agenten for enabled tenants';

    public function handle(): int
    {
        if (!KiAgentService::isAvailable()) {
            $this->error('KiAgent tables are not available — run migrations first.');
            return self::FAILURE;
        }

        $tenantOption = (string) $this->option('tenant');

        if ($tenantOption === 'all') {
            // Query tenants that have agent enabled
            $tenantIds = DB::table('agent_config')
                ->where('enabled', 1)
                ->pluck('tenant_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($tenantIds)) {
                $this->line('No tenants have KI-Agenten enabled.');
                return self::SUCCESS;
            }
        } else {
            $parsed = (int) $tenantOption;
            if ($parsed <= 0) {
                $this->error("Invalid --tenant value: '{$tenantOption}'");
                return self::FAILURE;
            }
            $tenantIds = [$parsed];
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY-RUN mode — no proposals will be persisted.');
        }

        $this->info(sprintf('Dispatching KI-Agenten for %d tenant(s)…', count($tenantIds)));

        foreach ($tenantIds as $tenantId) {
            TenantContext::setById($tenantId);

            if (!TenantContext::hasFeature('caring_community')) {
                $this->line("Tenant {$tenantId}: caring_community feature disabled — skipping.");
                continue;
            }

            $this->line("Tenant {$tenantId}: running all agents…");

            $summary = KiAgentService::runAllAgents($tenantId);

            if (!empty($summary['skipped'])) {
                $this->line("  → Skipped: {$summary['reason']}");
                continue;
            }

            foreach ((array) ($summary['agents'] ?? []) as $agentType => $result) {
                if (!empty($result['skipped'])) {
                    $this->line("  [{$agentType}] disabled");
                } elseif (!empty($result['error'])) {
                    $this->error("  [{$agentType}] FAILED: {$result['error']}");
                } else {
                    $this->line(sprintf(
                        '  [%s] run_id=%d proposals=%d auto_applied=%d',
                        $agentType,
                        (int) ($result['run_id'] ?? 0),
                        (int) ($result['proposals'] ?? 0),
                        (int) ($result['auto_applied'] ?? 0),
                    ));
                }
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
