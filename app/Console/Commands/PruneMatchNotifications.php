<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Prune match-notification dedup markers older than 30 days.
 *
 * The `match_notification_sent` table prevents the hourly hot-match cron
 * from re-notifying the same user about the same listing. Markers are
 * scoped to a 30-day window (matching the schema comment on the
 * 2026_03_06_fix_match_history_columns migration); older rows are
 * permanently dropped here to keep the table small.
 */
class PruneMatchNotifications extends Command
{
    protected $signature = 'nexus:prune-match-notifications {--days=30}';
    protected $description = 'Prune match_notification_sent rows older than the retention window (default 30 days)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        if (!Schema::hasTable('match_notification_sent')) {
            $this->warn('match_notification_sent table does not exist — nothing to prune.');
            return self::SUCCESS;
        }

        try {
            $deleted = DB::delete(
                'DELETE FROM match_notification_sent WHERE sent_at < NOW() - INTERVAL ? DAY',
                [$days]
            );
            $this->info("Pruned {$deleted} match-notification dedup marker(s) older than {$days} days.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('PruneMatchNotifications failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
