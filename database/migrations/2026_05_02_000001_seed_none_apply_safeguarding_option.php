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
 */
return new class extends Migration
{
    private const PRESET_SOURCE = 'migration_2026_05_02_seed_none_apply';

    public function up(): void
    {
        $tenants = DB::table('tenant_safeguarding_options')
            ->select('tenant_id', DB::raw('MAX(sort_order) as max_sort'))
            ->where('is_active', 1)
            ->whereNotExists(function ($query): void {
                $query->from('tenant_safeguarding_options as existing')
                    ->whereColumn('existing.tenant_id', 'tenant_safeguarding_options.tenant_id')
                    ->where('existing.option_key', 'none_apply');
            })
            ->groupBy('tenant_id')
            ->get();

        $now = now();

        foreach ($tenants as $row) {
            DB::table('tenant_safeguarding_options')->insertOrIgnore([
                'tenant_id' => $row->tenant_id,
                'option_key' => 'none_apply',
                'option_type' => 'checkbox',
                'label' => 'None of these apply to me',
                'description' => 'I have reviewed the options above and none of them apply to my situation. This is recorded so coordinators know I have seen and considered this step.',
                'help_url' => null,
                'sort_order' => (int) $row->max_sort + 10,
                'is_active' => 1,
                'is_required' => 0,
                'select_options' => null,
                'triggers' => '{}',
                'preset_source' => self::PRESET_SOURCE,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $tenantIds = DB::table('tenant_safeguarding_options')
            ->where('option_key', 'none_apply')
            ->where('preset_source', self::PRESET_SOURCE)
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            DB::table('tenant_safeguarding_options')
                ->where('tenant_id', $tenantId)
                ->where('option_key', 'none_apply')
                ->where('preset_source', self::PRESET_SOURCE)
                ->delete();
        }
    }
};
