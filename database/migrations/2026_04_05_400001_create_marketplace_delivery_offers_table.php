<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create marketplace_delivery_offers table for community-powered delivery.
 *
 * NEXUS differentiator: community members offer to deliver items for time credits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_delivery_offers')) {
            return;
        }

        Schema::create('marketplace_delivery_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->default(1);
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('deliverer_id');
            $table->decimal('time_credits', 8, 2)->comment('Time credits offered for delivery');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->comment('Estimated delivery time in minutes');
            $table->text('notes')->nullable()->comment('Deliverer notes about the delivery');
            $table->enum('status', ['pending', 'accepted', 'declined', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status'], 'idx_mdo_order_status');
            $table->index(['deliverer_id', 'status'], 'idx_mdo_deliverer_status');
            $table->index('tenant_id', 'idx_mdo_tenant');

            // Note: deliverer_id is already covered by idx_mdo_deliverer_status composite index above
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_delivery_offers');
    }
};
