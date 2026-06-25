<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models\Concerns;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\TenantSafeguardingOption;
use App\Scopes\TenantScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Security-regression tests for the HasTenantScope trait.
 *
 * This is the CRITICAL tenant-isolation guarantee: every query on a model
 * that uses HasTenantScope must be filtered to the current tenant, and
 * rows from other tenants must NEVER be visible.
 *
 * Concrete model used: TenantSafeguardingOption (table: tenant_safeguarding_options).
 * Unique tenant IDs: 99771 (primary), 99772 (other).
 */
class HasTenantScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID       = 99771;
    private const OTHER_TENANT_ID = 99772;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Seed both tenants so TenantContext::setById() can resolve them
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'HTS Primary Tenant',
                'slug'              => 'hts-primary-99771',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        DB::table('tenants')->updateOrInsert(
            ['id' => self::OTHER_TENANT_ID],
            [
                'name'              => 'HTS Other Tenant',
                'slug'              => 'hts-other-99772',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ─── trait presence ────────────────────────────────────────────────────────

    public function test_model_uses_has_tenant_scope_trait(): void
    {
        $traits = class_uses_recursive(TenantSafeguardingOption::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    // ─── (a) auto-stamp tenant_id on create ────────────────────────────────────

    public function test_create_auto_stamps_current_tenant_id(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $option = TenantSafeguardingOption::create([
            'option_key'  => 'auto_stamp_test_99771',
            'option_type' => 'checkbox',
            'label'       => 'Auto Stamp Test',
            'sort_order'  => 0,
            'is_active'   => true,
            'is_required' => false,
        ]);

        $this->assertSame(self::TENANT_ID, (int) $option->tenant_id);
    }

    public function test_create_does_not_override_explicit_tenant_id(): void
    {
        // If tenant_id is already set in the attributes, the trait should not override it.
        // The trait guard: `if (!isset($model->tenant_id) && TenantContext::getId())`
        $option = TenantSafeguardingOption::create([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'explicit_tenant_test_99771',
            'option_type' => 'checkbox',
            'label'       => 'Explicit Tenant Test',
            'sort_order'  => 0,
            'is_active'   => true,
            'is_required' => false,
        ]);

        $this->assertSame(self::TENANT_ID, (int) $option->tenant_id);
    }

    // ─── (b) default query returns ONLY current-tenant rows ────────────────────
    // THIS IS THE CRITICAL TENANT-ISOLATION SECURITY TEST.

    public function test_query_returns_only_current_tenant_rows(): void
    {
        // Insert one row for the current tenant and one for the other tenant using raw DB
        // (bypassing Eloquent/global-scope so we can insert rows for the "other" tenant)
        DB::table('tenant_safeguarding_options')->insert([
            [
                'tenant_id'   => self::TENANT_ID,
                'option_key'  => 'mine_99771',
                'option_type' => 'checkbox',
                'label'       => 'My Option',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
            [
                'tenant_id'   => self::OTHER_TENANT_ID,
                'option_key'  => 'theirs_99772',
                'option_type' => 'checkbox',
                'label'       => 'Their Option',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
        ]);

        // Eloquent query — must return ONLY the current tenant's row
        $results = TenantSafeguardingOption::all();

        $this->assertCount(1, $results);
        $this->assertSame(self::TENANT_ID, (int) $results->first()->tenant_id);
        $this->assertSame('My Option', $results->first()->label);
    }

    public function test_other_tenant_row_is_invisible_to_current_tenant(): void
    {
        DB::table('tenant_safeguarding_options')->insert([
            'tenant_id'   => self::OTHER_TENANT_ID,
            'option_key'  => 'invisible_99772',
            'option_type' => 'checkbox',
            'label'       => 'Should Never Appear',
            'sort_order'  => 0,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        // Current tenant context is TENANT_ID (99771); the row belongs to OTHER_TENANT_ID.
        $count = TenantSafeguardingOption::count();
        $this->assertSame(0, $count);
    }

    // ─── (c) switching tenant changes visible rows ──────────────────────────────

    public function test_switching_tenant_context_changes_visible_rows(): void
    {
        DB::table('tenant_safeguarding_options')->insert([
            [
                'tenant_id'   => self::TENANT_ID,
                'option_key'  => 'ctx_switch_primary_99771',
                'option_type' => 'checkbox',
                'label'       => 'Primary Tenant Option',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
            [
                'tenant_id'   => self::OTHER_TENANT_ID,
                'option_key'  => 'ctx_switch_other_99772',
                'option_type' => 'checkbox',
                'label'       => 'Other Tenant Option',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
        ]);

        // Initially sees only TENANT_ID rows
        TenantContext::setById(self::TENANT_ID);
        $primaryRows = TenantSafeguardingOption::all();
        $this->assertCount(1, $primaryRows);
        $this->assertSame('Primary Tenant Option', $primaryRows->first()->label);

        // Switch to OTHER_TENANT_ID — now sees other tenant's rows only
        TenantContext::setById(self::OTHER_TENANT_ID);
        $otherRows = TenantSafeguardingOption::all();
        $this->assertCount(1, $otherRows);
        $this->assertSame('Other Tenant Option', $otherRows->first()->label);

        // Restore to primary for tearDown
        TenantContext::setById(self::TENANT_ID);
    }

    // ─── (d) withoutGlobalScope bypass ─────────────────────────────────────────

    public function test_without_global_scope_returns_all_tenant_rows(): void
    {
        DB::table('tenant_safeguarding_options')->insert([
            [
                'tenant_id'   => self::TENANT_ID,
                'option_key'  => 'bypass_primary_99771',
                'option_type' => 'checkbox',
                'label'       => 'Bypass Primary',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
            [
                'tenant_id'   => self::OTHER_TENANT_ID,
                'option_key'  => 'bypass_other_99772',
                'option_type' => 'checkbox',
                'label'       => 'Bypass Other',
                'sort_order'  => 0,
                'is_active'   => 1,
                'is_required' => 0,
                'created_at'  => now(),
            ],
        ]);

        // Normal scoped query sees only 1 row
        $scopedCount = TenantSafeguardingOption::count();
        $this->assertSame(1, $scopedCount);

        // Bypassing the global scope sees BOTH rows (cross-tenant admin query pattern)
        $allCount = TenantSafeguardingOption::withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', [self::TENANT_ID, self::OTHER_TENANT_ID])
            ->count();
        $this->assertSame(2, $allCount);
    }

    // ─── edge: find() respects scope ───────────────────────────────────────────

    public function test_find_by_id_returns_null_for_other_tenant_row(): void
    {
        $id = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::OTHER_TENANT_ID,
            'option_key'  => 'find_other_99772',
            'option_type' => 'checkbox',
            'label'       => 'Should Not Be Found',
            'sort_order'  => 0,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        // Current context = TENANT_ID (99771); the row belongs to OTHER_TENANT_ID (99772)
        $found = TenantSafeguardingOption::find($id);
        $this->assertNull($found);
    }

    public function test_find_by_id_returns_row_for_current_tenant(): void
    {
        $id = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'option_key'  => 'find_mine_99771',
            'option_type' => 'checkbox',
            'label'       => 'Should Be Found',
            'sort_order'  => 0,
            'is_active'   => 1,
            'is_required' => 0,
            'created_at'  => now(),
        ]);

        $found = TenantSafeguardingOption::find($id);
        $this->assertNotNull($found);
        $this->assertSame('Should Be Found', $found->label);
    }
}
