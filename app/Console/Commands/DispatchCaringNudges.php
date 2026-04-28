<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringNudgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchCaringNudges extends Command
{
    protected $signature = 'caring:nudges-dispatch {--tenant= : Specific tenant ID} {--dry-run} {--limit= : Maximum nudges per tenant}';

    protected $description = 'Dispatch enabled Caring Community smart nudges for tenants';

    public function handle(CaringNudgeService $service): int
    {
        $tenantOption = $this->option('tenant');
        $tenantIds = $tenantOption !== null && $tenantOption !== ''
            ? [(int) $tenantOption]
            : DB::table('tenants')->where('is_active', 1)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null && $limitOption !== '' ? (int) $limitOption : null;
        $totalSent = 0;

        foreach ($tenantIds as $tenantId) {
            if ($tenantId <= 0) {
                continue;
            }

            TenantContext::setById($tenantId);
            if (!TenantContext::hasFeature('caring_community')) {
                $this->line("Tenant {$tenantId}: caring community disabled");
                continue;
            }

            $result = $service->dispatchDue($tenantId, $limit, $dryRun);
            $totalSent += (int) ($result['sent'] ?? 0);
            $this->line(sprintf(
                'Tenant %d: enabled=%s dry_run=%s candidates=%d sent=%d',
                $tenantId,
                !empty($result['enabled']) ? 'yes' : 'no',
                !empty($result['dry_run']) ? 'yes' : 'no',
                (int) ($result['candidates'] ?? 0),
                (int) ($result['sent'] ?? 0),
            ));
        }

        $this->info("Smart nudges dispatched: {$totalSent}");

        return self::SUCCESS;
    }
}
