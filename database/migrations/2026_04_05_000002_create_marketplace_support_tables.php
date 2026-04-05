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
     * Creates marketplace support tables: categories, images, saved_listings,
     * and category_templates. Also adds the deferred category_id FK on
     * marketplace_listings now that marketplace_categories exists.
     */
    public function up(): void
    {
        // ─── marketplace_categories ───────────────────────────────────
        if (!Schema::hasTable('marketplace_categories')) {
            Schema::create('marketplace_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name', 100);
                $table->string('slug', 100);
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug'], 'mpc_tenant_slug_unique');

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('marketplace_categories')
                    ->nullOnDelete();
            });
        }

        // Add deferred FK from marketplace_listings.category_id → marketplace_categories
        if (Schema::hasTable('marketplace_listings') && Schema::hasTable('marketplace_categories')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                // Only add if the FK doesn't already exist
                $table->foreign('category_id')
                    ->references('id')
                    ->on('marketplace_categories')
                    ->nullOnDelete();
            });
        }

        // ─── marketplace_images ───────────────────────────────────────
        if (!Schema::hasTable('marketplace_images')) {
            Schema::create('marketplace_images', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->string('image_url', 500);
                $table->string('thumbnail_url', 500)->nullable();
                $table->string('alt_text', 255)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->timestamp('created_at')->nullable();

                $table->foreign('marketplace_listing_id')
                    ->references('id')
                    ->on('marketplace_listings')
                    ->cascadeOnDelete();
            });
        }

        // ─── marketplace_saved_listings ───────────────────────────────
        if (!Schema::hasTable('marketplace_saved_listings')) {
            Schema::create('marketplace_saved_listings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('marketplace_listing_id');
                $table->timestamp('created_at')->nullable();

                $table->unique(
                    ['tenant_id', 'user_id', 'marketplace_listing_id'],
                    'mps_tenant_user_listing_unique'
                );

                $table->foreign('marketplace_listing_id')
                    ->references('id')
                    ->on('marketplace_listings')
                    ->cascadeOnDelete();
            });
        }

        // ─── marketplace_category_templates ───────────────────────────
        if (!Schema::hasTable('marketplace_category_templates')) {
            Schema::create('marketplace_category_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name', 100);
                $table->json('fields');
                $table->timestamps();

                $table->foreign('category_id')
                    ->references('id')
                    ->on('marketplace_categories')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('marketplace_category_templates');
        Schema::dropIfExists('marketplace_saved_listings');
        Schema::dropIfExists('marketplace_images');

        // Remove the deferred FK from marketplace_listings before dropping categories
        if (Schema::hasTable('marketplace_listings')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::dropIfExists('marketplace_categories');
    }
};
