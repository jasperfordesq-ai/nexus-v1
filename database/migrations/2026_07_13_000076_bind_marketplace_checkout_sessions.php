<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Persist Stripe Checkout Sessions so expiry cannot release payable inventory. */
return new class extends Migration
{
    private const OWNER = 'nexus-migration:2026_07_13_000076';
    private const SESSION_INDEX = 'mo_checkout_session_unique';

    public function up(): void
    {
        if (! Schema::hasTable('marketplace_orders')) {
            return;
        }

        $addSessionId = ! Schema::hasColumn('marketplace_orders', 'checkout_session_id');
        $addFingerprint = ! Schema::hasColumn('marketplace_orders', 'checkout_fingerprint');
        Schema::table('marketplace_orders', function (Blueprint $table) use ($addSessionId, $addFingerprint): void {
            if ($addSessionId) {
                $table->string('checkout_session_id', 255)
                    ->nullable()
                    ->after('payment_intent_id')
                    ->comment(self::OWNER);
            }
            if ($addFingerprint) {
                $table->string('checkout_fingerprint', 64)
                    ->nullable()
                    ->after('checkout_key')
                    ->comment(self::OWNER);
            }
        });

        // Index DDL is separate because MariaDB commits each ALTER TABLE. A
        // retry after the column ALTER succeeded must still create the unique.
        $this->addIndexIfMissing(
            'marketplace_orders',
            ['checkout_session_id'],
            self::SESSION_INDEX,
            true,
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_orders')) {
            return;
        }

        $this->dropOwnedIndex('marketplace_orders', self::SESSION_INDEX);
        $this->dropOwnedColumn('marketplace_orders', 'checkout_session_id');
        $this->dropOwnedColumn('marketplace_orders', 'checkout_fingerprint');

        // Columns/indexes from a partial deployment of the pre-marker version
        // are intentionally preserved because this migration cannot prove
        // that it created them.
    }

    /** @param list<string> $columns */
    private function addIndexIfMissing(
        string $table,
        array $columns,
        string $index,
        bool $unique = false,
    ): void {
        if (Schema::hasIndex($table, $index)) {
            $this->assertIndexDefinition($table, $columns, $index, $unique);

            return;
        }

        if ($this->supportsOwnershipMarkers()) {
            $kind = $unique ? 'UNIQUE ' : '';
            $columnList = implode(', ', array_map(
                static fn (string $column): string => "`{$column}`",
                $columns,
            ));
            DB::statement(
                "CREATE {$kind}INDEX `{$index}` ON `{$table}` ({$columnList}) "
                . "COMMENT '" . self::OWNER . "'",
            );

            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $index, $unique): void {
            if ($unique) {
                $blueprint->unique($columns, $index);

                return;
            }

            $blueprint->index($columns, $index);
        });
    }

    /** @param list<string> $columns */
    private function assertIndexDefinition(
        string $table,
        array $columns,
        string $index,
        bool $unique,
    ): void {
        if (! $this->supportsOwnershipMarkers()) {
            return;
        }

        $rows = DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->orderBy('SEQ_IN_INDEX')
            ->get(['COLUMN_NAME', 'NON_UNIQUE']);
        $actualColumns = [];
        $actualUnique = null;
        foreach ($rows as $row) {
            $actualColumns[] = (string) $row->COLUMN_NAME;
            $actualUnique ??= (int) $row->NON_UNIQUE === 0;
        }

        if ($actualColumns !== $columns || $actualUnique !== $unique) {
            throw new LogicException(
                "marketplace_migration_index_definition_mismatch:{$table}.{$index}",
            );
        }
    }

    private function dropOwnedIndex(string $table, string $index): void
    {
        if (! $this->ownsIndex($table, $index)) {
            return;
        }

        DB::statement("DROP INDEX `{$index}` ON `{$table}`");
    }

    private function dropOwnedColumn(string $table, string $column): void
    {
        if (! $this->ownsColumn($table, $column)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropColumn($column);
        });
    }

    private function ownsIndex(string $table, string $index): bool
    {
        return $this->supportsOwnershipMarkers()
            && DB::table('information_schema.STATISTICS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $index)
                ->where('INDEX_COMMENT', self::OWNER)
                ->exists();
    }

    private function ownsColumn(string $table, string $column): bool
    {
        return $this->supportsOwnershipMarkers()
            && DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->where('COLUMN_COMMENT', self::OWNER)
                ->exists();
    }

    private function supportsOwnershipMarkers(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
