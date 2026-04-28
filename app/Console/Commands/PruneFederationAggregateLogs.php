<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CaringCommunity\FederationAggregateService;
use Illuminate\Console\Command;

/**
 * Prune federation aggregate query log entries older than 12 months.
 *
 * The architecture document mandates a 12-month retention window for
 * audit trail purposes. This command runs daily at 02:00.
 */
class PruneFederationAggregateLogs extends Command
{
    protected $signature = 'federation:prune-aggregate-logs';
    protected $description = 'Prune federation aggregate query log entries older than 12 months';

    public function handle(FederationAggregateService $service): int
    {
        $deleted = $service->pruneOldLogs();
        $this->info("Pruned {$deleted} federation aggregate query log entries.");
        return self::SUCCESS;
    }
}
