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
        if (!Schema::hasColumn('feed_posts', 'quoted_post_id')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->unsignedInteger('quoted_post_id')
                    ->nullable()
                    ->after('original_post_id')
                    ->comment('ID of the post being quoted (quote repost)');

                $table->index('quoted_post_id', 'idx_feed_posts_quoted_post_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('feed_posts', 'quoted_post_id')) {
            Schema::table('feed_posts', function (Blueprint $table) {
                $table->dropIndex('idx_feed_posts_quoted_post_id');
                $table->dropColumn('quoted_post_id');
            });
        }
    }
};
