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
     * Add narrowly-scoped indexes for the search and member-directory paths
     * added to the hot-path EXPLAIN audit after the initial performance pass.
     */
    public function up(): void
    {
        if ($this->hasColumns('events', ['tenant_id', 'start_time', 'id'])) {
            $this->addIndex('events', 'idx_events_tenant_start_id', ['tenant_id', 'start_time', 'id']);
        }

        if ($this->hasColumns('saved_searches', ['tenant_id', 'user_id', 'created_at', 'id'])) {
            $this->addIndex('saved_searches', 'idx_saved_searches_user_created', ['tenant_id', 'user_id', 'created_at', 'id']);
        }

        if ($this->hasColumns('users', ['tenant_id', 'privacy_search', 'status', 'id'])) {
            $this->addIndex('users', 'idx_users_tenant_privacy_status_id', ['tenant_id', 'privacy_search', 'status', 'id']);
        }
    }

    public function down(): void
    {
        $this->dropIndex('users', 'idx_users_tenant_privacy_status_id');
        $this->dropIndex('saved_searches', 'idx_saved_searches_user_created');
        $this->dropIndex('events', 'idx_events_tenant_start_id');
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

    private function dropIndex(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
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
