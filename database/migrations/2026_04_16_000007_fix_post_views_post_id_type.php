<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('post_views', 'post_id')) {
            return;
        }

        Schema::table('post_views', function (Blueprint $table) {
            // Drop unique indexes that include post_id before changing column type
            if ($this->indexExists('post_views', 'post_views_user_unique')) {
                $table->dropUnique('post_views_user_unique');
            }
            if ($this->indexExists('post_views', 'post_views_ip_unique')) {
                $table->dropUnique('post_views_ip_unique');
            }
            if ($this->indexExists('post_views', 'post_views_post_id_index')) {
                $table->dropIndex('post_views_post_id_index');
            }
        });

        // Change post_id from bigint unsigned to unsigned int to match feed_posts.id (int 11)
        DB::statement('ALTER TABLE `post_views` MODIFY COLUMN `post_id` INT UNSIGNED NOT NULL');

        Schema::table('post_views', function (Blueprint $table) {
            // Re-add indexes after column type change
            if (!$this->indexExists('post_views', 'post_views_user_unique')) {
                $table->unique(['tenant_id', 'post_id', 'user_id'], 'post_views_user_unique');
            }
            if (!$this->indexExists('post_views', 'post_views_ip_unique')) {
                $table->unique(['tenant_id', 'post_id', 'ip_hash'], 'post_views_ip_unique');
            }
            if (!$this->indexExists('post_views', 'post_views_post_id_index')) {
                $table->index('post_id', 'post_views_post_id_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('post_views', 'post_id')) {
            return;
        }

        Schema::table('post_views', function (Blueprint $table) {
            if ($this->indexExists('post_views', 'post_views_user_unique')) {
                $table->dropUnique('post_views_user_unique');
            }
            if ($this->indexExists('post_views', 'post_views_ip_unique')) {
                $table->dropUnique('post_views_ip_unique');
            }
            if ($this->indexExists('post_views', 'post_views_post_id_index')) {
                $table->dropIndex('post_views_post_id_index');
            }
        });

        DB::statement('ALTER TABLE `post_views` MODIFY COLUMN `post_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('post_views', function (Blueprint $table) {
            if (!$this->indexExists('post_views', 'post_views_user_unique')) {
                $table->unique(['tenant_id', 'post_id', 'user_id'], 'post_views_user_unique');
            }
            if (!$this->indexExists('post_views', 'post_views_ip_unique')) {
                $table->unique(['tenant_id', 'post_id', 'ip_hash'], 'post_views_ip_unique');
            }
            if (!$this->indexExists('post_views', 'post_views_post_id_index')) {
                $table->index('post_id', 'post_views_post_id_index');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $indexes = DB::select(
            "SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?",
            [$indexName]
        );
        return !empty($indexes);
    }
};
