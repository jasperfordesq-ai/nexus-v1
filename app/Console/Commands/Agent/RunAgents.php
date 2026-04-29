<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\Agent;

use App\Core\TenantContext;
use App\Services\Agent\AgentRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — KI-Agenten scheduler entry point.
 *
 * Iterates every enabled `agent_definitions` row and invokes AgentRunner.
 * Optional --tenant and --agent flags scope the run.
 */
class RunAgents extends Command
{
    protected $signature = 'agents:run
                            {--tenant= : Tenant ID to limit run to}
                            {--agent= : Agent definition ID or slug to limit run to}';

    protected $description = 'Run all enabled AG61 agents (or a single agent definition).';

    public function handle(): int
    {
        if (!Schema::hasTable('agent_definitions')) {
            $this->warn('agent_definitions table does not exist — run migrations.');
            return self::SUCCESS;
        }

        $tenantOpt = $this->option('tenant');
        $agentOpt  = $this->option('agent');

        $query = DB::table('agent_definitions')->where('is_enabled', 1);
        if ($tenantOpt !== null && $tenantOpt !== '') {
            $query->where('tenant_id', (int) $tenantOpt);
        }
        if ($agentOpt !== null && $agentOpt !== '') {
            if (ctype_digit((string) $agentOpt)) {
                $query->where('id', (int) $agentOpt);
            } else {
                $query->where('slug', (string) $agentOpt);
            }
        }

        $defs = $query->orderBy('tenant_id')->orderBy('id')->get();

        if ($defs->isEmpty()) {
            $this->line('No enabled agent definitions match.');
            return self::SUCCESS;
        }

        $this->info("Running {$defs->count()} agent definition(s)…");

        foreach ($defs as $def) {
            try {
                TenantContext::setById((int) $def->tenant_id);
            } catch (\Throwable $e) {
                $this->warn("Tenant {$def->tenant_id} could not be set: " . $e->getMessage());
                continue;
            }

            $this->line(sprintf('  [tenant=%d] [%s] running…', $def->tenant_id, $def->slug));
            $result = AgentRunner::run((int) $def->id, 'schedule');

            if (!empty($result['error'])) {
                $this->error("    FAILED: {$result['error']}");
            } elseif (!empty($result['skipped'])) {
                $this->line("    skipped: " . ($result['reason'] ?? 'unknown'));
            } else {
                $this->line(sprintf(
                    '    run_id=%d proposals=%d tokens=%d/%d cost_cents=%d',
                    (int) ($result['run_id'] ?? 0),
                    (int) ($result['proposals_created'] ?? 0),
                    (int) ($result['llm_input_tokens'] ?? 0),
                    (int) ($result['llm_output_tokens'] ?? 0),
                    (int) ($result['cost_cents'] ?? 0),
                ));
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
