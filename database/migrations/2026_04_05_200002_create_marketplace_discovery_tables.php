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
        // ─── Saved Searches ──────────────────────────────────────────────
        if (!Schema::hasTable('marketplace_saved_searches')) {
            Schema::create('marketplace_saved_searches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 100);
                $table->string('search_query', 255)->nullable();
                $table->json('filters')->nullable();
                $table->enum('alert_frequency', ['instant', 'daily', 'weekly'])->default('daily');
                $table->enum('alert_channel', ['email', 'push', 'both'])->default('email');
                $table->timestamp('last_alerted_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // user index (no FK — users table has different charset/collation)
                $table->index('user_id', 'mss_user_id_idx');
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ─── Collections ─────────────────────────────────────────────────
        if (!Schema::hasTable('marketplace_collections')) {
            Schema::create('marketplace_collections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->unsignedInteger('item_count')->default(0);
                $table->timestamps();

                // user index (no FK — users table has different charset/collation)
                $table->index('user_id', 'mc_user_id_idx');
                $table->index(['tenant_id', 'user_id']);
            });
        }

        // ─── Collection Items ────────────────────────────────────────────
        if (!Schema::hasTable('marketplace_collection_items')) {
            Schema::create('marketplace_collection_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('collection_id');
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('collection_id')
                    ->references('id')->on('marketplace_collections')
                    ->onDelete('cascade');
                $table->foreign('marketplace_listing_id')
                    ->references('id')->on('marketplace_listings')
                    ->onDelete('cascade');
                $table->unique(['collection_id', 'marketplace_listing_id'], 'mci_collection_listing_unique');
            });
        }

        // ─── Promotions ──────────────────────────────────────────────────
        if (!Schema::hasTable('marketplace_promotions')) {
            Schema::create('marketplace_promotions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('promotion_type', ['bump', 'featured', 'top_of_category', 'homepage_carousel']);
                $table->string('stripe_payment_intent_id', 255)->nullable();
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('expires_at');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('impressions')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->timestamps();

                $table->foreign('marketplace_listing_id')
                    ->references('id')->on('marketplace_listings')
                    ->onDelete('cascade');
                // user index (no FK — users table has different charset/collation)
                $table->index('user_id', 'mpr_user_id_idx');
                $table->index(['tenant_id', 'marketplace_listing_id', 'is_active'], 'mp_listing_active_idx');
                $table->index(['tenant_id', 'user_id'], 'mp_user_idx');
                $table->index(['is_active', 'expires_at'], 'mp_active_expires_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_promotions');
        Schema::dropIfExists('marketplace_collection_items');
        Schema::dropIfExists('marketplace_collections');
        Schema::dropIfExists('marketplace_saved_searches');
    }
};
