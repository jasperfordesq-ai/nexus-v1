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
     * Composite indexes for the volunteering hot paths identified in the
     * module performance audit. vol_logs and vol_applications grow unbounded,
     * yet the admin hours list, org-owner pending-hours query and approvals
     * list all filter (tenant_id, status) and ORDER BY created_at with only a
     * lone tenant_id index available — a full-partition filesort per request.
     * vol_shifts(end_time) supports the lapsed-volunteer reminder discovery,
     * and vol_donations(tenant_id, created_at) supports the admin activity
     * feed's date-ranged donation branch. Additive and guarded for mixed
     * legacy/prod schemas.
     */
    public function up(): void
    {
        if ($this->hasColumns('vol_logs', ['tenant_id', 'status', 'created_at'])) {
            $this->addIndex('vol_logs', 'idx_vol_logs_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        if ($this->hasColumns('vol_applications', ['tenant_id', 'status', 'created_at'])) {
            $this->addIndex('vol_applications', 'idx_vol_apps_tenant_status_created', ['tenant_id', 'status', 'created_at']);
        }

        if ($this->hasColumns('vol_shifts', ['end_time'])) {
            $this->addIndex('vol_shifts', 'idx_vol_shifts_end_time', ['end_time']);
        }

        if ($this->hasColumns('vol_donations', ['tenant_id', 'created_at'])) {
            $this->addIndex('vol_donations', 'idx_vol_donations_tenant_created', ['tenant_id', 'created_at']);
        }
    }

    public function down(): void
    {
        $this->dropIndex('vol_donations', 'idx_vol_donations_tenant_created');
        $this->dropIndex('vol_shifts', 'idx_vol_shifts_end_time');
        $this->dropIndex('vol_applications', 'idx_vol_apps_tenant_status_created');
        $this->dropIndex('vol_logs', 'idx_vol_logs_tenant_status_created');
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
