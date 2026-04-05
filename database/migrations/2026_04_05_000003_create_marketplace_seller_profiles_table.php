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
     * Creates the marketplace_seller_profiles table for seller identity,
     * business details, payment integration, and trust metrics.
     * Guarded with Schema::hasTable to ensure idempotency.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_seller_profiles')) {
            return;
        }

        Schema::create('marketplace_seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->string('display_name', 100)->nullable();
            $table->text('bio')->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->enum('seller_type', ['private', 'business'])->default('private');
            $table->string('business_name', 200)->nullable();
            $table->string('business_registration', 100)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->json('business_address')->nullable();
            $table->boolean('business_verified')->default(false);
            $table->string('stripe_account_id', 100)->nullable();
            $table->boolean('stripe_onboarding_complete')->default(false);
            $table->integer('response_time_avg')->nullable()->comment('Average response time in minutes');
            $table->decimal('response_rate', 5, 2)->nullable()->comment('Response rate percentage');
            $table->integer('total_sales')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('avg_rating', 3, 2)->nullable();
            $table->integer('total_ratings')->default(0);
            $table->decimal('community_trust_score', 5, 2)->nullable();
            $table->boolean('is_community_endorsed')->default(false);
            $table->timestamp('joined_marketplace_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'mpsp_tenant_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_seller_profiles');
    }
};
