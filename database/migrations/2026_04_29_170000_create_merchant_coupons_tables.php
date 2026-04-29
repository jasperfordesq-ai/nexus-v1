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
     * AG63 — Merchant discount / coupon system.
     *
     * Creates merchant_coupons (issued by sellers) and merchant_coupon_redemptions
     * (per-use audit + QR token store). Tenant-scoped, idempotent.
     */
    public function up(): void
    {
        if (!Schema::hasTable('merchant_coupons')) {
            Schema::create('merchant_coupons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('seller_id'); // FK marketplace_seller_profiles.id
                $table->string('code', 64);
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->enum('discount_type', ['percent', 'fixed', 'bogo'])->default('percent');
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->unsignedInteger('min_order_cents')->nullable();
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('max_uses_per_member')->default(1);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->enum('status', ['draft', 'active', 'paused', 'expired'])->default('draft');
                $table->enum('applies_to', ['all_listings', 'listing_ids', 'category_ids'])->default('all_listings');
                $table->json('applies_to_ids')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'seller_id']);
            });
        }

        if (!Schema::hasTable('merchant_coupon_redemptions')) {
            Schema::create('merchant_coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coupon_id');
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedInteger('discount_applied_cents')->default(0);
                $table->timestamp('redeemed_at')->useCurrent();
                $table->enum('redemption_method', ['online', 'qr_scan'])->default('online');
                $table->string('qr_token', 64)->nullable()->unique();
                $table->timestamp('qr_expires_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'coupon_id']);
                $table->index(['tenant_id', 'user_id']);
                $table->index(['tenant_id', 'order_id']);

                $table->foreign('coupon_id')
                    ->references('id')->on('merchant_coupons')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_coupon_redemptions');
        Schema::dropIfExists('merchant_coupons');
    }
};
