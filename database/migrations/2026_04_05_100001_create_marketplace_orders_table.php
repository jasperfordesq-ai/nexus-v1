<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the marketplace_orders table — order lifecycle for the Marketplace module.
     * Guarded with Schema::hasTable to ensure idempotency.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            return;
        }

        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('order_number', 50);
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('marketplace_listing_id');
            $table->unsignedBigInteger('marketplace_offer_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('time_credits_used', 8, 2)->nullable();
            $table->enum('status', [
                'pending_payment',
                'paid',
                'shipped',
                'delivered',
                'completed',
                'disputed',
                'refunded',
                'cancelled',
            ])->default('pending_payment');
            $table->string('payment_intent_id', 255)->nullable();
            $table->timestamp('escrow_released_at')->nullable();
            $table->string('shipping_method', 100)->nullable();
            $table->decimal('shipping_cost', 8, 2)->nullable();
            $table->string('tracking_number', 255)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->json('delivery_address')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->timestamp('seller_confirmed_at')->nullable();
            $table->timestamp('auto_complete_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            // Unique order number per tenant
            $table->unique(['tenant_id', 'order_number'], 'mo_tenant_order_number_unique');

            // Composite indexes
            $table->index(['tenant_id', 'buyer_id'], 'mo_tenant_buyer_idx');
            $table->index(['tenant_id', 'seller_id'], 'mo_tenant_seller_idx');
            $table->index(['tenant_id', 'status'], 'mo_tenant_status_idx');

            // Foreign keys
            $table->foreign('buyer_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('marketplace_listing_id')->references('id')->on('marketplace_listings')->cascadeOnDelete();
            $table->foreign('marketplace_offer_id')->references('id')->on('marketplace_offers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
