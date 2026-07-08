<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes justified by the DB/index EXPLAIN pass for high-traffic platform
     * reads. These are intentionally narrow and additive; the pass found that
     * feed, message inbox, notification, listing-public and marketplace-public
     * cursor paths already had matching composite indexes.
     */
    public function up(): void
    {
        if ($this->hasColumns('event_rsvps', ['tenant_id', 'event_id', 'status'])) {
            $this->addIndex('event_rsvps', 'idx_rsvps_tenant_event_status', ['tenant_id', 'event_id', 'status']);
        }

        if ($this->hasColumns('marketplace_images', ['tenant_id', 'marketplace_listing_id', 'is_primary', 'sort_order'])) {
            $this->addIndex('marketplace_images', 'idx_mpi_tenant_listing_primary_sort', ['tenant_id', 'marketplace_listing_id', 'is_primary', 'sort_order']);
        }

        if ($this->hasColumns('search_logs', ['tenant_id', 'created_at', 'query'])) {
            $this->addIndex('search_logs', 'idx_search_logs_tenant_created_query', ['tenant_id', 'created_at', 'query']);
        }

        if ($this->hasColumns('categories', ['tenant_id', 'type', 'slug'])) {
            $this->addIndex('categories', 'idx_categories_tenant_type_slug', ['tenant_id', 'type', 'slug']);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. These are additive performance indexes
        // verified by EXPLAIN, and production rollback should not drop indexes
        // automatically during a blue/green rollback window.
    }

    /**
     * @param list<string> $columns
     */
    private function hasColumns(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $columns
     */
    private function addIndex(string $table, string $index, array $columns): void
    {
        if ($this->indexExists($table, $index)) {
            return;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columnSql})");
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($rows);
    }
};
