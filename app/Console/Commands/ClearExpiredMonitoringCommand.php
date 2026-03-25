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
 * Clear expired monitoring restrictions from user_messaging_restrictions.
 *
 * Runs daily to unset under_monitoring flag when monitoring_expires_at has passed.
 */
class ClearExpiredMonitoringCommand extends Command
{
    protected $signature = 'safeguarding:clear-expired-monitoring';
    protected $description = 'Clear expired safeguarding monitoring restrictions';

    public function handle(): int
    {
        $now = now();

        $affected = DB::table('user_messaging_restrictions')
            ->where('under_monitoring', true)
            ->whereNotNull('monitoring_expires_at')
            ->where('monitoring_expires_at', '<', $now)
            ->update([
                'under_monitoring' => false,
            ]);

        if ($affected > 0) {
            Log::info('Cleared expired monitoring restrictions', ['count' => $affected]);
            $this->info("Cleared {$affected} expired monitoring restriction(s).");
        } else {
            $this->info('No expired monitoring restrictions found.');
        }

        return self::SUCCESS;
    }
}
