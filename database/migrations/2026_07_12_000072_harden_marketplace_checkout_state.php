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

/**
 * Add durable checkout idempotency, expiry and funds-flow metadata.
 *
 * Historical duplicate offer orders are preserved for audit/accounting. Only
 * the oldest remains associated with the offer so the new unique constraint
 * can prevent any further duplicate conversion of an accepted offer.
 * Duplicate coupon and loyalty links are likewise detached and marked
 * reversed; these cleanup mutations are intentionally not undone by down().
 */
return new class extends Migration
{
    private const OWNER = 'nexus-migration:2026_07_12_000072';
    private const ORDER_OFFER_INDEX = 'mo_offer_once_unique';
    private const ORDER_CHECKOUT_INDEX = 'mo_tenant_buyer_checkout_unique';
    private const ORDER_EXPIRY_INDEX = 'mo_pending_expiry_idx';
    private const ORDER_SHIPPING_INDEX = 'mo_shipping_option_idx';
    private const ORDER_LOYALTY_INDEX = 'mo_loyalty_redemption_unique';
    private const COUPON_ORDER_INDEX = 'mcr_order_once_unique';
    private const LOYALTY_ORDER_INDEX = 'clr_order_once_unique';
    private const LOYALTY_EXPIRY_INDEX = 'clr_pending_expiry_idx';
    private const DELIVERY_WALLET_INDEX = 'mdo_wallet_tx_once_unique';

    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement(<<<'SQL'
                    UPDATE marketplace_orders duplicate_order
                    INNER JOIN (
                        SELECT marketplace_offer_id, MIN(id) AS keeper_id
                        FROM marketplace_orders
                        WHERE marketplace_offer_id IS NOT NULL
                        GROUP BY marketplace_offer_id
                        HAVING COUNT(*) > 1
                    ) duplicate_set
                        ON duplicate_set.marketplace_offer_id = duplicate_order.marketplace_offer_id
                    SET duplicate_order.marketplace_offer_id = NULL
                    WHERE duplicate_order.id <> duplicate_set.keeper_id
                SQL);
            }

            Schema::table('marketplace_orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('marketplace_orders', 'checkout_key')) {
                    $table->string('checkout_key', 64)
                        ->nullable()
                        ->after('marketplace_offer_id')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_orders', 'shipping_option_id')) {
                    $table->unsignedBigInteger('shipping_option_id')
                        ->nullable()
                        ->after('shipping_method')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_orders', 'payment_expires_at')) {
                    $table->timestamp('payment_expires_at')
                        ->nullable()
                        ->after('payment_intent_id')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_orders', 'wallet_transaction_id')) {
                    $table->unsignedBigInteger('wallet_transaction_id')
                        ->nullable()
                        ->after('time_credits_used')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('marketplace_orders', 'loyalty_redemption_id')) {
                    $table->unsignedBigInteger('loyalty_redemption_id')
                        ->nullable()
                        ->after('wallet_transaction_id')
                        ->comment(self::OWNER);
                }
            });

            if (Schema::hasColumn('marketplace_orders', 'marketplace_offer_id')) {
                $this->addIndexIfMissing(
                    'marketplace_orders',
                    ['marketplace_offer_id'],
                    self::ORDER_OFFER_INDEX,
                    true,
                );
            }
            if (Schema::hasColumn('marketplace_orders', 'tenant_id')
                && Schema::hasColumn('marketplace_orders', 'buyer_id')
                && Schema::hasColumn('marketplace_orders', 'checkout_key')) {
                $this->addIndexIfMissing(
                    'marketplace_orders',
                    ['tenant_id', 'buyer_id', 'checkout_key'],
                    self::ORDER_CHECKOUT_INDEX,
                    true,
                );
            }
            if (Schema::hasColumn('marketplace_orders', 'tenant_id')
                && Schema::hasColumn('marketplace_orders', 'status')
                && Schema::hasColumn('marketplace_orders', 'payment_expires_at')) {
                $this->addIndexIfMissing(
                    'marketplace_orders',
                    ['tenant_id', 'status', 'payment_expires_at'],
                    self::ORDER_EXPIRY_INDEX,
                );
            }
            if (Schema::hasColumn('marketplace_orders', 'shipping_option_id')) {
                $this->addIndexIfMissing(
                    'marketplace_orders',
                    ['shipping_option_id'],
                    self::ORDER_SHIPPING_INDEX,
                );
            }
            if (Schema::hasColumn('marketplace_orders', 'loyalty_redemption_id')) {
                $this->addIndexIfMissing(
                    'marketplace_orders',
                    ['loyalty_redemption_id'],
                    self::ORDER_LOYALTY_INDEX,
                    true,
                );
            }
        }

        if (Schema::hasTable('marketplace_payments')
            && ! Schema::hasColumn('marketplace_payments', 'funds_flow')) {
            Schema::table('marketplace_payments', function (Blueprint $table): void {
                $table->string('funds_flow', 32)
                    ->default('destination_charge')
                    ->after('stripe_charge_id')
                    ->comment(self::OWNER);
            });
        }

        if (Schema::hasTable('merchant_coupon_redemptions')) {
            Schema::table('merchant_coupon_redemptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('merchant_coupon_redemptions', 'reversed_at')) {
                    $table->timestamp('reversed_at')
                        ->nullable()
                        ->after('redeemed_at')
                        ->comment(self::OWNER);
                }
                if (! Schema::hasColumn('merchant_coupon_redemptions', 'reversal_reason')) {
                    $table->string('reversal_reason', 100)
                        ->nullable()
                        ->after('reversed_at')
                        ->comment(self::OWNER);
                }
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement(<<<'SQL'
                    UPDATE merchant_coupon_redemptions duplicate_redemption
                    INNER JOIN (
                        SELECT order_id, MIN(id) AS keeper_id
                        FROM merchant_coupon_redemptions
                        WHERE order_id IS NOT NULL
                        GROUP BY order_id
                        HAVING COUNT(*) > 1
                    ) duplicate_set ON duplicate_set.order_id = duplicate_redemption.order_id
                    SET duplicate_redemption.order_id = NULL,
                        duplicate_redemption.reversed_at = COALESCE(duplicate_redemption.reversed_at, NOW()),
                        duplicate_redemption.reversal_reason = COALESCE(duplicate_redemption.reversal_reason, 'historical_duplicate')
                    WHERE duplicate_redemption.id <> duplicate_set.keeper_id
                SQL);
                DB::statement(<<<'SQL'
                    UPDATE merchant_coupons coupon
                    SET coupon.usage_count = (
                        SELECT COUNT(*)
                        FROM merchant_coupon_redemptions redemption
                        WHERE redemption.coupon_id = coupon.id
                          AND redemption.reversed_at IS NULL
                    )
                SQL);
            }
            if (Schema::hasColumn('merchant_coupon_redemptions', 'order_id')) {
                $this->addIndexIfMissing(
                    'merchant_coupon_redemptions',
                    ['order_id'],
                    self::COUPON_ORDER_INDEX,
                    true,
                );
            }
        }

        if (Schema::hasTable('caring_loyalty_redemptions')) {
            Schema::table('caring_loyalty_redemptions', function (Blueprint $table): void {
                if (! Schema::hasColumn('caring_loyalty_redemptions', 'expires_at')) {
                    $table->timestamp('expires_at')
                        ->nullable()
                        ->after('redeemed_at')
                        ->comment(self::OWNER);
                }
            });
            if (DB::getDriverName() === 'mysql') {
                DB::statement(<<<'SQL'
                    UPDATE caring_loyalty_redemptions duplicate_redemption
                    INNER JOIN (
                        SELECT marketplace_order_id, MIN(id) AS keeper_id
                        FROM caring_loyalty_redemptions
                        WHERE marketplace_order_id IS NOT NULL
                        GROUP BY marketplace_order_id
                        HAVING COUNT(*) > 1
                    ) duplicate_set
                        ON duplicate_set.marketplace_order_id = duplicate_redemption.marketplace_order_id
                    SET duplicate_redemption.marketplace_order_id = NULL,
                        duplicate_redemption.status = 'reversed'
                    WHERE duplicate_redemption.id <> duplicate_set.keeper_id
                SQL);
            }
            if (Schema::hasColumn('caring_loyalty_redemptions', 'marketplace_order_id')) {
                $this->addIndexIfMissing(
                    'caring_loyalty_redemptions',
                    ['marketplace_order_id'],
                    self::LOYALTY_ORDER_INDEX,
                    true,
                );
            }
            if (Schema::hasColumn('caring_loyalty_redemptions', 'tenant_id')
                && Schema::hasColumn('caring_loyalty_redemptions', 'status')
                && Schema::hasColumn('caring_loyalty_redemptions', 'expires_at')) {
                $this->addIndexIfMissing(
                    'caring_loyalty_redemptions',
                    ['tenant_id', 'status', 'expires_at'],
                    self::LOYALTY_EXPIRY_INDEX,
                );
            }
        }

        if (! Schema::hasTable('marketplace_payment_refunds')) {
            Schema::create('marketplace_payment_refunds', function (Blueprint $table): void {
                $table->comment(self::OWNER);
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('payment_id');
                $table->string('stripe_refund_id', 255)->unique();
                $table->decimal('amount', 10, 2);
                $table->decimal('platform_fee_reversal', 10, 2)->default(0);
                $table->decimal('seller_payout_reversal', 10, 2)->default(0);
                $table->string('reason', 500)->nullable();
                $table->timestamps();
                $table->foreign('payment_id')
                    ->references('id')->on('marketplace_payments')->cascadeOnDelete();
                $table->index(['tenant_id', 'payment_id'], 'mpr_tenant_payment_idx');
            });
        }

        if (Schema::hasTable('marketplace_delivery_offers')
            && ! Schema::hasColumn('marketplace_delivery_offers', 'wallet_transaction_id')) {
            Schema::table('marketplace_delivery_offers', function (Blueprint $table): void {
                $table->unsignedInteger('wallet_transaction_id')
                    ->nullable()
                    ->after('completed_at')
                    ->comment(self::OWNER);
            });
        }
        if (Schema::hasTable('marketplace_delivery_offers')
            && Schema::hasColumn('marketplace_delivery_offers', 'wallet_transaction_id')) {
            $this->addIndexIfMissing(
                'marketplace_delivery_offers',
                ['wallet_transaction_id'],
                self::DELIVERY_WALLET_INDEX,
                true,
            );
        }
    }

    public function down(): void
    {
        $this->dropOwnedIndex('marketplace_delivery_offers', self::DELIVERY_WALLET_INDEX);
        $this->dropOwnedColumn('marketplace_delivery_offers', 'wallet_transaction_id');

        if ($this->ownsTable('marketplace_payment_refunds')) {
            Schema::drop('marketplace_payment_refunds');
        }

        $this->dropOwnedIndex('caring_loyalty_redemptions', self::LOYALTY_ORDER_INDEX);
        $this->dropOwnedIndex('caring_loyalty_redemptions', self::LOYALTY_EXPIRY_INDEX);
        $this->dropOwnedColumn('caring_loyalty_redemptions', 'expires_at');

        $this->dropOwnedIndex('merchant_coupon_redemptions', self::COUPON_ORDER_INDEX);
        $this->dropOwnedColumn('merchant_coupon_redemptions', 'reversed_at');
        $this->dropOwnedColumn('merchant_coupon_redemptions', 'reversal_reason');

        $this->dropOwnedColumn('marketplace_payments', 'funds_flow');

        $this->dropOwnedIndex('marketplace_orders', self::ORDER_OFFER_INDEX);
        $this->dropOwnedIndex('marketplace_orders', self::ORDER_CHECKOUT_INDEX);
        $this->dropOwnedIndex('marketplace_orders', self::ORDER_EXPIRY_INDEX);
        $this->dropOwnedIndex('marketplace_orders', self::ORDER_SHIPPING_INDEX);
        $this->dropOwnedIndex('marketplace_orders', self::ORDER_LOYALTY_INDEX);
        foreach ([
            'checkout_key',
            'shipping_option_id',
            'payment_expires_at',
            'wallet_transaction_id',
            'loyalty_redemption_id',
        ] as $column) {
            $this->dropOwnedColumn('marketplace_orders', $column);
        }

        // The duplicate cleanup in up() is intentionally irreversible: the
        // historical rows remain auditable and must not be reattached. Any
        // artifact left by a partial pre-marker deployment is also preserved,
        // because existence alone cannot prove this migration created it.
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

    private function ownsTable(string $table): bool
    {
        return $this->supportsOwnershipMarkers()
            && DB::table('information_schema.TABLES')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $table)
                ->where('TABLE_COMMENT', self::OWNER)
                ->exists();
    }

    private function supportsOwnershipMarkers(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
};
