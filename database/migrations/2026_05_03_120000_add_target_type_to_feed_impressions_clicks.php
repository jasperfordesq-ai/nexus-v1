<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Polymorphic CTR tracking — listings, events, etc. EdgeRank now needs
 * impression / click signal for non-post feed items, so feed_impressions
 * and feed_clicks gain a target_type column. Existing rows backfill to
 * 'post' (the only type previously written).
 *
 * The unique key (post_id, user_id, tenant_id) is widened to include
 * target_type so the same numeric id across types doesn't collide.
 *
 * Idempotent — guarded by hasColumn checks.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('feed_impressions') && !Schema::hasColumn('feed_impressions', 'target_type')) {
            Schema::table('feed_impressions', function (Blueprint $table) {
                $table->string('target_type', 32)->default('post')->after('post_id');
            });

            // Drop old unique key, add wider one. Wrapped in try/catch in
            // case the index name varies across environments.
            try { DB::statement('ALTER TABLE feed_impressions DROP INDEX uq_impression'); } catch (\Throwable $e) {}
            try {
                DB::statement('ALTER TABLE feed_impressions ADD UNIQUE KEY uq_impression (post_id, target_type, user_id, tenant_id)');
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('feed_clicks') && !Schema::hasColumn('feed_clicks', 'target_type')) {
            Schema::table('feed_clicks', function (Blueprint $table) {
                $table->string('target_type', 32)->default('post')->after('post_id');
            });

            try { DB::statement('ALTER TABLE feed_clicks DROP INDEX uq_click'); } catch (\Throwable $e) {}
            try {
                DB::statement('ALTER TABLE feed_clicks ADD UNIQUE KEY uq_click (post_id, target_type, user_id, tenant_id)');
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('feed_impressions') && Schema::hasColumn('feed_impressions', 'target_type')) {
            try { DB::statement('ALTER TABLE feed_impressions DROP INDEX uq_impression'); } catch (\Throwable $e) {}
            Schema::table('feed_impressions', function (Blueprint $table) {
                $table->dropColumn('target_type');
            });
            try {
                DB::statement('ALTER TABLE feed_impressions ADD UNIQUE KEY uq_impression (post_id, user_id, tenant_id)');
            } catch (\Throwable $e) {}
        }

        if (Schema::hasTable('feed_clicks') && Schema::hasColumn('feed_clicks', 'target_type')) {
            try { DB::statement('ALTER TABLE feed_clicks DROP INDEX uq_click'); } catch (\Throwable $e) {}
            Schema::table('feed_clicks', function (Blueprint $table) {
                $table->dropColumn('target_type');
            });
            try {
                DB::statement('ALTER TABLE feed_clicks ADD UNIQUE KEY uq_click (post_id, user_id, tenant_id)');
            } catch (\Throwable $e) {}
        }
    }
};
