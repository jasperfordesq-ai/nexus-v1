<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gamification feed cards (badge_earned / level_up) were written with a
 * literal source_id = 0. Combined with feed_activity's unique key
 * uq_tenant_source (tenant_id, source_type, source_id) and the
 * ON DUPLICATE KEY UPDATE upsert in FeedActivityService::recordActivity(),
 * every award in a tenant collapsed into ONE shared row per type whose
 * user_id/title/content were overwritten by each subsequent event. The feed
 * served these cards with id = 0, so no member or admin UI action could
 * address them (admin delete included), and the author shown was whoever
 * earned something last — often rendering as a nameless post.
 *
 * GamificationService now records source_id = user_id (one card per user).
 * This migration removes the legacy collapsed rows on every tenant, plus any
 * engagement rows that referenced the un-addressable id 0. The rows carry no
 * recoverable history (they were overwritten in place), so there is no down
 * path to restore them.
 */
return new class extends Migration
{
    private const GAMIFICATION_TYPES = ['badge_earned', 'level_up'];

    public function up(): void
    {
        if (Schema::hasTable('feed_activity')) {
            DB::table('feed_activity')
                ->whereIn('source_type', self::GAMIFICATION_TYPES)
                ->where('source_id', 0)
                ->delete();
        }

        // Defensive sweep: these types are excluded from likes/comments in the
        // API, so these normally delete 0 rows — but any stray engagement rows
        // keyed to id 0 would be unreachable forever once the parent is gone.
        foreach (['feed_hidden' => 'target', 'likes' => 'target', 'comments' => 'target'] as $table => $prefix) {
            if (Schema::hasTable($table)) {
                DB::table($table)
                    ->whereIn("{$prefix}_type", self::GAMIFICATION_TYPES)
                    ->where("{$prefix}_id", 0)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Irreversible: the collapsed rows were overwritten on every award and
        // carry no recoverable history. Intentionally a no-op.
    }
};
