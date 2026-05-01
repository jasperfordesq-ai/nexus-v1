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
 * Add admin-driven reversal metadata to caring_loyalty_redemptions.
 *
 * Allows the loyalty admin console to reverse an `applied` redemption,
 * record who reversed it, when, and why, and refund the credits to the
 * member's wallet atomically.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('caring_loyalty_redemptions')) {
            return;
        }

        Schema::table('caring_loyalty_redemptions', function (Blueprint $table) {
            if (! Schema::hasColumn('caring_loyalty_redemptions', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('redeemed_at');
            }
            if (! Schema::hasColumn('caring_loyalty_redemptions', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            }
            if (! Schema::hasColumn('caring_loyalty_redemptions', 'reversal_reason')) {
                $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('caring_loyalty_redemptions')) {
            return;
        }

        Schema::table('caring_loyalty_redemptions', function (Blueprint $table) {
            if (Schema::hasColumn('caring_loyalty_redemptions', 'reversal_reason')) {
                $table->dropColumn('reversal_reason');
            }
            if (Schema::hasColumn('caring_loyalty_redemptions', 'reversed_by')) {
                $table->dropColumn('reversed_by');
            }
            if (Schema::hasColumn('caring_loyalty_redemptions', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
        });
    }
};
