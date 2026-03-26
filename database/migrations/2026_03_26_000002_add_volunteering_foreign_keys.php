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
     * Add foreign key constraints to volunteering tables.
     *
     * Each FK is wrapped in a try/catch so the migration is idempotent —
     * if the FK already exists, or the referenced row data would violate the
     * constraint, we log a warning and continue.
     */
    public function up(): void
    {
        $fks = [
            ['vol_applications', 'opportunity_id', 'vol_opportunities', 'id', 'CASCADE'],
            ['vol_applications', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_applications', 'shift_id', 'vol_shifts', 'id', 'SET NULL'],
            ['vol_shifts', 'opportunity_id', 'vol_opportunities', 'id', 'CASCADE'],
            ['vol_logs', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_logs', 'organization_id', 'vol_organizations', 'id', 'SET NULL'],
            ['vol_logs', 'opportunity_id', 'vol_opportunities', 'id', 'SET NULL'],
            ['vol_shift_checkins', 'shift_id', 'vol_shifts', 'id', 'CASCADE'],
            ['vol_shift_checkins', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_reviews', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_certificates', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_expenses', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_expenses', 'organization_id', 'vol_organizations', 'id', 'CASCADE'],
            ['vol_credentials', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_shift_swap_requests', 'from_user_id', 'users', 'id', 'CASCADE'],
            ['vol_shift_swap_requests', 'to_user_id', 'users', 'id', 'CASCADE'],
            ['vol_shift_swap_requests', 'from_shift_id', 'vol_shifts', 'id', 'CASCADE'],
            ['vol_shift_swap_requests', 'to_shift_id', 'vol_shifts', 'id', 'CASCADE'],
            ['vol_shift_waitlist', 'shift_id', 'vol_shifts', 'id', 'CASCADE'],
            ['vol_shift_waitlist', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_shift_group_reservations', 'shift_id', 'vol_shifts', 'id', 'CASCADE'],
            ['vol_shift_group_members', 'reservation_id', 'vol_shift_group_reservations', 'id', 'CASCADE'],
            ['vol_shift_group_members', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_emergency_alert_recipients', 'alert_id', 'vol_emergency_alerts', 'id', 'CASCADE'],
            ['vol_emergency_alert_recipients', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_guardian_consents', 'minor_user_id', 'users', 'id', 'CASCADE'],
            ['vol_donations', 'user_id', 'users', 'id', 'CASCADE'],
            ['vol_donations', 'organization_id', 'vol_organizations', 'id', 'SET NULL'],
        ];

        foreach ($fks as [$table, $column, $refTable, $refColumn, $onDelete]) {
            // Skip if either table doesn't exist
            if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
                continue;
            }

            // Skip if the column doesn't exist on the source table
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            // Build a conventional FK name
            $fkName = "{$table}_{$column}_foreign";

            // Check if this FK already exists by inspecting CREATE TABLE output
            try {
                $createSql = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                $ddl = $createSql->{'Create Table'} ?? '';
                if (str_contains($ddl, $fkName)) {
                    // FK already exists — skip
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            // Attempt to add the FK — catch errors (e.g. orphan data violating constraint)
            try {
                $onDeleteSql = strtoupper($onDelete);
                DB::statement(
                    "ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` "
                    . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`) "
                    . "ON DELETE {$onDeleteSql} ON UPDATE CASCADE"
                );
            } catch (\Throwable $e) {
                // Log warning but don't fail the migration — orphan data or duplicate FK
                logger()->warning("FK {$fkName} not added: {$e->getMessage()}");
            }
        }
    }

    /**
     * Drop all volunteering foreign keys added above.
     */
    public function down(): void
    {
        $fks = [
            ['vol_applications', 'opportunity_id'],
            ['vol_applications', 'user_id'],
            ['vol_applications', 'shift_id'],
            ['vol_shifts', 'opportunity_id'],
            ['vol_logs', 'user_id'],
            ['vol_logs', 'organization_id'],
            ['vol_logs', 'opportunity_id'],
            ['vol_shift_checkins', 'shift_id'],
            ['vol_shift_checkins', 'user_id'],
            ['vol_reviews', 'user_id'],
            ['vol_certificates', 'user_id'],
            ['vol_expenses', 'user_id'],
            ['vol_expenses', 'organization_id'],
            ['vol_credentials', 'user_id'],
            ['vol_shift_swap_requests', 'from_user_id'],
            ['vol_shift_swap_requests', 'to_user_id'],
            ['vol_shift_swap_requests', 'from_shift_id'],
            ['vol_shift_swap_requests', 'to_shift_id'],
            ['vol_shift_waitlist', 'shift_id'],
            ['vol_shift_waitlist', 'user_id'],
            ['vol_shift_group_reservations', 'shift_id'],
            ['vol_shift_group_members', 'reservation_id'],
            ['vol_shift_group_members', 'user_id'],
            ['vol_emergency_alert_recipients', 'alert_id'],
            ['vol_emergency_alert_recipients', 'user_id'],
            ['vol_guardian_consents', 'minor_user_id'],
            ['vol_donations', 'user_id'],
            ['vol_donations', 'organization_id'],
        ];

        foreach ($fks as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $fkName = "{$table}_{$column}_foreign";

            try {
                $createSql = DB::selectOne("SHOW CREATE TABLE `{$table}`");
                $ddl = $createSql->{'Create Table'} ?? '';
                if (! str_contains($ddl, $fkName)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            } catch (\Throwable $e) {
                logger()->warning("Could not drop FK {$fkName}: {$e->getMessage()}");
            }
        }
    }
};
