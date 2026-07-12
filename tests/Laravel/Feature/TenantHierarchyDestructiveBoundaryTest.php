<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantHierarchyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class TenantHierarchyDestructiveBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_low_level_hard_delete_is_disabled_even_for_an_empty_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $result = TenantHierarchyService::deleteTenant((int) $tenant->id, true);

        $this->assertFalse($result['success']);
        $this->assertSame(__('api.super_hard_delete_disabled'), $result['error']);
        $this->assertTrue(DB::table('tenants')->where('id', $tenant->id)->exists());
    }

    public function test_hard_delete_refuses_to_reassign_users_across_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->forTenant((int) $tenant->id)->create();

        $result = TenantHierarchyService::deleteTenant((int) $tenant->id, true);

        $this->assertFalse($result['success']);
        $this->assertSame(__('api.super_hard_delete_disabled'), $result['error']);
        $this->assertTrue(DB::table('tenants')->where('id', $tenant->id)->exists());
        $this->assertSame(
            (int) $tenant->id,
            (int) DB::table('users')->where('id', $user->id)->value('tenant_id')
        );
    }

    public function test_hard_delete_refuses_to_reparent_even_inactive_children(): void
    {
        $tenant = Tenant::factory()->create([
            'path' => '/hard-delete-parent/',
            'allows_subtenants' => 1,
        ]);
        $child = Tenant::factory()->create([
            'parent_id' => $tenant->id,
            'path' => '/hard-delete-parent/child/',
            'depth' => 1,
            'is_active' => 0,
        ]);

        $result = TenantHierarchyService::deleteTenant((int) $tenant->id, true);

        $this->assertFalse($result['success']);
        $this->assertSame(__('api.super_hard_delete_disabled'), $result['error']);
        $this->assertTrue(DB::table('tenants')->where('id', $tenant->id)->exists());
        $this->assertSame(
            (int) $tenant->id,
            (int) DB::table('tenants')->where('id', $child->id)->value('parent_id')
        );
    }
}
