<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\Agent;

use Database\Seeders\DefaultAgentDefinitionsSeeder;
use Illuminate\Console\Command;

/**
 * AG61 — Seed the four default agent definitions.
 *
 * Usage:
 *   php artisan tenant:seed-agents              # all active tenants
 *   php artisan tenant:seed-agents --tenant=2   # single tenant
 */
class SeedAgentsCommand extends Command
{
    protected $signature = 'tenant:seed-agents
                            {--tenant= : Tenant ID to seed; omit for all active tenants}';

    protected $description = 'Seed the four default AG61 agent definitions (idempotent).';

    public function handle(): int
    {
        $tenantOpt = $this->option('tenant');
        $tenantId  = ($tenantOpt !== null && $tenantOpt !== '') ? (int) $tenantOpt : null;

        $seeder = new DefaultAgentDefinitionsSeeder();
        $seeder->run($tenantId);

        $this->info($tenantId
            ? "Seeded default agent definitions for tenant {$tenantId}."
            : 'Seeded default agent definitions for all active tenants.');

        return self::SUCCESS;
    }
}
