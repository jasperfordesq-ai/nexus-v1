<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AG46 — Merchant inventory tracking columns on marketplace_listings.
 *  - inventory_count        NULL = unlimited
 *  - low_stock_threshold    notify seller when count <= threshold
 *  - is_oversold_protected  reject orders that would push count negative
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listings')) {
            return;
        }

        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'inventory_count')) {
                $table->integer('inventory_count')->nullable()->after('quantity');
            }
            if (!Schema::hasColumn('marketplace_listings', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->nullable()->default(5)->after('inventory_count');
            }
            if (!Schema::hasColumn('marketplace_listings', 'is_oversold_protected')) {
                $table->boolean('is_oversold_protected')->default(true)->after('low_stock_threshold');
            }
        });

        // Add index in a separate call so column exists first.
        Schema::table('marketplace_listings', function (Blueprint $table) {
            try {
                $table->index('inventory_count', 'mpl_inventory_count_idx');
            } catch (\Throwable $e) {
                // index may already exist
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listings')) {
            return;
        }

        Schema::table('marketplace_listings', function (Blueprint $table) {
            try {
                $table->dropIndex('mpl_inventory_count_idx');
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('marketplace_listings', 'is_oversold_protected')) {
                $table->dropColumn('is_oversold_protected');
            }
            if (Schema::hasColumn('marketplace_listings', 'low_stock_threshold')) {
                $table->dropColumn('low_stock_threshold');
            }
            if (Schema::hasColumn('marketplace_listings', 'inventory_count')) {
                $table->dropColumn('inventory_count');
            }
        });
    }
};
