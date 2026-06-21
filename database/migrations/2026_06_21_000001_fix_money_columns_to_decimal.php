<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B1 — convert the core money columns from int(11) to DECIMAL(10,2).
 *
 * `transactions.amount` and `users.balance` are still `int(11)` on production
 * (verified live 2026-06-21 via read-only SHOW COLUMNS), even though both
 * Eloquent models cast them to `decimal:2` and exchanges mint fractional
 * `max(0.25, ...)` hours. Writing 0.25 into an int column rounds to 0 with no
 * exception — silently destroying or fabricating real value.
 *
 * A prior raw-SQL migration (`migrations/2026_03_28_data_integrity_audit.sql`,
 * legacy registry id 261) contained the same ALTERs and is logged applied, but
 * the live columns are still int. Root cause: the legacy runner
 * (App\Console\Commands\ImportLegacyMigrations) executes the whole multi-statement
 * file with DB::unprepared() and then UNCONDITIONALLY records it as applied —
 * there is no per-statement verification, so a silently-skipped or later-reverted
 * ALTER leaves the registry lying. This migration fixes that by VERIFYING the
 * post-state and throwing if the conversion did not take, so it can never lie.
 *
 * Type/nullability are preserved exactly except for the int->decimal widening:
 *   - transactions.amount : NOT NULL  -> DECIMAL(10,2) NOT NULL DEFAULT 0
 *   - users.balance       : NULLable  -> DECIMAL(10,2)        DEFAULT 0.00
 * int -> decimal is a non-lossy widening (5 -> 5.00), so existing whole-number
 * balances and amounts are unchanged.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, column: string, definition: string}>
     */
    private array $targets = [
        ['table' => 'transactions', 'column' => 'amount',  'definition' => 'DECIMAL(10,2) NOT NULL DEFAULT 0'],
        ['table' => 'users',        'column' => 'balance', 'definition' => 'DECIMAL(10,2) NULL DEFAULT 0.00'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $t) {
            if (! Schema::hasTable($t['table']) || ! Schema::hasColumn($t['table'], $t['column'])) {
                continue;
            }

            if (! $this->isDecimal($t['table'], $t['column'])) {
                DB::statement("ALTER TABLE `{$t['table']}` MODIFY `{$t['column']}` {$t['definition']}");
            }

            // Never silently no-op like the 2026-03-28 raw-SQL migration did:
            // confirm the column is now DECIMAL or fail loudly.
            $after = $this->columnType($t['table'], $t['column']);
            if (stripos($after, 'decimal') === false) {
                throw new \RuntimeException(
                    "Money-column conversion failed: {$t['table']}.{$t['column']} is still '{$after}', expected DECIMAL."
                );
            }
        }
    }

    /**
     * Intentionally NOT reversible. Reverting these columns to int would round
     * every fractional credit back to a whole number — silent, irreversible
     * money loss. A money-safety fix must never be auto-rolled-back.
     */
    public function down(): void
    {
        // no-op by design — see class docblock.
    }

    private function isDecimal(string $table, string $column): bool
    {
        return stripos($this->columnType($table, $column), 'decimal') !== false;
    }

    private function columnType(string $table, string $column): string
    {
        // information_schema supports bound parameters (SHOW COLUMNS ... LIKE ? does not).
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row->type ?? '';
    }
};
