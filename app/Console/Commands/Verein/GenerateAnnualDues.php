<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands\Verein;

use App\Core\TenantContext;
use App\Services\Verein\VereinDuesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AG54 — Annual — generates one verein_member_dues row per active member of
 * every active fee config across every tenant. Idempotent.
 *
 * Scheduled to run on Jan 1 each year.
 */
class GenerateAnnualDues extends Command
{
    protected $signature = 'verein:generate-annual-dues {--year=} {--tenant=} {--organization=}';
    protected $description = 'Generate annual Verein membership dues rows for every active member of every active fee config';

    public function handle(VereinDuesService $service): int
    {
        $year = (int) ($this->option('year') ?: date('Y'));
        $tenantFilter = $this->option('tenant');
        $orgFilter = $this->option('organization');

        $query = DB::table('verein_membership_fees')->where('is_active', true);
        if ($tenantFilter !== null) {
            $query->where('tenant_id', (int) $tenantFilter);
        }
        if ($orgFilter !== null) {
            $query->where('organization_id', (int) $orgFilter);
        }

        $configs = $query->get();
        $totals = ['generated' => 0, 'skipped' => 0, 'orgs' => 0];

        foreach ($configs as $config) {
            try {
                TenantContext::setById((int) $config->tenant_id);
                $result = $service->generateAnnualDues((int) $config->organization_id, $year);
                $totals['generated'] += (int) $result['generated'];
                $totals['skipped'] += (int) $result['skipped'];
                $totals['orgs']++;
            } catch (\Throwable $e) {
                Log::warning('VereinDues: annual generation failed for org', [
                    'organization_id' => $config->organization_id,
                    'tenant_id' => $config->tenant_id,
                    'year' => $year,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Annual Verein dues generation complete for {$year}: " .
            "{$totals['orgs']} orgs, {$totals['generated']} generated, {$totals['skipped']} skipped.");
        return self::SUCCESS;
    }
}
