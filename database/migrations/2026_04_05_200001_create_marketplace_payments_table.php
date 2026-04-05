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
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_payments')) {
            Schema::create('marketplace_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id');
                $table->string('stripe_payment_intent_id', 255)->nullable()->index();
                $table->string('stripe_charge_id', 255)->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('EUR');
                $table->decimal('platform_fee', 10, 2)->default(0);
                $table->decimal('seller_payout', 10, 2)->default(0);
                $table->string('payment_method', 50)->nullable();
                $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded', 'partially_refunded'])->default('pending');
                $table->decimal('refund_amount', 10, 2)->nullable();
                $table->text('refund_reason')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->enum('payout_status', ['pending', 'scheduled', 'paid', 'failed'])->default('pending');
                $table->string('payout_id', 255)->nullable();
                $table->timestamp('paid_out_at')->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                    ->references('id')
                    ->on('marketplace_orders')
                    ->onDelete('cascade');

                $table->index(['tenant_id', 'order_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('marketplace_escrow')) {
            Schema::create('marketplace_escrow', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('payment_id');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('EUR');
                $table->enum('status', ['held', 'released', 'refunded', 'disputed'])->default('held');
                $table->timestamp('held_at')->nullable();
                $table->timestamp('release_after')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->enum('release_trigger', ['buyer_confirmed', 'auto_timeout', 'admin_override', 'dispute_resolved'])->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                    ->references('id')
                    ->on('marketplace_orders')
                    ->onDelete('cascade');

                $table->foreign('payment_id')
                    ->references('id')
                    ->on('marketplace_payments')
                    ->onDelete('cascade');

                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_escrow');
        Schema::dropIfExists('marketplace_payments');
    }
};
