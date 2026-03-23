<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TenantVisibilityService;
use App\Core\SuperPanelAccess;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class TenantVisibilityServiceTest extends TestCase
{
    public function test_getVisibleTenantIds_returns_empty_when_access_denied(): void
    {
        Mockery::mock('alias:' . SuperPanelAccess::class)
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => false]);

        $result = TenantVisibilityService::getVisibleTenantIds();
        $this->assertEmpty($result);
    }

    public function test_getTenantList_returns_empty_on_error(): void
    {
        // Force an error
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));
        Mockery::mock('alias:' . SuperPanelAccess::class)
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => true, 'level' => 'master']);

        $result = TenantVisibilityService::getTenantList();
        $this->assertEmpty($result);
    }

    public function test_getTenant_returns_null_when_access_denied(): void
    {
        Mockery::mock('alias:' . SuperPanelAccess::class)
            ->shouldReceive('canAccessTenant')
            ->with(999)
            ->andReturn(false);

        $this->assertNull(TenantVisibilityService::getTenant(999));
    }

    public function test_getDashboardStats_returns_zeroed_when_no_visible_ids(): void
    {
        Mockery::mock('alias:' . SuperPanelAccess::class)
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => false]);

        $result = TenantVisibilityService::getDashboardStats();

        $this->assertEquals(0, $result['total_tenants']);
        $this->assertEquals(0, $result['total_users']);
        $this->assertEmpty($result['recent_tenants']);
    }
}
