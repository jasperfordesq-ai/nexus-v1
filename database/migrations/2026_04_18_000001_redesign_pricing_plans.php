<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the placeholder plan seed data with the official pricing tiers.
 *
 * Tier model (aligned with hosting cost structure):
 *   Starter  — free trial, shared infra, 50 members
 *   Community — community timebanks on shared hosting, 500 members
 *   Partner   — partner organisations with broader programmes, 2 000 members
 *   Network   — full platform operators (Made Open-scale), unlimited
 */
return new class extends Migration
{
    /** @var array<int, array<string, mixed>> */
    private array $plans = [
        [
            'old_slug'      => 'seed',
            'slug'          => 'starter',
            'name'          => 'Starter',
            'description'   => 'Perfect for small community groups piloting time banking. Get up and running quickly with no upfront cost.',
            'tier_level'    => 0,
            'max_users'     => 50,
            'price_monthly' => 0.00,
            'price_yearly'  => 0.00,
            'features'      => [
                'Up to 50 community members',
                'Community listings & time wallet',
                'Member profiles & direct messaging',
                'Community feed',
                'Email support',
            ],
        ],
        [
            'old_slug'      => 'community',
            'slug'          => 'community',
            'name'          => 'Community',
            'description'   => 'Built for established community timebanks. Shared hosting keeps your costs low while giving your members everything they need to thrive.',
            'tier_level'    => 1,
            'max_users'     => 500,
            'price_monthly' => 29.00,
            'price_yearly'  => 290.00,
            'features'      => [
                'Up to 500 community members',
                'Full listings, time wallet & service exchanges',
                'Events, groups & group exchanges',
                'Blog, resources & knowledge base',
                'iOS & Android PWA',
                'Priority email support',
            ],
        ],
        [
            'old_slug'      => 'regional',
            'slug'          => 'partner',
            'name'          => 'Partner',
            'description'   => 'For organisations running timebanks as part of broader social programmes. Includes advanced coordination tools and federation capabilities.',
            'tier_level'    => 2,
            'max_users'     => 2000,
            'price_monthly' => 89.00,
            'price_yearly'  => 890.00,
            'features'      => [
                'Up to 2,000 community members',
                'Everything in Community',
                'Volunteering & organisations',
                'Gamification, challenges & goals',
                'Federation & inter-community connections',
                'Ideation challenges & community polls',
                'Phone & email support',
            ],
        ],
        [
            'old_slug'      => 'network',
            'slug'          => 'network',
            'name'          => 'Network',
            'description'   => 'For platform operators and networks deploying NEXUS at scale. Full access to every module, dedicated infrastructure priority, and a named account manager.',
            'tier_level'    => 3,
            'max_users'     => null,
            'price_monthly' => 249.00,
            'price_yearly'  => 2490.00,
            'features'      => [
                'Unlimited community members',
                'Everything in Partner',
                'AI-powered matching & assistant',
                'Community marketplace',
                'Advanced analytics & reporting',
                'Dedicated infrastructure priority',
                'Named account manager',
            ],
        ],
    ];

    public function up(): void
    {
        foreach ($this->plans as $plan) {
            $oldSlug  = $plan['old_slug'];
            $data     = $this->buildRow($plan);

            $existing = DB::table('pay_plans')->where('slug', $oldSlug)->first();

            if ($existing) {
                DB::table('pay_plans')
                    ->where('id', $existing->id)
                    ->update(array_merge($data, ['updated_at' => now()]));
            } elseif (!DB::table('pay_plans')->where('slug', $plan['slug'])->exists()) {
                DB::table('pay_plans')->insert(array_merge($data, [
                    'is_active'       => 1,
                    'allowed_layouts' => '[]',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Restore previous (placeholder) plan data
        $restores = [
            ['slug' => 'starter',   'restore_slug' => 'seed',      'name' => 'Seed',      'price_monthly' => 0,   'price_yearly' => 0,    'max_users' => 50,   'tier_level' => 0],
            ['slug' => 'community', 'restore_slug' => 'community',  'name' => 'Community', 'price_monthly' => 20,  'price_yearly' => 200,  'max_users' => 250,  'tier_level' => 1],
            ['slug' => 'partner',   'restore_slug' => 'regional',   'name' => 'Regional',  'price_monthly' => 50,  'price_yearly' => 500,  'max_users' => 1000, 'tier_level' => 2],
            ['slug' => 'network',   'restore_slug' => 'network',    'name' => 'Network',   'price_monthly' => 120, 'price_yearly' => 1200, 'max_users' => null, 'tier_level' => 3],
        ];

        foreach ($restores as $r) {
            DB::table('pay_plans')->where('slug', $r['slug'])->update([
                'slug'          => $r['restore_slug'],
                'name'          => $r['name'],
                'description'   => null,
                'price_monthly' => $r['price_monthly'],
                'price_yearly'  => $r['price_yearly'],
                'max_users'     => $r['max_users'],
                'tier_level'    => $r['tier_level'],
                'features'      => '[]',
                'updated_at'    => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $plan */
    private function buildRow(array $plan): array
    {
        return [
            'slug'          => $plan['slug'],
            'name'          => $plan['name'],
            'description'   => $plan['description'],
            'tier_level'    => $plan['tier_level'],
            'max_users'     => $plan['max_users'],
            'price_monthly' => $plan['price_monthly'],
            'price_yearly'  => $plan['price_yearly'],
            'features'      => json_encode($plan['features']),
        ];
    }
};
