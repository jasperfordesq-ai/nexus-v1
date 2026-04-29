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
 * AG58 — Member Premium Tier Paywall Framework
 *
 * Member-facing premium subscription tiers (distinct from INF1a tenant billing).
 * Tenant admins define tiers; members subscribe via Stripe Checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_premium_tiers')) {
            Schema::create('member_premium_tiers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('tenant_id');
                $table->string('slug', 80);
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->unsignedInteger('monthly_price_cents')->default(0);
                $table->unsignedInteger('yearly_price_cents')->default(0);
                $table->string('stripe_price_id_monthly', 120)->nullable();
                $table->string('stripe_price_id_yearly', 120)->nullable();
                // JSON array of feature keys this tier unlocks (e.g. ["verified_badge","priority_matching","advanced_search","ad_free"])
                $table->json('features')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('member_subscriptions')) {
            Schema::create('member_subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('tenant_id');
                $table->unsignedBigInteger('tier_id');
                $table->string('stripe_subscription_id', 120)->nullable();
                $table->string('stripe_customer_id', 120)->nullable();
                // active | past_due | canceled | grace | incomplete | trialing
                $table->string('status', 32)->default('incomplete');
                $table->string('billing_interval', 16)->default('monthly'); // monthly | yearly
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('current_period_end')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamp('grace_period_ends_at')->nullable();
                $table->timestamps();

                $table->unique('stripe_subscription_id');
                $table->index(['user_id', 'status']);
                $table->index(['tenant_id', 'status']);
                $table->index('tier_id');
            });
        }

        if (! Schema::hasTable('member_subscription_events')) {
            Schema::create('member_subscription_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->unsignedInteger('tenant_id');
                $table->string('event_type', 80);
                $table->string('stripe_event_id', 120)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique('stripe_event_id');
                $table->index(['subscription_id', 'event_type']);
                $table->index(['tenant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_subscription_events');
        Schema::dropIfExists('member_subscriptions');
        Schema::dropIfExists('member_premium_tiers');
    }
};
