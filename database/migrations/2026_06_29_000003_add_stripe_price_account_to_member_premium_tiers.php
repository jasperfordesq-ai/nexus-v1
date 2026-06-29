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
        if (!Schema::hasTable('member_premium_tiers')) {
            return;
        }

        Schema::table('member_premium_tiers', function (Blueprint $table): void {
            if (!Schema::hasColumn('member_premium_tiers', 'stripe_price_account_id')) {
                $table->string('stripe_price_account_id', 100)
                    ->default('platform_default')
                    ->after('stripe_price_id_yearly')
                    ->index('idx_member_premium_tiers_price_account');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('member_premium_tiers')) {
            return;
        }

        Schema::table('member_premium_tiers', function (Blueprint $table): void {
            if (Schema::hasColumn('member_premium_tiers', 'stripe_price_account_id')) {
                $table->dropIndex('idx_member_premium_tiers_price_account');
                $table->dropColumn('stripe_price_account_id');
            }
        });
    }
};
