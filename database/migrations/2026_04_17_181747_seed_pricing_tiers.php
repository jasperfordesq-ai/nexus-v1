<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'max_users' column to pay_plans if missing.
        Schema::table('pay_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_plans', 'max_users')) {
                $table->unsignedInteger('max_users')->nullable()->after('tier_level')
                    ->comment('NULL means unlimited');
            }
        });

        // Add 'notes' column to tenant_plan_assignments if missing.
        Schema::table('tenant_plan_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_plan_assignments', 'notes')) {
                $table->text('notes')->nullable()->after('trial_ends_at');
            }
        });

        // Seed the 4 pricing tiers (skip if slug already exists).
        $plans = [
            [
                'slug'          => 'seed',
                'name'          => 'Seed',
                'tier_level'    => 0,
                'max_users'     => 50,
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
            ],
            [
                'slug'          => 'community',
                'name'          => 'Community',
                'tier_level'    => 1,
                'max_users'     => 250,
                'price_monthly' => 20.00,
                'price_yearly'  => 200.00,
            ],
            [
                'slug'          => 'regional',
                'name'          => 'Regional',
                'tier_level'    => 2,
                'max_users'     => 1000,
                'price_monthly' => 50.00,
                'price_yearly'  => 500.00,
            ],
            [
                'slug'          => 'network',
                'name'          => 'Network',
                'tier_level'    => 3,
                'max_users'     => null,
                'price_monthly' => 120.00,
                'price_yearly'  => 1200.00,
            ],
        ];

        foreach ($plans as $plan) {
            if (!DB::table('pay_plans')->where('slug', $plan['slug'])->exists()) {
                DB::table('pay_plans')->insert(array_merge($plan, [
                    'description'     => null,
                    'features'        => '[]',
                    'allowed_layouts' => '[]',
                    'is_active'       => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the 4 seeded plans (only those added by this migration).
        DB::table('pay_plans')->whereIn('slug', ['seed', 'community', 'regional', 'network'])->delete();
    }
};
