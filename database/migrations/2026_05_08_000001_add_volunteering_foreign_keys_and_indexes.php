<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the volunteering-schema FK constraints and composite indexes that were
 * flagged as missing by the 2026-05-08 audit.
 *
 * Notes:
 *  - The earlier 2026_03_26_000002 migration attempted some of these FKs but
 *    silently swallowed failures (try/catch + logger->warning), so several
 *    were never actually created. This migration adds the genuinely-missing
 *    ones explicitly.
 *  - vol_expenses uses `int unsigned` for FK columns while its parent tables
 *    (users.id, vol_organizations.id, vol_opportunities.id, vol_shifts.id) are
 *    `int signed`. We MODIFY the columns to signed int before adding the FKs,
 *    otherwise InnoDB rejects the constraint with errno 150.
 *  - Orphan rows are nulled (for nullable columns) or deleted (for required
 *    columns) before the FK is added so the ALTER cannot fail on bad data.
 */
return new class extends Migration {
    public function up(): void
    {
        // ------------------------------------------------------------------
        // 1. vol_opportunities.organization_id  ->  vol_organizations.id
        //    Column is NOT NULL, so we DELETE orphan rows and use CASCADE.
        // ------------------------------------------------------------------
        if (
            Schema::hasTable('vol_opportunities')
            && Schema::hasTable('vol_organizations')
            && Schema::hasColumn('vol_opportunities', 'organization_id')
            && ! $this->fkExists('vol_opportunities', 'vol_opportunities_organization_id_foreign')
        ) {
            DB::statement(
                'DELETE FROM vol_opportunities '
                . 'WHERE organization_id NOT IN (SELECT id FROM vol_organizations)'
            );
            DB::statement(
                'ALTER TABLE `vol_opportunities` '
                . 'ADD CONSTRAINT `vol_opportunities_organization_id_foreign` '
                . 'FOREIGN KEY (`organization_id`) REFERENCES `vol_organizations` (`id`) '
                . 'ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }

        // ------------------------------------------------------------------
        // 2. vol_expenses FKs (column type fix-up + FK creation)
        //    user_id           NOT NULL  -> users.id           CASCADE
        //    organization_id   NULL      -> vol_organizations  SET NULL
        //    opportunity_id    NULL      -> vol_opportunities  SET NULL
        //    shift_id          NULL      -> vol_shifts         SET NULL
        // ------------------------------------------------------------------
        if (Schema::hasTable('vol_expenses')) {
            // Align column types with parent tables (signed int).
            // MariaDB/MySQL: MODIFY is idempotent — safe to run repeatedly.
            $this->modifyToSignedInt('vol_expenses', 'user_id', false);
            $this->modifyToSignedInt('vol_expenses', 'organization_id', true);
            $this->modifyToSignedInt('vol_expenses', 'opportunity_id', true);
            $this->modifyToSignedInt('vol_expenses', 'shift_id', true);

            // user_id (CASCADE — required column, delete orphans)
            if (
                Schema::hasTable('users')
                && Schema::hasColumn('vol_expenses', 'user_id')
                && ! $this->fkExists('vol_expenses', 'vol_expenses_user_id_foreign')
            ) {
                DB::statement(
                    'DELETE FROM vol_expenses '
                    . 'WHERE user_id NOT IN (SELECT id FROM users)'
                );
                DB::statement(
                    'ALTER TABLE `vol_expenses` ADD CONSTRAINT `vol_expenses_user_id_foreign` '
                    . 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) '
                    . 'ON DELETE CASCADE ON UPDATE CASCADE'
                );
            }

            // organization_id (SET NULL — nullable, null orphans)
            if (
                Schema::hasTable('vol_organizations')
                && Schema::hasColumn('vol_expenses', 'organization_id')
                && ! $this->fkExists('vol_expenses', 'vol_expenses_organization_id_foreign')
            ) {
                DB::statement(
                    'UPDATE vol_expenses SET organization_id = NULL '
                    . 'WHERE organization_id IS NOT NULL '
                    . 'AND organization_id NOT IN (SELECT id FROM vol_organizations)'
                );
                DB::statement(
                    'ALTER TABLE `vol_expenses` ADD CONSTRAINT `vol_expenses_organization_id_foreign` '
                    . 'FOREIGN KEY (`organization_id`) REFERENCES `vol_organizations` (`id`) '
                    . 'ON DELETE SET NULL ON UPDATE CASCADE'
                );
            }

            // opportunity_id (SET NULL)
            if (
                Schema::hasTable('vol_opportunities')
                && Schema::hasColumn('vol_expenses', 'opportunity_id')
                && ! $this->fkExists('vol_expenses', 'vol_expenses_opportunity_id_foreign')
            ) {
                DB::statement(
                    'UPDATE vol_expenses SET opportunity_id = NULL '
                    . 'WHERE opportunity_id IS NOT NULL '
                    . 'AND opportunity_id NOT IN (SELECT id FROM vol_opportunities)'
                );
                DB::statement(
                    'ALTER TABLE `vol_expenses` ADD CONSTRAINT `vol_expenses_opportunity_id_foreign` '
                    . 'FOREIGN KEY (`opportunity_id`) REFERENCES `vol_opportunities` (`id`) '
                    . 'ON DELETE SET NULL ON UPDATE CASCADE'
                );
            }

            // shift_id (SET NULL)
            if (
                Schema::hasTable('vol_shifts')
                && Schema::hasColumn('vol_expenses', 'shift_id')
                && ! $this->fkExists('vol_expenses', 'vol_expenses_shift_id_foreign')
            ) {
                DB::statement(
                    'UPDATE vol_expenses SET shift_id = NULL '
                    . 'WHERE shift_id IS NOT NULL '
                    . 'AND shift_id NOT IN (SELECT id FROM vol_shifts)'
                );
                DB::statement(
                    'ALTER TABLE `vol_expenses` ADD CONSTRAINT `vol_expenses_shift_id_foreign` '
                    . 'FOREIGN KEY (`shift_id`) REFERENCES `vol_shifts` (`id`) '
                    . 'ON DELETE SET NULL ON UPDATE CASCADE'
                );
            }
        }

        // ------------------------------------------------------------------
        // 3. vol_org_transactions FKs
        //    vol_organization_id  NOT NULL  -> vol_organizations  CASCADE
        //    user_id              NULL      -> users              SET NULL
        //    vol_log_id           NULL      -> vol_logs           SET NULL
        // ------------------------------------------------------------------
        if (Schema::hasTable('vol_org_transactions')) {
            // vol_organization_id (CASCADE)
            if (
                Schema::hasTable('vol_organizations')
                && Schema::hasColumn('vol_org_transactions', 'vol_organization_id')
                && ! $this->fkExists('vol_org_transactions', 'vol_org_transactions_vol_organization_id_foreign')
            ) {
                DB::statement(
                    'DELETE FROM vol_org_transactions '
                    . 'WHERE vol_organization_id NOT IN (SELECT id FROM vol_organizations)'
                );
                DB::statement(
                    'ALTER TABLE `vol_org_transactions` ADD CONSTRAINT `vol_org_transactions_vol_organization_id_foreign` '
                    . 'FOREIGN KEY (`vol_organization_id`) REFERENCES `vol_organizations` (`id`) '
                    . 'ON DELETE CASCADE ON UPDATE CASCADE'
                );
            }

            // user_id (SET NULL)
            if (
                Schema::hasTable('users')
                && Schema::hasColumn('vol_org_transactions', 'user_id')
                && ! $this->fkExists('vol_org_transactions', 'vol_org_transactions_user_id_foreign')
            ) {
                DB::statement(
                    'UPDATE vol_org_transactions SET user_id = NULL '
                    . 'WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)'
                );
                DB::statement(
                    'ALTER TABLE `vol_org_transactions` ADD CONSTRAINT `vol_org_transactions_user_id_foreign` '
                    . 'FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) '
                    . 'ON DELETE SET NULL ON UPDATE CASCADE'
                );
            }

            // vol_log_id (SET NULL)
            if (
                Schema::hasTable('vol_logs')
                && Schema::hasColumn('vol_org_transactions', 'vol_log_id')
                && ! $this->fkExists('vol_org_transactions', 'vol_org_transactions_vol_log_id_foreign')
            ) {
                DB::statement(
                    'UPDATE vol_org_transactions SET vol_log_id = NULL '
                    . 'WHERE vol_log_id IS NOT NULL AND vol_log_id NOT IN (SELECT id FROM vol_logs)'
                );
                DB::statement(
                    'ALTER TABLE `vol_org_transactions` ADD CONSTRAINT `vol_org_transactions_vol_log_id_foreign` '
                    . 'FOREIGN KEY (`vol_log_id`) REFERENCES `vol_logs` (`id`) '
                    . 'ON DELETE SET NULL ON UPDATE CASCADE'
                );
            }

            // Composite index (tenant_id, vol_organization_id) for tenant-scoped lookups
            if (! $this->indexExists('vol_org_transactions', 'idx_vot_tenant_org')) {
                DB::statement(
                    'CREATE INDEX `idx_vot_tenant_org` ON `vol_org_transactions` '
                    . '(`tenant_id`, `vol_organization_id`)'
                );
            }
        }

        // ------------------------------------------------------------------
        // 4. vol_logs.organization_id -> vol_organizations.id (SET NULL)
        //    Already attempted by 2026_03_26 but failed silently. Add explicitly.
        // ------------------------------------------------------------------
        if (
            Schema::hasTable('vol_logs')
            && Schema::hasTable('vol_organizations')
            && Schema::hasColumn('vol_logs', 'organization_id')
            && ! $this->fkExists('vol_logs', 'vol_logs_organization_id_foreign')
        ) {
            DB::statement(
                'UPDATE vol_logs SET organization_id = NULL '
                . 'WHERE organization_id IS NOT NULL '
                . 'AND organization_id NOT IN (SELECT id FROM vol_organizations)'
            );
            DB::statement(
                'ALTER TABLE `vol_logs` ADD CONSTRAINT `vol_logs_organization_id_foreign` '
                . 'FOREIGN KEY (`organization_id`) REFERENCES `vol_organizations` (`id`) '
                . 'ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        // Drop FKs in reverse order of creation.
        $fks = [
            ['vol_logs', 'vol_logs_organization_id_foreign'],
            ['vol_org_transactions', 'vol_org_transactions_vol_log_id_foreign'],
            ['vol_org_transactions', 'vol_org_transactions_user_id_foreign'],
            ['vol_org_transactions', 'vol_org_transactions_vol_organization_id_foreign'],
            ['vol_expenses', 'vol_expenses_shift_id_foreign'],
            ['vol_expenses', 'vol_expenses_opportunity_id_foreign'],
            ['vol_expenses', 'vol_expenses_organization_id_foreign'],
            ['vol_expenses', 'vol_expenses_user_id_foreign'],
            ['vol_opportunities', 'vol_opportunities_organization_id_foreign'],
        ];

        foreach ($fks as [$table, $fkName]) {
            if (Schema::hasTable($table) && $this->fkExists($table, $fkName)) {
                try {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
                } catch (\Throwable $e) {
                    logger()->warning("Could not drop FK {$fkName}: {$e->getMessage()}");
                }
            }
        }

        if (Schema::hasTable('vol_org_transactions') && $this->indexExists('vol_org_transactions', 'idx_vot_tenant_org')) {
            try {
                DB::statement('DROP INDEX `idx_vot_tenant_org` ON `vol_org_transactions`');
            } catch (\Throwable $e) {
                logger()->warning("Could not drop index idx_vot_tenant_org: {$e->getMessage()}");
            }
        }
    }

    /**
     * Check if a named foreign key already exists on a table.
     */
    private function fkExists(string $table, string $fkName): bool
    {
        try {
            $row = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $ddl = $row->{'Create Table'} ?? '';
            return str_contains($ddl, "`{$fkName}`");
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a named index already exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return ! empty($rows);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Modify a column to signed `int` (matching parent PK type) so the FK
     * can be added. No-op if the column is already signed int.
     */
    private function modifyToSignedInt(string $table, string $column, bool $nullable): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            $row = DB::selectOne(
                'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
        } catch (\Throwable) {
            return;
        }

        if (! $row) {
            return;
        }

        $type = strtolower($row->COLUMN_TYPE ?? '');
        $isNullable = (($row->IS_NULLABLE ?? 'NO') === 'YES');

        // If already signed int and nullability matches, nothing to do.
        if (! str_contains($type, 'unsigned') && $isNullable === $nullable) {
            return;
        }

        $nullClause = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` INT {$nullClause}");
    }
};
