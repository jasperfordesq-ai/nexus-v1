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

/** Persist the state required to resolve marketplace disputes and DSA appeals. */
return new class extends Migration
{
    private const OWNER = 'nexus-migration:2026_07_12_000074';
    private const ORDER_REFUND_INDEX = 'mo_wallet_refund_once_unique';
    private const PAYMENT_DISPUTE_INDEX = 'marketplace_payments_stripe_dispute_id_index';
    private const REPORT_APPEAL_INDEX = 'mr_appealed_by_idx';
    private const LISTING_ENFORCEMENT_INDEX = 'marketplace_listings_marketplace_enforcement_report_id_index';
    private const SELLER_ENFORCEMENT_INDEX = 'msp_enforcement_report_idx';

    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')
            && ! Schema::hasColumn('marketplace_orders', 'wallet_refund_transaction_id')) {
            Schema::table('marketplace_orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('wallet_refund_transaction_id')
                    ->nullable()
                    ->after('wallet_transaction_id')
                    ->comment(self::OWNER);
            });
        }
        if (Schema::hasTable('marketplace_orders')
            && Schema::hasColumn('marketplace_orders', 'wallet_refund_transaction_id')) {
            $this->addIndexIfMissing(
                'marketplace_orders',
                ['wallet_refund_transaction_id'],
                self::ORDER_REFUND_INDEX,
                true,
            );
        }

        if (Schema::hasTable('marketplace_disputes')
            && ! Schema::hasColumn('marketplace_disputes', 'prior_order_status')) {
            Schema::table('marketplace_disputes', function (Blueprint $table): void {
                $table->string('prior_order_status', 32)
                    ->nullable()
                    ->after('status')
                    ->comment(self::OWNER);
            });
        }

        if (Schema::hasTable('marketplace_payments')) {
            Schema::table('marketplace_payments', function (Blueprint $table): void {
                if (! Schema::hasColumn('marketplace_payments', 'stripe_dispute_id')) {
                    $table->string('stripe_dispute_id', 255)
                        ->nullable()
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_payments', 'stripe_dispute_status')) {
                    $table->string('stripe_dispute_status', 50)
                        ->nullable()
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_payments', 'dispute_previous_order_status')) {
                    $table->string('dispute_previous_order_status', 32)
                        ->nullable()
                        ->comment(self::OWNER);
                }
            });
            if (Schema::hasColumn('marketplace_payments', 'stripe_dispute_id')) {
                $this->addIndexIfMissing(
                    'marketplace_payments',
                    ['stripe_dispute_id'],
                    self::PAYMENT_DISPUTE_INDEX,
                );
            }
        }

        if (Schema::hasTable('marketplace_reports')) {
            Schema::table('marketplace_reports', function (Blueprint $table): void {
                if (! Schema::hasColumn('marketplace_reports', 'appealed_by')) {
                    $table->unsignedBigInteger('appealed_by')
                        ->nullable()
                        ->after('appeal_text')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_reports', 'enforcement_snapshot')) {
                    $table->json('enforcement_snapshot')
                        ->nullable()
                        ->after('action_taken')
                        ->comment(self::OWNER);
                }
            });
            if (Schema::hasColumn('marketplace_reports', 'appealed_by')) {
                $this->addIndexIfMissing(
                    'marketplace_reports',
                    ['appealed_by'],
                    self::REPORT_APPEAL_INDEX,
                );
            }
        }

        if (Schema::hasTable('marketplace_listings')
            && ! Schema::hasColumn('marketplace_listings', 'marketplace_enforcement_report_id')) {
            Schema::table('marketplace_listings', function (Blueprint $table): void {
                $table->unsignedBigInteger('marketplace_enforcement_report_id')
                    ->nullable()
                    ->comment(self::OWNER);
            });
        }
        if (Schema::hasTable('marketplace_listings')
            && Schema::hasColumn('marketplace_listings', 'marketplace_enforcement_report_id')) {
            $this->addIndexIfMissing(
                'marketplace_listings',
                ['marketplace_enforcement_report_id'],
                self::LISTING_ENFORCEMENT_INDEX,
            );
        }

        if (Schema::hasTable('marketplace_seller_profiles')
            && ! Schema::hasColumn('marketplace_seller_profiles', 'marketplace_suspension_report_id')) {
            Schema::table('marketplace_seller_profiles', function (Blueprint $table): void {
                $table->unsignedBigInteger('marketplace_suspension_report_id')
                    ->nullable()
                    ->comment(self::OWNER);
            });
        }
        if (Schema::hasTable('marketplace_seller_profiles')
            && Schema::hasColumn('marketplace_seller_profiles', 'marketplace_suspension_report_id')) {
            $this->addIndexIfMissing(
                'marketplace_seller_profiles',
                ['marketplace_suspension_report_id'],
                self::SELLER_ENFORCEMENT_INDEX,
            );
        }
    }

    public function down(): void
    {
        $this->dropOwnedIndex('marketplace_seller_profiles', self::SELLER_ENFORCEMENT_INDEX);
        $this->dropOwnedColumn('marketplace_seller_profiles', 'marketplace_suspension_report_id');

        $this->dropOwnedIndex('marketplace_listings', self::LISTING_ENFORCEMENT_INDEX);
        $this->dropOwnedColumn('marketplace_listings', 'marketplace_enforcement_report_id');

        $this->dropOwnedIndex('marketplace_reports', self::REPORT_APPEAL_INDEX);
        $this->dropOwnedColumn('marketplace_reports', 'appealed_by');
        $this->dropOwnedColumn('marketplace_reports', 'enforcement_snapshot');

        $this->dropOwnedIndex('marketplace_payments', self::PAYMENT_DISPUTE_INDEX);
        $this->dropOwnedColumn('marketplace_payments', 'stripe_dispute_id');
        $this->dropOwnedColumn('marketplace_payments', 'stripe_dispute_status');
        $this->dropOwnedColumn('marketplace_payments', 'dispute_previous_order_status');

        $this->dropOwnedColumn('marketplace_disputes', 'prior_order_status');

        $this->dropOwnedIndex('marketplace_orders', self::ORDER_REFUND_INDEX);
        $this->dropOwnedColumn('marketplace_orders', 'wallet_refund_transaction_id');

        // Artifacts from a partial deployment of the pre-marker version remain
        // in place: existence alone is not proof that this migration owns them.
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
