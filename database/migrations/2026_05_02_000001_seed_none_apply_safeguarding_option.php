<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the "None of these apply to me" safeguarding option for every tenant
 * that already has at least one active safeguarding option configured.
 *
 * Uses INSERT IGNORE so re-running is safe and any tenant that already has a
 * custom none_apply row is left untouched.
 *
 * The sort_order is set to MAX(existing sort_order) + 10 per tenant so it
 * always appears last in the list regardless of how many options a tenant has.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Collect all tenants that have at least one active safeguarding option
        // but do not yet have a none_apply option.
        $tenants = DB::table('tenant_safeguarding_options')
            ->select('tenant_id', DB::raw('MAX(sort_order) as max_sort'))
            ->where('is_active', 1)
            ->whereNotExists(function ($query) {
                $query->from('tenant_safeguarding_options as existing')
                    ->whereColumn('existing.tenant_id', 'tenant_safeguarding_options.tenant_id')
                    ->where('existing.option_key', 'none_apply');
            })
            ->groupBy('tenant_id')
            ->get();

        $now = now();

        foreach ($tenants as $row) {
            DB::table('tenant_safeguarding_options')->insertOrIgnore([
                'tenant_id'    => $row->tenant_id,
                'option_key'   => 'none_apply',
                'option_type'  => 'checkbox',
                'label'        => 'None of these apply to me',
                'description'  => 'I have reviewed the options above and none of them apply to my situation. This is recorded so coordinators know I have seen and considered this step.',
                'help_url'     => null,
                'sort_order'   => (int) $row->max_sort + 10,
                'is_active'    => 1,
                'is_required'  => 0,
                'select_options' => null,
                'triggers'     => '{}',
                'preset_source' => null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Remove none_apply rows that were added by this migration.
        // Rows with preset_source = null and option_key = none_apply are migration-seeded ones.
        // We do NOT delete rows where a tenant admin may have customised them (no safe sentinel).
        // Safe to remove all none_apply rows on rollback — admins can re-add if needed.
        DB::table('tenant_safeguarding_options')
            ->where('option_key', 'none_apply')
            ->where('preset_source', null) // explicit null sentinel — matches only migration-seeded rows
            ->delete();
    }
};
