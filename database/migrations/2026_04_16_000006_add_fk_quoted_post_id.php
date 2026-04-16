<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullify any orphaned quoted_post_id values before adding the FK
        DB::statement(
            "UPDATE feed_posts SET quoted_post_id = NULL
             WHERE quoted_post_id IS NOT NULL
               AND quoted_post_id NOT IN (SELECT id FROM feed_posts fp2)"
        );

        Schema::table('feed_posts', function (Blueprint $table) {
            if (!$this->fkExists('feed_posts', 'fk_feed_posts_quoted')) {
                try {
                    $table->foreign('quoted_post_id', 'fk_feed_posts_quoted')
                        ->references('id')
                        ->on('feed_posts')
                        ->onDelete('set null');
                } catch (\Exception $e) {
                    Log::warning('Could not add FK fk_feed_posts_quoted: ' . $e->getMessage());
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('feed_posts', function (Blueprint $table) {
            if ($this->fkExists('feed_posts', 'fk_feed_posts_quoted')) {
                $table->dropForeign('fk_feed_posts_quoted');
            }
        });
    }

    private function fkExists(string $tableName, string $constraintName): bool
    {
        $result = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$tableName, $constraintName]
        );
        return !empty($result);
    }
};
