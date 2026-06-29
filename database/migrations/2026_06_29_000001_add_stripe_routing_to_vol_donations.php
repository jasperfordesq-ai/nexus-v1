<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table): void {
            if (!Schema::hasColumn('vol_donations', 'payment_route')) {
                $table->string('payment_route', 50)
                    ->default('platform_default')
                    ->after('payment_reference')
                    ->index('idx_vd_payment_route');
            }

            if (!Schema::hasColumn('vol_donations', 'stripe_account_id')) {
                $table->string('stripe_account_id', 100)
                    ->nullable()
                    ->after('stripe_payment_intent_id')
                    ->index('idx_vd_stripe_account');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vol_donations')) {
            return;
        }

        Schema::table('vol_donations', function (Blueprint $table): void {
            if (Schema::hasColumn('vol_donations', 'stripe_account_id')) {
                $table->dropIndex('idx_vd_stripe_account');
                $table->dropColumn('stripe_account_id');
            }

            if (Schema::hasColumn('vol_donations', 'payment_route')) {
                $table->dropIndex('idx_vd_payment_route');
                $table->dropColumn('payment_route');
            }
        });
    }
};
