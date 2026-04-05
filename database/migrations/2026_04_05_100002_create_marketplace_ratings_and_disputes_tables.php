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
     * Creates the marketplace_seller_ratings and marketplace_disputes tables.
     * Guarded with Schema::hasTable to ensure idempotency.
     */
    public function up(): void
    {
        // -----------------------------------------------------------------
        //  marketplace_seller_ratings — mutual buyer/seller ratings per order
        // -----------------------------------------------------------------
        if (!Schema::hasTable('marketplace_seller_ratings')) {
            Schema::create('marketplace_seller_ratings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('rater_id');
                $table->unsignedBigInteger('ratee_id');
                $table->enum('rater_role', ['buyer', 'seller']);
                $table->tinyInteger('rating'); // 1–5
                $table->text('comment')->nullable();
                $table->boolean('is_anonymous')->default(false);
                $table->timestamps();

                // One rating per role per order per tenant
                $table->unique(
                    ['tenant_id', 'order_id', 'rater_role'],
                    'msr_tenant_order_role_unique'
                );

                // Foreign keys
                $table->foreign('order_id')->references('id')->on('marketplace_orders')->cascadeOnDelete();
                $table->foreign('rater_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('ratee_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // -----------------------------------------------------------------
        //  marketplace_disputes — order dispute management
        // -----------------------------------------------------------------
        if (!Schema::hasTable('marketplace_disputes')) {
            Schema::create('marketplace_disputes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('opened_by');
                $table->enum('reason', [
                    'not_received',
                    'not_as_described',
                    'damaged',
                    'wrong_item',
                    'other',
                ]);
                $table->text('description');
                $table->json('evidence_urls')->nullable();
                $table->enum('status', [
                    'open',
                    'under_review',
                    'resolved_buyer',
                    'resolved_seller',
                    'escalated',
                    'closed',
                ])->default('open');
                $table->text('resolution_notes')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->decimal('refund_amount', 10, 2)->nullable();
                $table->timestamps();

                // Composite indexes
                $table->index(['tenant_id', 'status'], 'md_tenant_status_idx');

                // Foreign keys
                $table->foreign('order_id')->references('id')->on('marketplace_orders')->cascadeOnDelete();
                $table->foreign('opened_by')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_disputes');
        Schema::dropIfExists('marketplace_seller_ratings');
    }
};
