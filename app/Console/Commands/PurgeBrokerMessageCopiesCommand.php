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
 * Purge old broker message copies per retention policy.
 *
 * Deletes reviewed, non-flagged copies older than 90 days.
 * Flagged copies are retained for 1 year.
 */
class PurgeBrokerMessageCopiesCommand extends Command
{
    protected $signature = 'safeguarding:purge-message-copies
                            {--days=90 : Retention days for reviewed copies}
                            {--flagged-days=365 : Retention days for flagged copies}';
    protected $description = 'Purge old broker message copies per retention policy';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $flaggedDays = (int) $this->option('flagged-days');
        $now = now();

        // Delete reviewed, non-flagged copies older than retention period
        $reviewedDeleted = DB::table('broker_message_copies')
            ->whereNotNull('reviewed_at')
            ->where('flagged', false)
            ->where('sent_at', '<', $now->copy()->subDays($days))
            ->delete();

        // Delete flagged copies older than extended retention period
        $flaggedDeleted = DB::table('broker_message_copies')
            ->whereNotNull('reviewed_at')
            ->where('flagged', true)
            ->where('sent_at', '<', $now->copy()->subDays($flaggedDays))
            ->delete();

        $total = $reviewedDeleted + $flaggedDeleted;

        if ($total > 0) {
            Log::info('Purged broker message copies', [
                'reviewed_deleted' => $reviewedDeleted,
                'flagged_deleted' => $flaggedDeleted,
            ]);
        }

        $this->info("Purged {$reviewedDeleted} reviewed + {$flaggedDeleted} flagged copies.");

        return self::SUCCESS;
    }
}
