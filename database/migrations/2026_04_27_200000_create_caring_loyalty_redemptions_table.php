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
 * Caring loyalty redemption ledger.
 *
 * Records each instance where a member applies time credits as a discount
 * on a marketplace purchase from a participating local merchant. The bridge
 * between the time-credit wallet (hours) and the cash marketplace (CHF).
 *
 * Each row captures the exchange rate AT TIME of redemption so a merchant
 * later changing their rate cannot retroactively distort historical reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('caring_loyalty_redemptions')) {
            return;
        }

        Schema::create('caring_loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('member_user_id');
            $table->unsignedBigInteger('merchant_user_id');
            $table->unsignedBigInteger('marketplace_listing_id')->nullable();
            $table->unsignedBigInteger('marketplace_order_id')->nullable();

            $table->decimal('credits_used', 8, 2);
            $table->decimal('exchange_rate_chf', 6, 2);
            $table->decimal('discount_chf', 8, 2);
            $table->decimal('order_total_chf', 10, 2);

            $table->enum('status', ['pending', 'applied', 'reversed'])->default('applied');
            $table->timestamp('redeemed_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'member_user_id'], 'clr_tenant_member_idx');
            $table->index(['tenant_id', 'merchant_user_id'], 'clr_tenant_merchant_idx');
            $table->index(['tenant_id', 'redeemed_at'], 'clr_tenant_redeemed_idx');
            $table->index(['tenant_id', 'marketplace_listing_id'], 'clr_tenant_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caring_loyalty_redemptions');
    }
};
