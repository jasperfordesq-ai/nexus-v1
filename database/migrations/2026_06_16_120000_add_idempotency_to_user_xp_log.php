<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency key for XP awards.
 *
 * GamificationService::awardXP() had no natural dedup key in user_xp_log (the
 * UpdateWalletBalance listener literally documents this). A queue re-delivery
 * after its 1-hour cache claim expires — or any caller firing the same event
 * twice — could award send_credits / receive_credits XP more than once for the
 * same transaction.
 *
 * This adds a nullable source_reference column plus a unique index on
 * (tenant_id, user_id, action, source_reference). MySQL/MariaDB permit multiple
 * NULLs in a unique index, so legacy reference-less awards are completely
 * unaffected; only callers that pass a reference (e.g. the transaction id)
 * become idempotent at the database level.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_xp_log')) {
            return;
        }

        if (!Schema::hasColumn('user_xp_log', 'source_reference')) {
            Schema::table('user_xp_log', function (Blueprint $table) {
                $table->string('source_reference', 100)->nullable()->after('description');
            });
        }

        $hasIndex = collect(DB::select(
            "SHOW INDEX FROM user_xp_log WHERE Key_name = 'uniq_user_xp_log_ref'"
        ))->isNotEmpty();

        if (!$hasIndex) {
            Schema::table('user_xp_log', function (Blueprint $table) {
                $table->unique(
                    ['tenant_id', 'user_id', 'action', 'source_reference'],
                    'uniq_user_xp_log_ref'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_xp_log')) {
            return;
        }

        $hasIndex = collect(DB::select(
            "SHOW INDEX FROM user_xp_log WHERE Key_name = 'uniq_user_xp_log_ref'"
        ))->isNotEmpty();

        if ($hasIndex) {
            Schema::table('user_xp_log', function (Blueprint $table) {
                $table->dropUnique('uniq_user_xp_log_ref');
            });
        }

        if (Schema::hasColumn('user_xp_log', 'source_reference')) {
            Schema::table('user_xp_log', function (Blueprint $table) {
                $table->dropColumn('source_reference');
            });
        }
    }
};
