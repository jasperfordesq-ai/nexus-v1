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

    private function getColumnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        );

        return $row?->column_type;
    }

    private function getReferencedColumnDefinition(): ?string
    {
        $row = DB::selectOne(
            "SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            ['feed_posts', 'id']
        );

        if ($row === null) {
            return null;
        }

        return $row->column_type . ($row->is_nullable === 'YES' ? ' NULL' : ' NOT NULL');
    }

    public function up(): void
    {
        if (!Schema::hasTable('feed_posts')) {
            return;
        }

        // Nullify any orphaned quoted_post_id values before adding the FK
        DB::statement(
            "UPDATE feed_posts SET quoted_post_id = NULL
             WHERE quoted_post_id IS NOT NULL
               AND quoted_post_id NOT IN (SELECT id FROM feed_posts fp2)"
        );

        $quotedPostType = $this->getColumnType('feed_posts', 'quoted_post_id');
        $postIdType = $this->getColumnType('feed_posts', 'id');
        $referencedColumnDefinition = $this->getReferencedColumnDefinition();

        if (
            $quotedPostType !== null
            && $postIdType !== null
            && strtolower($quotedPostType) !== strtolower($postIdType)
            && $referencedColumnDefinition !== null
        ) {
            DB::statement("ALTER TABLE `feed_posts` MODIFY `quoted_post_id` {$referencedColumnDefinition} NULL");
        }

        if ($this->fkExists('feed_posts', 'fk_feed_posts_quoted')) {
            return;
        }

        Schema::table('feed_posts', function (Blueprint $table) {
            $table->foreign('quoted_post_id', 'fk_feed_posts_quoted')
                ->references('id')
                ->on('feed_posts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('feed_posts')) {
            return;
        }

        Schema::table('feed_posts', function (Blueprint $table) {
            if ($this->fkExists('feed_posts', 'fk_feed_posts_quoted')) {
                $table->dropForeign('fk_feed_posts_quoted');
            }
        });
    }
};
