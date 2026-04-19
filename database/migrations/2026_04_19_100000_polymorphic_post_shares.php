<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make `post_shares` fully polymorphic.
 *
 * The `original_type` + `original_post_id` columns already exist (with comment
 * "post, listing, event") but were never used — every share was a post.
 * This migration:
 *   1. Ensures rows with empty/NULL original_type are backfilled to 'post'.
 *   2. Deduplicates any existing duplicate shares (oldest row wins).
 *   3. Adds a unique index on (tenant_id, user_id, original_type, original_post_id)
 *      so INSERT IGNORE actually prevents double-shares.
 *   4. Adds a composite index on (original_type, original_post_id, tenant_id)
 *      to support fast share_count + is_shared lookups for typed feed items.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('post_shares')) {
            return;
        }

        // (1) Backfill any rows missing an original_type
        DB::statement("UPDATE post_shares SET original_type = 'post' WHERE original_type IS NULL OR original_type = ''");

        // (2) Deduplicate existing shares — keep the oldest row per
        //     (tenant_id, user_id, original_type, original_post_id) tuple.
        DB::statement("
            DELETE ps FROM post_shares ps
            INNER JOIN post_shares older
                ON  older.tenant_id        = ps.tenant_id
                AND older.user_id          = ps.user_id
                AND older.original_type    = ps.original_type
                AND older.original_post_id = ps.original_post_id
                AND older.id               < ps.id
        ");

        // (3) Add unique index for real duplicate protection.
        $unique = 'post_shares_uniq_share';
        if (!$this->indexExists('post_shares', $unique)) {
            DB::statement("
                ALTER TABLE post_shares
                ADD UNIQUE KEY `{$unique}` (tenant_id, user_id, original_type, original_post_id)
            ");
        }

        // (4) Composite index on (original_type, original_post_id, tenant_id) for
        //     fast lookups when rendering feed (is_shared + share_count per item).
        $typeIdx = 'post_shares_type_target_tenant_idx';
        if (!$this->indexExists('post_shares', $typeIdx)) {
            DB::statement("
                ALTER TABLE post_shares
                ADD KEY `{$typeIdx}` (original_type, original_post_id, tenant_id)
            ");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('post_shares')) {
            return;
        }
        foreach (['post_shares_uniq_share', 'post_shares_type_target_tenant_idx'] as $name) {
            if ($this->indexExists('post_shares', $name)) {
                DB::statement("ALTER TABLE post_shares DROP INDEX `{$name}`");
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS hit FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$db, $table, $index]
        );
        return $row !== null;
    }
};
