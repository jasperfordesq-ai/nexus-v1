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
 * Per-seller loyalty programme opt-in settings.
 *
 * A seller (typically a local marketplace merchant) opts in to accepting
 * time credits as a discount on their listings. They set the exchange
 * rate (CHF per hour) and a maximum discount cap (% of order total).
 *
 * The merchant absorbs the discount cost — this is their loyalty
 * programme, not a tenant treasury subsidy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_seller_loyalty_settings')) {
            return;
        }

        Schema::create('marketplace_seller_loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('seller_user_id');

            $table->boolean('accepts_time_credits')->default(false);
            $table->decimal('loyalty_chf_per_hour', 6, 2)->default(25.00);
            $table->unsignedTinyInteger('loyalty_max_discount_pct')->default(50);

            $table->timestamps();

            $table->unique(['tenant_id', 'seller_user_id'], 'mpsls_tenant_seller_unique');
            $table->index(['tenant_id', 'accepts_time_credits'], 'mpsls_tenant_accepts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_seller_loyalty_settings');
    }
};
