<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the three paid plan tiers alongside the existing free Starter plan.
 *
 * Pricing is based on hosting cost per tenant:
 *   Community — shared hosting, community timebanks, up to 500 members
 *   Partner   — partner organisations with broader programmes, up to 2 000 members
 *   Network   — platform operators (Made Open scale), unlimited members
 *
 * Feature bullets describe member limits only — not platform capabilities.
 */
return new class extends Migration
{
    private array $plans = [
        [
            'slug'          => 'community',
            'name'          => 'Community',
            'description'   => 'For established community timebanks. Shared hosting keeps costs low.',
            'tier_level'    => 1,
            'max_users'     => 500,
            'price_monthly' => 29.00,
            'price_yearly'  => 290.00,
            'features'      => ['Up to 500 community members'],
        ],
        [
            'slug'          => 'partner',
            'name'          => 'Partner',
            'description'   => 'For organisations running timebanks as part of broader social programmes.',
            'tier_level'    => 2,
            'max_users'     => 2000,
            'price_monthly' => 89.00,
            'price_yearly'  => 890.00,
            'features'      => ['Up to 2,000 community members'],
        ],
        [
            'slug'          => 'network',
            'name'          => 'Network',
            'description'   => 'For platform operators deploying NEXUS at scale.',
            'tier_level'    => 3,
            'max_users'     => null,
            'price_monthly' => 249.00,
            'price_yearly'  => 2490.00,
            'features'      => ['Unlimited community members'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->plans as $plan) {
            if (!DB::table('pay_plans')->where('slug', $plan['slug'])->exists()) {
                DB::table('pay_plans')->insert([
                    'name'            => $plan['name'],
                    'slug'            => $plan['slug'],
                    'description'     => $plan['description'],
                    'tier_level'      => $plan['tier_level'],
                    'max_users'       => $plan['max_users'],
                    'price_monthly'   => $plan['price_monthly'],
                    'price_yearly'    => $plan['price_yearly'],
                    'features'        => json_encode($plan['features']),
                    'allowed_layouts' => '[]',
                    'is_active'       => 1,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('pay_plans')->whereIn('slug', ['community', 'partner', 'network'])->delete();
    }
};
