<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier 2b governance: annual review of safeguarding preferences.
 *
 * The `safeguarding:review-flags` artisan command uses these columns to
 * track reminder and escalation state. Preferences older than 365 days get
 * a reminder email; if no response within 30 days, admins are notified and
 * the flag stays active (Safeguarding Ireland adult-autonomy principle —
 * we cannot unilaterally strip someone's self-identified protections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_safeguarding_preferences', 'review_reminder_sent_at')) {
                $table->timestamp('review_reminder_sent_at')->nullable()->after('revoked_at');
            }
            if (!Schema::hasColumn('user_safeguarding_preferences', 'review_confirmed_at')) {
                $table->timestamp('review_confirmed_at')->nullable()->after('review_reminder_sent_at');
            }
            if (!Schema::hasColumn('user_safeguarding_preferences', 'review_escalated_at')) {
                $table->timestamp('review_escalated_at')->nullable()->after('review_confirmed_at');
            }
        });

        // Composite index on (tenant_id, review_reminder_sent_at) for fast
        // scheduled-command lookups — the command filters by tenant and by
        // whether the reminder has been sent yet.
        Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
            try {
                $table->index(
                    ['tenant_id', 'review_reminder_sent_at'],
                    'idx_usp_review_reminder'
                );
            } catch (\Throwable $e) {
                // Index may already exist from a partial previous run
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_safeguarding_preferences', function (Blueprint $table): void {
            try {
                $table->dropIndex('idx_usp_review_reminder');
            } catch (\Throwable $e) {
                // ignore
            }
            foreach ([
                'review_escalated_at',
                'review_confirmed_at',
                'review_reminder_sent_at',
            ] as $col) {
                if (Schema::hasColumn('user_safeguarding_preferences', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
