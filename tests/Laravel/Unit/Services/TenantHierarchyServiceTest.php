<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TenantHierarchyService;
use App\Services\SuperAdminAuditService;
use Illuminate\Support\Facades\DB;
use Mockery;

class TenantHierarchyServiceTest extends TestCase
{
    public function test_createTenant_fails_when_parent_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 999)->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::createTenant(['name' => 'Test'], 999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Parent tenant not found', $result['error']);
    }

    public function test_createTenant_fails_when_name_empty(): void
    {
        $parent = (object) ['id' => 1, 'depth' => 0, 'allows_subtenants' => 1, 'max_depth' => 3, 'path' => '/1/'];
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($parent);

        $result = TenantHierarchyService::createTenant(['name' => ''], 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', $result['error']);
    }

    public function test_updateTenant_fails_when_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 999)->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::updateTenant(999, ['name' => 'Updated']);

        $this->assertFalse($result['success']);
        $this->assertEquals('Tenant not found', $result['error']);
    }

    public function test_updateTenant_fails_when_no_valid_fields(): void
    {
        $tenant = (object) ['id' => 2, 'name' => 'Old', 'slug' => 'old'];
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($tenant);

        $result = TenantHierarchyService::updateTenant(2, ['invalid_field' => 'value']);

        $this->assertFalse($result['success']);
        $this->assertEquals('No valid fields to update', $result['error']);
    }

    public function test_deleteTenant_prevents_deleting_master(): void
    {
        $result = TenantHierarchyService::deleteTenant(1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot delete the Master tenant', $result['error']);
    }

    public function test_deleteTenant_fails_when_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::deleteTenant(999);

        $this->assertFalse($result['success']);
    }

    public function test_moveTenant_prevents_moving_master(): void
    {
        $result = TenantHierarchyService::moveTenant(1, 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot move the Master tenant', $result['error']);
    }

    public function test_moveTenant_prevents_self_parent(): void
    {
        $result = TenantHierarchyService::moveTenant(5, 5);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('own parent', $result['error']);
    }

    public function test_toggleSubtenantCapability_fails_when_not_found(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::toggleSubtenantCapability(999, true);

        $this->assertFalse($result['success']);
    }

    public function test_assignTenantSuperAdmin_fails_when_user_not_found(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::assignTenantSuperAdmin(999, 2);

        $this->assertFalse($result['success']);
    }

    public function test_revokeTenantSuperAdmin_fails_when_user_not_found(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = TenantHierarchyService::revokeTenantSuperAdmin(999);

        $this->assertFalse($result['success']);
    }
}
