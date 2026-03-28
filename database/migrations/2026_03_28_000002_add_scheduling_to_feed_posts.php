<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('feed_posts', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('visibility');
            }
            if (!Schema::hasColumn('feed_posts', 'publish_status')) {
                $table->enum('publish_status', ['published', 'scheduled', 'draft'])
                    ->default('published')
                    ->after('scheduled_at');
            }
        });

        // Add composite index for the cron query that finds scheduled posts ready to publish
        $existing = collect(Schema::getIndexes('feed_posts'))->pluck('name')->all();
        if (!in_array('idx_feed_posts_publish_schedule', $existing, true)) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->index(['publish_status', 'scheduled_at'], 'idx_feed_posts_publish_schedule');
            });
        }
    }

    public function down(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            $table->dropIndex('idx_feed_posts_publish_schedule');
        });

        Schema::table('feed_posts', function (Blueprint $table) {
            if (Schema::hasColumn('feed_posts', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('feed_posts', 'publish_status')) {
                $table->dropColumn('publish_status');
            }
        });
    }
};
