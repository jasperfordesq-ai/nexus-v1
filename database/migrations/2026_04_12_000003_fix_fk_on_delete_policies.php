<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TD10 — FK ON DELETE policy fixes (Category B, safe subset).
 *
 * Scope: Only FKs whose child column is ALREADY nullable. Columns that are
 * NOT NULL are documented in docs/database/cascade-policy.md and require
 * a product/legal decision + a separate schema-change migration.
 *
 * Changes applied (CASCADE → SET NULL):
 *   - transactions.giver_id       (nullable) — financial audit trail
 *   - search_logs.user_id         (nullable) — analytics history
 *
 * Each change is wrapped in try/catch so a failure does not abort the migration.
 * The down() method fully reverses each change (SET NULL → CASCADE).
 */
return new class extends Migration {
    /**
     * List of FK changes: [child_table, fk_name, column, parent_table, parent_col, new_on_delete, prev_on_delete].
     *
     * @var array<int, array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    private array $changes = [
        ['transactions', 'fk_transactions_giver',  'giver_id', 'users', 'id', 'SET NULL', 'CASCADE'],
        ['search_logs',  'fk_search_logs_user',    'user_id',  'users', 'id', 'SET NULL', 'CASCADE'],
    ];

    public function up(): void
    {
        foreach ($this->changes as [$table, $fk, $col, $parent, $pcol, $newBehavior, $prevBehavior]) {
            $this->applyFkChange($table, $fk, $col, $parent, $pcol, $newBehavior);
        }
    }

    public function down(): void
    {
        // Reverse: put them back to their previous behavior.
        foreach ($this->changes as [$table, $fk, $col, $parent, $pcol, $newBehavior, $prevBehavior]) {
            $this->applyFkChange($table, $fk, $col, $parent, $pcol, $prevBehavior);
        }
    }

    /**
     * Idempotently drop + recreate the FK on (table.column → parent.parent_col)
     * with the requested ON DELETE behavior.
     *
     * FK NAME DISCOVERY: Laravel + legacy SQL migrations produce inconsistent FK
     * names (e.g. `transactions_giver_id_foreign` vs `fk_transactions_giver`).
     * We therefore look up the ACTUAL constraint name by column from
     * information_schema.KEY_COLUMN_USAGE before dropping. The `$fk` argument
     * is used only as the canonical name for the RECREATED constraint.
     */
    private function applyFkChange(
        string $table,
        string $fk,
        string $col,
        string $parent,
        string $pcol,
        string $newBehavior
    ): void {
        try {
            if (!Schema::hasTable($table)) {
                Log::warning("[TD10] Skipping FK change — table {$table} missing.");
                return;
            }

            // SET NULL requires the column to be nullable. Verify.
            if ($newBehavior === 'SET NULL' && !$this->isColumnNullable($table, $col)) {
                Log::warning("[TD10] Skipping {$table}.{$col} — column is NOT NULL; SET NULL would violate schema.");
                return;
            }

            // Discover the ACTUAL FK name by column (handles Laravel vs legacy naming).
            $existingFk = $this->findFkByColumn($table, $col, $parent, $pcol);

            if ($existingFk !== null) {
                $currentBehavior = $this->getCurrentOnDelete($table, $existingFk);
                if ($currentBehavior === $newBehavior) {
                    Log::info("[TD10] {$table}.{$existingFk} already ON DELETE {$newBehavior}; skipping.");
                    return;
                }
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$existingFk}`");
            } else {
                $currentBehavior = 'NONE';
            }

            $sql = sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                $table,
                $fk,
                $col,
                $parent,
                $pcol,
                $newBehavior
            );
            DB::statement($sql);

            Log::info("[TD10] {$table}.{$col}: ON DELETE {$currentBehavior} → {$newBehavior} (FK now `{$fk}`)");
        } catch (\Throwable $e) {
            Log::error("[TD10] Failed to alter {$table}.{$col}: " . $e->getMessage());
            // Continue — other changes should still attempt to apply.
        }
    }

    private function isColumnNullable(string $table, string $column): bool
    {
        $dbName = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$dbName, $table, $column]
        );
        return $row !== null && strtoupper($row->IS_NULLABLE) === 'YES';
    }

    /**
     * Look up the real FK constraint name on (table.column) pointing at (parent.parent_col).
     * Returns null if no such FK exists.
     */
    private function findFkByColumn(string $table, string $column, string $parent, string $parentCol): ?string
    {
        $dbName = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?
             LIMIT 1',
            [$dbName, $table, $column, $parent, $parentCol]
        );
        return $row !== null ? $row->CONSTRAINT_NAME : null;
    }

    private function getCurrentOnDelete(string $table, string $fk): ?string
    {
        $dbName = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$dbName, $table, $fk]
        );
        return $row !== null ? strtoupper($row->DELETE_RULE) : null;
    }
};
