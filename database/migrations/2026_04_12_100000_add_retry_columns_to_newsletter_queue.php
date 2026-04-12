<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add exponential-backoff retry bookkeeping columns to newsletter_queue.
 *
 * - `attempts` TINYINT — number of send attempts so far (0 on queue).
 * - `last_attempted_at` TIMESTAMP NULL — wall-clock time of the most recent attempt.
 *
 * processQueue() re-claims rows whose backoff (pow(attempts,2) * 60 seconds)
 * has elapsed, and permanently fails items once `attempts >= 5`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('newsletter_queue')) {
            return;
        }

        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('newsletter_queue', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('newsletter_queue', 'last_attempted_at')) {
                $table->timestamp('last_attempted_at')->nullable()->after('attempts');
            }
        });

        // Index for the retry-eligibility scan used by processQueue().
        // Guard: SHOW INDEX to avoid "duplicate key name" on re-runs.
        try {
            $exists = collect(\DB::select("SHOW INDEX FROM newsletter_queue WHERE Key_name = 'idx_newsletter_queue_retry'"))->isNotEmpty();
            if (!$exists) {
                \DB::statement('CREATE INDEX idx_newsletter_queue_retry ON newsletter_queue (newsletter_id, status, last_attempted_at)');
            }
        } catch (\Throwable $e) {
            // Index creation is a best-effort optimization; swallow errors.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('newsletter_queue')) {
            return;
        }

        try {
            \DB::statement('DROP INDEX idx_newsletter_queue_retry ON newsletter_queue');
        } catch (\Throwable $e) {
            // Ignore if already dropped
        }

        Schema::table('newsletter_queue', function (Blueprint $table) {
            if (Schema::hasColumn('newsletter_queue', 'last_attempted_at')) {
                $table->dropColumn('last_attempted_at');
            }
            if (Schema::hasColumn('newsletter_queue', 'attempts')) {
                $table->dropColumn('attempts');
            }
        });
    }
};
