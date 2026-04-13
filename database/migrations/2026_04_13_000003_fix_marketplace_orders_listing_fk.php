<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve order history when a marketplace_listing is deleted.
 *
 * The original migration (2026_04_05_100001_create_marketplace_orders_table)
 * sets the FK on marketplace_listing_id to cascadeOnDelete. That means
 * deleting a listing wipes the associated orders — destroying audit trail,
 * buyer/seller history, Stripe payment reconciliation, and analytics.
 *
 * Switch to nullOnDelete and make the column nullable so orders survive
 * listing deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }

        // Drop the existing FK (Laravel default name is <table>_<column>_foreign)
        $this->dropForeignIfExists('marketplace_orders', 'marketplace_orders_marketplace_listing_id_foreign');

        // Make the column nullable so nullOnDelete works
        Schema::table('marketplace_orders', function ($table) {
            $table->unsignedBigInteger('marketplace_listing_id')->nullable()->change();
        });

        // Re-add FK as nullOnDelete
        Schema::table('marketplace_orders', function ($table) {
            $table->foreign('marketplace_listing_id')
                ->references('id')
                ->on('marketplace_listings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }

        $this->dropForeignIfExists('marketplace_orders', 'marketplace_orders_marketplace_listing_id_foreign');

        Schema::table('marketplace_orders', function ($table) {
            $table->unsignedBigInteger('marketplace_listing_id')->nullable(false)->change();
        });

        Schema::table('marketplace_orders', function ($table) {
            $table->foreign('marketplace_listing_id')
                ->references('id')
                ->on('marketplace_listings')
                ->cascadeOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        } catch (\Throwable $e) {
            // Constraint not present — ignore
        }
    }
};
