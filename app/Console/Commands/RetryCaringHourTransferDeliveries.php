<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CaringHourTransferService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryCaringHourTransferDeliveries extends Command
{
    protected $signature = 'caring:hour-transfers-retry
        {--tenant= : Specific tenant ID}
        {--limit=25 : Maximum due transfers per tenant}';

    protected $description = 'Retry due remote Caring Community hour-transfer deliveries';

    public function handle(CaringHourTransferService $service): int
    {
        $tenantOption = $this->option('tenant');
        $tenantIds = $tenantOption !== null && $tenantOption !== ''
            ? [(int) $tenantOption]
            : DB::table('tenants')->where('is_active', 1)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $limit = max(1, min(250, (int) $this->option('limit')));
        $totals = ['processed' => 0, 'delivered' => 0, 'failed' => 0];
        $tenantFailures = 0;

        foreach ($tenantIds as $tenantId) {
            if ($tenantId <= 0) {
                continue;
            }

            try {
                TenantContext::setById($tenantId);
                if (! TenantContext::hasFeature('caring_community')) {
                    $this->line("Tenant {$tenantId}: caring community disabled");
                    continue;
                }

                $result = $service->retryRemoteDeliveries($tenantId, $limit);
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + (int) ($result[$key] ?? 0);
                }

                $this->line(sprintf(
                    'Tenant %d: processed=%d delivered=%d failed=%d',
                    $tenantId,
                    (int) ($result['processed'] ?? 0),
                    (int) ($result['delivered'] ?? 0),
                    (int) ($result['failed'] ?? 0),
                ));
            } catch (\Throwable $e) {
                $tenantFailures++;
                Log::error('[RetryCaringHourTransferDeliveries] tenant failure', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Tenant {$tenantId}: failed - " . $e->getMessage());
                continue;
            }
        }

        $this->info(sprintf(
            'Remote hour-transfer retries: processed=%d delivered=%d failed=%d tenant_failures=%d',
            $totals['processed'],
            $totals['delivered'],
            $totals['failed'],
            $tenantFailures,
        ));

        return $tenantFailures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
