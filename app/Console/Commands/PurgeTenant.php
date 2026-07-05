<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TenantProvisioning\TenantPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permanently purge a tenant and ALL of its data.
 *
 *   php artisan tenant:purge {id} [--dry-run] [--force]
 *
 * This is the real engine entry point for tenant deletion (a full purge can
 * delete millions of rows and call external APIs — too slow for a synchronous
 * HTTP request). The god-only API endpoint dispatches a job that ends up here.
 *
 *   --dry-run  report per-table row counts + external resources; delete nothing.
 *              Doubles as the "how messy is this specific tenant" preview.
 *   --force    skip the type-the-slug confirmation prompt (used by the queued job).
 *
 * Guards (enforced in TenantPurgeService): never Master (id 1); tenant must be
 * deactivated first; must have no child tenants.
 */
class PurgeTenant extends Command
{
    protected $signature = 'tenant:purge
                            {id : the tenant id to purge}
                            {--dry-run : report what would be deleted without deleting anything}
                            {--force : skip the interactive slug confirmation}';

    protected $description = 'Permanently and irreversibly purge a tenant and all its data (deactivate it first)';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('id');
        $dryRun   = (bool) $this->option('dry-run');

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found.");
            return self::FAILURE;
        }

        $slug = (string) ($tenant->slug ?: ('tenant-' . $tenantId));

        if ($dryRun) {
            $this->info("DRY RUN — nothing will be deleted. Tenant: {$tenant->name} ({$slug})");
        } else {
            $this->warn("⚠  PERMANENT PURGE of tenant '{$tenant->name}' ({$slug}, id {$tenantId}). This CANNOT be undone.");

            if (!$this->option('force')) {
                $typed = (string) $this->ask("Type the tenant slug ('{$slug}') to confirm");
                if ($typed !== $slug) {
                    $this->error('Slug did not match — aborting.');
                    return self::FAILURE;
                }
            }
        }

        $report = TenantPurgeService::purge($tenantId, ['dry_run' => $dryRun]);

        if (!($report['success'] ?? false)) {
            $this->error($report['error'] ?? 'Purge failed.');
            return self::FAILURE;
        }

        // Table breakdown
        if (!empty($report['tables'])) {
            $rows = [];
            foreach ($report['tables'] as $table => $count) {
                $rows[] = [$table, $count];
            }
            $this->table(['table', $dryRun ? 'rows (would delete)' : 'rows deleted'], $rows);
        } else {
            $this->line('No tenant-scoped rows found.');
        }

        $totals = $report['totals'] ?? ['tables_touched' => 0, 'rows' => 0];
        $this->info(sprintf(
            '%s %d rows across %d tables.',
            $dryRun ? 'Would delete' : 'Deleted',
            $totals['rows'] ?? 0,
            $totals['tables_touched'] ?? 0
        ));
        $this->line(sprintf(
            'Members: %s%d',
            $dryRun ? 'would delete ' : 'deleted ',
            $dryRun ? ($report['members_to_delete'] ?? 0) : ($report['members_deleted'] ?? 0)
        ));

        if (!empty($report['external'])) {
            $this->newLine();
            $this->line('External systems:');
            foreach ($report['external'] as $key => $value) {
                $this->line('  - ' . $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value)));
            }
        }

        foreach (($report['warnings'] ?? []) as $w) {
            $this->warn('  ! ' . $w);
        }

        if (!empty($report['manual_followups'])) {
            $this->newLine();
            $this->line('Manual follow-ups:');
            foreach ($report['manual_followups'] as $f) {
                $this->line('  • ' . $f);
            }
        }

        $this->newLine();
        $this->info($dryRun ? 'Dry run complete — re-run without --dry-run to purge.' : 'Purge complete.');
        return self::SUCCESS;
    }
}
