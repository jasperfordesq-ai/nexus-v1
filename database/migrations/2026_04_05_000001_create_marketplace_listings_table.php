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
     * Creates the marketplace_listings table — the core table for the Marketplace module.
     * Guarded with Schema::hasTable to ensure idempotency.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_listings')) {
            return;
        }

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 200);
            $table->text('description');
            $table->string('tagline', 300)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_currency', 3)->default('EUR');
            $table->enum('price_type', ['fixed', 'negotiable', 'free', 'auction', 'contact'])->default('fixed');
            $table->decimal('time_credit_price', 8, 2)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('condition', ['new', 'like_new', 'good', 'fair', 'poor'])->nullable();
            $table->integer('quantity')->default(1);
            $table->string('location', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('shipping_available')->default(false);
            $table->boolean('local_pickup')->default(true);
            $table->enum('delivery_method', ['pickup', 'shipping', 'both', 'community_delivery'])->default('pickup');
            $table->enum('seller_type', ['private', 'business'])->default('private');
            $table->enum('status', ['draft', 'active', 'sold', 'reserved', 'expired', 'removed'])->default('draft');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
            $table->text('moderation_notes')->nullable();
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('saves_count')->default(0);
            $table->integer('contacts_count')->default(0);
            $table->timestamp('promoted_until')->nullable();
            $table->string('promotion_type', 30)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->integer('renewal_count')->default(0);
            $table->json('template_data')->nullable();
            $table->timestamps();

            // Composite indexes
            $table->index(['tenant_id', 'status'], 'mpl_tenant_status_idx');
            $table->index(['tenant_id', 'category_id'], 'mpl_tenant_category_idx');
            $table->index(['tenant_id', 'user_id'], 'mpl_tenant_user_idx');
            $table->index(['tenant_id', 'latitude', 'longitude'], 'mpl_tenant_geo_idx');

            // Fulltext index for search
            $table->fullText(['title', 'description'], 'mpl_title_description_ft');

            // user_id index (no FK — users table has different charset/collation)
            $table->index('user_id', 'mpl_user_id_idx');
            // moderated_by index
            $table->index('moderated_by', 'mpl_moderated_by_idx');
            // category_id FK is deferred to Migration 2 (marketplace_categories must exist first)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
