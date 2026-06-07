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
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class TenantVisibilityServiceTest extends TestCase
{
    private $superPanelAlias;

    protected function setUp(): void
    {
        // App\Core\SuperPanelAccess may already be autoloaded by app boot or an
        // earlier test in the combined run, so the alias mock MUST be created
        // before parent::setUp() and tolerate the class already existing.
        // shouldIgnoreMissing() makes boot-time/static calls no-ops; per-test
        // expectations are layered on the shared instance in each test.
        $this->superPanelAlias = Mockery::mock('alias:' . SuperPanelAccess::class)->shouldIgnoreMissing();
        parent::setUp();
    }

    public function test_getVisibleTenantIds_returns_empty_when_access_denied(): void
    {
        $this->superPanelAlias
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => false]);

        $result = TenantVisibilityService::getVisibleTenantIds();
        $this->assertEmpty($result);
    }

    public function test_getTenantList_returns_empty_on_error(): void
    {
        // Force an error
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));
        $this->superPanelAlias
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => true, 'level' => 'master']);

        $result = TenantVisibilityService::getTenantList();
        $this->assertEmpty($result);
    }

    public function test_getTenant_returns_null_when_access_denied(): void
    {
        $this->superPanelAlias
            ->shouldReceive('canAccessTenant')
            ->with(999)
            ->andReturn(false);

        $this->assertNull(TenantVisibilityService::getTenant(999));
    }

    public function test_getDashboardStats_returns_zeroed_when_no_visible_ids(): void
    {
        $this->superPanelAlias
            ->shouldReceive('getAccess')
            ->andReturn(['granted' => false]);

        $result = TenantVisibilityService::getDashboardStats();

        $this->assertEquals(0, $result['total_tenants']);
        $this->assertEquals(0, $result['total_users']);
        $this->assertEmpty($result['recent_tenants']);
    }
}
