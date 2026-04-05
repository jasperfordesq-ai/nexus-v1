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
     * Creates the marketplace_offers table for buyer–seller negotiation
     * including counter-offers and expiry tracking.
     * Guarded with Schema::hasTable to ensure idempotency.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_offers')) {
            return;
        }

        Schema::create('marketplace_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('marketplace_listing_id');
            $table->unsignedBigInteger('buyer_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'countered', 'expired', 'withdrawn'])->default('pending');
            $table->decimal('counter_amount', 10, 2)->nullable();
            $table->text('counter_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(
                ['tenant_id', 'marketplace_listing_id', 'buyer_id'],
                'mpo_tenant_listing_buyer_idx'
            );
            $table->index(['tenant_id', 'status'], 'mpo_tenant_status_idx');

            // Foreign keys (marketplace tables only — users has different charset)
            $table->foreign('marketplace_listing_id')
                ->references('id')
                ->on('marketplace_listings')
                ->cascadeOnDelete();

            // user indexes (no FK — users table has different charset/collation)
            $table->index('buyer_id', 'mpo_buyer_id_idx');
            $table->index('seller_id', 'mpo_seller_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_offers');
    }
};
