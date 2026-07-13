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

/** Enforce one Stripe checkout mechanism per marketplace order. */
return new class extends Migration
{
    private const OWNER = 'nexus-migration:2026_07_13_000077';

    public function up(): void
    {
        if (! Schema::hasTable('marketplace_orders')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_orders', 'stripe_checkout_mode')) {
            Schema::table('marketplace_orders', function (Blueprint $table): void {
                $table->string('stripe_checkout_mode', 24)
                    ->nullable()
                    ->after('status')
                    ->comment(self::OWNER);
            });
        }

        // Backfills must run on retries where the column DDL committed before
        // the original migration process failed.
        if (Schema::hasColumn('marketplace_orders', 'checkout_session_id')) {
            DB::table('marketplace_orders')
                ->whereNotNull('checkout_session_id')
                ->update(['stripe_checkout_mode' => 'checkout_session']);
        }
        if (Schema::hasColumn('marketplace_orders', 'payment_intent_id')) {
            DB::table('marketplace_orders')
                ->whereNull('stripe_checkout_mode')
                ->whereNotNull('payment_intent_id')
                ->update(['stripe_checkout_mode' => 'payment_intent']);
        }
    }

    public function down(): void
    {
        if ($this->ownsColumn('marketplace_orders', 'stripe_checkout_mode')) {
            Schema::table('marketplace_orders', function (Blueprint $table): void {
                $table->dropColumn('stripe_checkout_mode');
            });
        }

        // A column from a partial deployment of the pre-marker version is
        // intentionally preserved because ownership cannot be proven.
    }

    private function ownsColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table)
            && DB::connection()->getDriverName() === 'mysql'
            && DB::table('information_schema.COLUMNS')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->where('COLUMN_COMMENT', self::OWNER)
                ->exists();
    }
};
