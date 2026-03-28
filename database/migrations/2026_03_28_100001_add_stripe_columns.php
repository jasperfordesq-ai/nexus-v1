<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Stripe-related columns to tenants, users, pay_plans,
 * tenant_plan_assignments, and vol_donations tables.
 *
 * All additions are idempotent via Schema::hasColumn() checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // tenants — Stripe customer ID
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'stripe_customer_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('stripe_customer_id', 255)->nullable()->after('is_active');
                $table->unique('stripe_customer_id', 'idx_tenants_stripe_customer');
            });
        }

        // users — Stripe customer ID (scoped per tenant)
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('stripe_customer_id', 255)->nullable();
                $table->index(['tenant_id', 'stripe_customer_id'], 'idx_users_stripe_customer');
            });
        }

        // pay_plans — Stripe product/price IDs
        if (Schema::hasTable('pay_plans')) {
            Schema::table('pay_plans', function (Blueprint $table) {
                if (! Schema::hasColumn('pay_plans', 'stripe_product_id')) {
                    $table->string('stripe_product_id', 255)->nullable();
                }
                if (! Schema::hasColumn('pay_plans', 'stripe_price_id_monthly')) {
                    $table->string('stripe_price_id_monthly', 255)->nullable();
                }
                if (! Schema::hasColumn('pay_plans', 'stripe_price_id_yearly')) {
                    $table->string('stripe_price_id_yearly', 255)->nullable();
                }
            });
        }

        // tenant_plan_assignments — Stripe subscription tracking
        if (Schema::hasTable('tenant_plan_assignments')) {
            Schema::table('tenant_plan_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('tenant_plan_assignments', 'stripe_subscription_id')) {
                    $table->string('stripe_subscription_id', 255)->nullable();
                    $table->unique('stripe_subscription_id', 'idx_tpa_stripe_sub');
                }
                if (! Schema::hasColumn('tenant_plan_assignments', 'stripe_current_period_end')) {
                    $table->timestamp('stripe_current_period_end')->nullable();
                }
            });
        }

        // vol_donations — Stripe payment intent tracking
        if (Schema::hasTable('vol_donations') && ! Schema::hasColumn('vol_donations', 'stripe_payment_intent_id')) {
            Schema::table('vol_donations', function (Blueprint $table) {
                $table->string('stripe_payment_intent_id', 255)->nullable();
                $table->unique('stripe_payment_intent_id', 'idx_vd_stripe_pi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'stripe_customer_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropIndex('idx_tenants_stripe_customer');
                $table->dropColumn('stripe_customer_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'stripe_customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_stripe_customer');
                $table->dropColumn('stripe_customer_id');
            });
        }

        if (Schema::hasTable('pay_plans')) {
            Schema::table('pay_plans', function (Blueprint $table) {
                $columns = ['stripe_product_id', 'stripe_price_id_monthly', 'stripe_price_id_yearly'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('pay_plans', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('tenant_plan_assignments')) {
            Schema::table('tenant_plan_assignments', function (Blueprint $table) {
                if (Schema::hasColumn('tenant_plan_assignments', 'stripe_subscription_id')) {
                    $table->dropIndex('idx_tpa_stripe_sub');
                    $table->dropColumn('stripe_subscription_id');
                }
                if (Schema::hasColumn('tenant_plan_assignments', 'stripe_current_period_end')) {
                    $table->dropColumn('stripe_current_period_end');
                }
            });
        }

        if (Schema::hasTable('vol_donations') && Schema::hasColumn('vol_donations', 'stripe_payment_intent_id')) {
            Schema::table('vol_donations', function (Blueprint $table) {
                $table->dropIndex('idx_vd_stripe_pi');
                $table->dropColumn('stripe_payment_intent_id');
            });
        }
    }
};
