<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_official and is_pinned columns to feed_posts.
 *
 * is_official — set to 1 when a municipality_announcer posts; renders a badge in the UI.
 * is_pinned   — set to 1 to keep the post at the top of the feed.
 *
 * Idempotent — guarded with Schema::hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('feed_posts', 'is_official')) {
                $table->tinyInteger('is_official')->unsigned()->default(0)->after('is_hidden')
                    ->comment('1 = official municipal announcement');
            }
            if (!Schema::hasColumn('feed_posts', 'is_pinned')) {
                $table->tinyInteger('is_pinned')->unsigned()->default(0)->after('is_official')
                    ->comment('1 = pinned to top of feed');
            }
        });

        // Index for efficient filtering of official/pinned posts per tenant
        Schema::table('feed_posts', function (Blueprint $table) {
            if (!collect(\DB::select("SHOW INDEX FROM feed_posts WHERE Key_name = 'idx_feed_posts_official'"))->count()) {
                $table->index(['tenant_id', 'is_official'], 'idx_feed_posts_official');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            if (Schema::hasColumn('feed_posts', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
            if (Schema::hasColumn('feed_posts', 'is_official')) {
                $table->dropColumn('is_official');
            }
        });
    }
};
