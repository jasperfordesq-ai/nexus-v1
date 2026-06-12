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
 * Reviews had no unique key on (reviewer_id, transaction_id) — the duplicate
 * guard was a check-then-insert in ReviewService with no transaction, so two
 * concurrent submissions (double-click / mobile retry) created two review
 * rows, double-counted the receiver's rating, and awarded XP twice.
 *
 * Adds the unique backstop. NULL transaction_id rows (general member reviews)
 * are unaffected — MariaDB unique indexes permit repeated NULLs.
 * Production was verified to contain zero duplicates (2026-06-12); the
 * defensive dedup below keeps the earliest row if any environment has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reviews')) {
            return;
        }

        if (Schema::hasIndex('reviews', 'uq_reviews_reviewer_transaction')) {
            return;
        }

        // Defensive dedup (keep the earliest review per reviewer+transaction).
        DB::statement("
            DELETE r1 FROM reviews r1
            JOIN reviews r2
              ON r2.reviewer_id = r1.reviewer_id
             AND r2.transaction_id = r1.transaction_id
             AND r2.id < r1.id
            WHERE r1.transaction_id IS NOT NULL
        ");

        Schema::table('reviews', function (Blueprint $table) {
            $table->unique(['reviewer_id', 'transaction_id'], 'uq_reviews_reviewer_transaction');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (Schema::hasIndex('reviews', 'uq_reviews_reviewer_transaction')) {
                $table->dropUnique('uq_reviews_reviewer_transaction');
            }
        });
    }
};
