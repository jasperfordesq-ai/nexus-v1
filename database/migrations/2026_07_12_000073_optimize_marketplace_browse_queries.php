<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PRICE_INDEX = 'mpl_browse_price_idx';
    private const PROMOTION_INDEX = 'mpl_browse_promotion_idx';

    public function up(): void
    {
        if (Schema::hasTable('marketplace_listings')) {
            if (! Schema::hasIndex('marketplace_listings', self::PRICE_INDEX)) {
                Schema::table('marketplace_listings', function (Blueprint $table): void {
                    $table->index(
                        ['tenant_id', 'status', 'moderation_status', 'price', 'id'],
                        self::PRICE_INDEX
                    );
                });
            }

            if (! Schema::hasIndex('marketplace_listings', self::PROMOTION_INDEX)) {
                Schema::table('marketplace_listings', function (Blueprint $table): void {
                    $table->index(
                        ['tenant_id', 'status', 'moderation_status', 'promoted_until', 'id'],
                        self::PROMOTION_INDEX
                    );
                });
            }
        }

        // Migration 2026_04_06 corrected this column, but older schema dumps
        // still create it as INT. Reassert the runtime contract for databases
        // bootstrapped or restored from those snapshots.
        if (Schema::hasTable('marketplace_delivery_offers')) {
            $column = DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', 'marketplace_delivery_offers')
                ->where('COLUMN_NAME', 'tenant_id')
                ->value('COLUMN_TYPE');

            if (strtolower((string) $column) !== 'bigint(20) unsigned') {
                DB::statement(
                    'ALTER TABLE `marketplace_delivery_offers` '
                    . 'MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 1'
                );
            }
        }

        // Heal counters already inflated by retries before save operations
        // became idempotent. The bookmark table's unique key is authoritative.
        if (Schema::hasTable('marketplace_listings')
            && Schema::hasTable('marketplace_saved_listings')) {
            DB::statement(<<<'SQL'
                UPDATE marketplace_listings AS listing
                LEFT JOIN (
                    SELECT tenant_id, marketplace_listing_id, COUNT(*) AS save_count
                    FROM marketplace_saved_listings
                    GROUP BY tenant_id, marketplace_listing_id
                ) AS saved
                  ON saved.tenant_id = listing.tenant_id
                 AND saved.marketplace_listing_id = listing.id
                SET listing.saves_count = COALESCE(saved.save_count, 0)
                SQL);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_listings')) {
            return;
        }

        if (Schema::hasIndex('marketplace_listings', self::PRICE_INDEX)) {
            Schema::table('marketplace_listings', function (Blueprint $table): void {
                $table->dropIndex(self::PRICE_INDEX);
            });
        }

        if (Schema::hasIndex('marketplace_listings', self::PROMOTION_INDEX)) {
            Schema::table('marketplace_listings', function (Blueprint $table): void {
                $table->dropIndex(self::PROMOTION_INDEX);
            });
        }

        // Do not reintroduce the known tenant identifier drift on rollback.
    }
};
