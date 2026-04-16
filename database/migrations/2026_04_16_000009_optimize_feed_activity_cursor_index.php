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
        Schema::table('feed_activity', function (Blueprint $table) {
            // Add a compact cursor-pagination index on (tenant_id, created_at, id).
            // The existing idx_main_feed covers (tenant_id, is_visible, created_at, id)
            // which requires is_visible in the WHERE clause. This lighter index covers
            // admin/unfiltered cursor queries and serves as a covering index for
            // COUNT queries scoped only by tenant + time range.
            if (!$this->indexExists('feed_activity', 'idx_feed_activity_cursor')) {
                $table->index(['tenant_id', 'created_at', 'id'], 'idx_feed_activity_cursor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('feed_activity', function (Blueprint $table) {
            if ($this->indexExists('feed_activity', 'idx_feed_activity_cursor')) {
                $table->dropIndex('idx_feed_activity_cursor');
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
