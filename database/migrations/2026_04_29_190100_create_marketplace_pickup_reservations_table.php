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
 * AG45 — Pickup reservations linking buyers to slots, with QR code for scan-on-pickup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_pickup_reservations')) {
            return;
        }

        Schema::create('marketplace_pickup_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('slot_id');
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('buyer_user_id');
            $table->string('qr_code', 64)->unique();
            $table->enum('status', ['reserved', 'picked_up', 'no_show', 'cancelled'])->default('reserved');
            $table->dateTime('reserved_at');
            $table->dateTime('picked_up_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'buyer_user_id'], 'mppr_tenant_buyer_idx');
            $table->index(['tenant_id', 'order_id'], 'mppr_tenant_order_idx');
            $table->index(['slot_id', 'status'], 'mppr_slot_status_idx');

            $table->foreign('slot_id')
                ->references('id')->on('marketplace_pickup_slots')
                ->cascadeOnDelete();
            $table->foreign('order_id')
                ->references('id')->on('marketplace_orders')
                ->cascadeOnDelete();
            $table->foreign('listing_id')
                ->references('id')->on('marketplace_listings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_pickup_reservations');
    }
};
