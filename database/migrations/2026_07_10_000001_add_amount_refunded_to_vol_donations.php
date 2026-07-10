<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track cumulative refunded money per donation (2026-07-10 volunteering audit M1).
 *
 * Stripe's charge.refunded webhook fires for PARTIAL refunds too, but the
 * handler previously ignored them entirely: the donation stayed `completed` at
 * its full amount, the giving day's raised_amount stayed overstated, and — the
 * money risk — a `ready` Gift Aid row was exported to HMRC at the full amount,
 * over-claiming on the refunded slice. Stripe reports amount_refunded
 * cumulatively per charge, so the handler needs this column to delta-apply
 * each event idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table) {
            if (!Schema::hasColumn('vol_donations', 'amount_refunded')) {
                // Keep default on the same line for the migration safety scanner.
                $table->decimal('amount_refunded', 10, 2)->default(0)->after('amount');
            }
        });

        // Backfill: fully refunded donations have, by definition, refunded
        // their full amount. Partially refunded history cannot be recovered
        // retroactively (Stripe-side only) and stays 0.
        DB::table('vol_donations')
            ->where('status', 'refunded')
            ->where('amount_refunded', 0)
            ->update(['amount_refunded' => DB::raw('amount')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table) {
            if (Schema::hasColumn('vol_donations', 'amount_refunded')) {
                $table->dropColumn('amount_refunded');
            }
        });
    }
};
