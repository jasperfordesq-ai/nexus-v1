<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consolidate all tenants onto the single free Starter plan and
 * remove every other plan row from pay_plans.
 *
 * Background: the DB accumulated multiple overlapping plan sets —
 * legacy SQL seeds (Free/Basic/Professional/Enterprise), a Laravel
 * PHP seed (Seed/Community/Regional/Network), and the redesigned set
 * (Starter/Community/Partner/Network), plus manual entries
 * (Solidarity/National/Federation). All 6 active tenants were sitting
 * on the old Enterprise plan (€0, tier 3) from the very first seed.
 *
 * After this migration only ONE plan row exists: Starter (free, tier 0).
 * Paid tiers will be created fresh from the super-admin UI when needed,
 * with correct per-member-count descriptions and Stripe sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Locate the Starter plan (slug='starter') — created by the
        //    previous redesign migration. This is the one we keep.
        $starter = DB::table('pay_plans')->where('slug', 'starter')->first();

        if (!$starter) {
            // Safety net: insert it if it somehow doesn't exist
            $starterId = DB::table('pay_plans')->insertGetId([
                'name'          => 'Starter',
                'slug'          => 'starter',
                'description'   => 'For community timebanks getting started with time banking.',
                'tier_level'    => 0,
                'max_users'     => 50,
                'price_monthly' => 0.00,
                'price_yearly'  => 0.00,
                'features'      => json_encode(['Up to 50 community members']),
                'allowed_layouts' => '[]',
                'is_active'     => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } else {
            $starterId = $starter->id;
        }

        // 2. Fix Starter plan data — description and features must
        //    describe member limits only, not platform capabilities.
        DB::table('pay_plans')->where('id', $starterId)->update([
            'name'          => 'Starter',
            'slug'          => 'starter',
            'description'   => 'For community timebanks getting started with time banking.',
            'tier_level'    => 0,
            'max_users'     => 50,
            'price_monthly' => 0.00,
            'price_yearly'  => 0.00,
            'features'      => json_encode(['Up to 50 community members']),
            'is_active'     => 1,
            'updated_at'    => now(),
        ]);

        Log::info("consolidate_tenants_to_free_plan: Starter plan is id={$starterId}");

        // 3. Point every tenant_plan_assignment to Starter.
        //    Clear Stripe subscription IDs — none were paid anyway.
        $moved = DB::table('tenant_plan_assignments')->update([
            'pay_plan_id'           => $starterId,
            'status'                => 'active',
            'stripe_subscription_id' => null,
            'notes'                 => 'Consolidated to Starter during platform setup (2026-04-18)',
            'updated_at'            => now(),
        ]);

        Log::info("consolidate_tenants_to_free_plan: moved {$moved} assignment(s) to Starter");

        // 4. Delete every plan that is not Starter.
        //    Foreign key ON DELETE RESTRICT is now safe because step 3
        //    moved all assignments away from these rows.
        $deleted = DB::table('pay_plans')->where('id', '!=', $starterId)->delete();

        Log::info("consolidate_tenants_to_free_plan: deleted {$deleted} redundant plan row(s)");
    }

    public function down(): void
    {
        // Non-destructive rollback: we cannot restore the deleted plan
        // rows without the original data. This migration is intentionally
        // not fully reversible — re-create plans via the super-admin UI.
        Log::warning('consolidate_tenants_to_free_plan: down() called — plan rows were permanently deleted, cannot restore automatically');
    }
};
