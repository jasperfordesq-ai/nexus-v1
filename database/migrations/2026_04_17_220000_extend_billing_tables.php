<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Extend tenant_plan_assignments ────────────────────────────────
        Schema::table('tenant_plan_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_plan_assignments', 'custom_price_monthly')) {
                $table->decimal('custom_price_monthly', 10, 2)->nullable()->after('notes')
                      ->comment('Per-tenant price override (monthly). NULL = use plan default.');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'custom_price_yearly')) {
                $table->decimal('custom_price_yearly', 10, 2)->nullable()->after('custom_price_monthly')
                      ->comment('Per-tenant price override (yearly). NULL = use plan default.');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'discount_percentage')) {
                $table->unsignedTinyInteger('discount_percentage')->default(0)->after('custom_price_yearly')
                      ->comment('0–100 % discount applied on top of custom/plan price.');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'discount_reason')) {
                $table->string('discount_reason', 255)->nullable()->after('discount_percentage')
                      ->comment('Reason for discount (charity verified, early adopter, hardship, etc.)');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'grace_period_ends_at')) {
                $table->timestamp('grace_period_ends_at')->nullable()->after('discount_reason')
                      ->comment('If over user limit, grace period ends here. NULL = not in grace.');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'is_paused')) {
                $table->boolean('is_paused')->default(false)->after('grace_period_ends_at')
                      ->comment('Billing paused (e.g. between grant funding cycles).');
            }
            if (!Schema::hasColumn('tenant_plan_assignments', 'nonprofit_verified')) {
                $table->boolean('nonprofit_verified')->default(false)->after('is_paused')
                      ->comment('Verified non-profit — eligible for 20% automatic discount.');
            }
        });

        // ── 2. Create billing_audit_log ──────────────────────────────────────
        if (!Schema::hasTable('billing_audit_log')) {
            Schema::create('billing_audit_log', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id');
                $table->unsignedInteger('acted_by_user_id')->nullable()
                      ->comment('God/delegate who made the change. NULL = system.');
                $table->string('action', 60)
                      ->comment('plan_assigned, price_overridden, discount_applied, grace_period_set, plan_paused, plan_resumed, delegate_granted, delegate_revoked, upgrade_requested');
                $table->json('old_value')->nullable();
                $table->json('new_value')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('tenant_id');
                $table->index('acted_by_user_id');
                $table->index('created_at');
            });
        }

        // ── 3. Create billing_delegates ──────────────────────────────────────
        if (!Schema::hasTable('billing_delegates')) {
            Schema::create('billing_delegates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('granted_by_user_id')
                      ->comment('Must be God (is_god=1).');
                $table->string('scope', 60)
                      ->comment('view_billing | edit_own_price | manage_children');
                $table->timestamp('granted_at')->useCurrent();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'scope']);
                $table->index('user_id');
            });
        }

        // ── 4. Seed revised 5-tier pricing ───────────────────────────────────
        $tiers = [
            ['slug' => 'solidarity', 'name' => 'Solidarity', 'tier_level' => 0, 'max_users' => 25,   'price_monthly' => 0.00,   'price_yearly' => 0.00,    'description' => 'Free forever for tiny, new, or struggling communities.'],
            ['slug' => 'community',  'name' => 'Community',  'tier_level' => 1, 'max_users' => 150,  'price_monthly' => 12.00,  'price_yearly' => 120.00,  'description' => 'Small active time bank (typical local group).'],
            ['slug' => 'regional',   'name' => 'Regional',   'tier_level' => 2, 'max_users' => 750,  'price_monthly' => 35.00,  'price_yearly' => 350.00,  'description' => 'Established time bank or small hub with a few sub-tenants.'],
            ['slug' => 'national',   'name' => 'National',   'tier_level' => 3, 'max_users' => 3000, 'price_monthly' => 70.00,  'price_yearly' => 700.00,  'description' => 'Large established network or national organisation.'],
            ['slug' => 'federation', 'name' => 'Federation', 'tier_level' => 4, 'max_users' => null, 'price_monthly' => 140.00, 'price_yearly' => 1400.00, 'description' => 'Very large hub, national umbrella, or international network.'],
        ];

        foreach ($tiers as $tier) {
            if (!DB::table('pay_plans')->where('slug', $tier['slug'])->exists()) {
                DB::table('pay_plans')->insert(array_merge($tier, [
                    'features'        => '[]',
                    'allowed_layouts' => '[]',
                    'is_active'       => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('tenant_plan_assignments', function (Blueprint $table) {
            $cols = ['custom_price_monthly', 'custom_price_yearly', 'discount_percentage',
                     'discount_reason', 'grace_period_ends_at', 'is_paused', 'nonprofit_verified'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('tenant_plan_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('billing_audit_log');
        Schema::dropIfExists('billing_delegates');

        DB::table('pay_plans')
            ->whereIn('slug', ['solidarity', 'community', 'regional', 'national', 'federation'])
            ->delete();
    }
};
