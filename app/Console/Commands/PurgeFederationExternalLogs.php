<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge federation external partner API logs older than N days.
 *
 * Keeps the federation_external_partner_logs table from growing unbounded
 * by deleting old entries on a daily schedule.
 */
class PurgeFederationExternalLogs extends Command
{
    protected $signature = 'federation:purge-external-logs
                            {--days=90 : Delete log entries older than this many days}';

    protected $description = 'Purge federation external partner API logs older than N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('Days must be at least 1.');
            return self::FAILURE;
        }

        $deleted = DB::delete(
            'DELETE FROM federation_external_partner_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );

        $this->info("Purged {$deleted} log entries older than {$days} days.");

        if ($deleted > 0) {
            Log::info('Purged federation external partner logs', [
                'deleted' => $deleted,
                'retention_days' => $days,
            ]);
        }

        return self::SUCCESS;
    }
}
