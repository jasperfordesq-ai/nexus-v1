<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\InactiveMemberService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class InactiveMemberServiceTest extends TestCase
{
    private InactiveMemberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InactiveMemberService();
    }

    public function test_detectInactive_returns_summary(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->detectInactive(2, 90);

        $this->assertArrayHasKey('tenant_id', $result);
        $this->assertArrayHasKey('threshold_days', $result);
        $this->assertArrayHasKey('total_flagged', $result);
        $this->assertArrayHasKey('resolved', $result);
        $this->assertSame(2, $result['tenant_id']);
        $this->assertSame(90, $result['threshold_days']);
    }

    public function test_getInactiveMembers_returns_paginated_results(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getInactiveMembers(2, 90, null, 50, 0);

        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('threshold_days', $result);
    }

    public function test_getInactiveMembers_clamps_limit(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        // Should not throw with extreme values
        $result = $this->service->getInactiveMembers(2, 90, null, 999, -5);
        $this->assertIsArray($result);
    }

    public function test_getInactivityStats_returns_summary(): void
    {
        DB::shouldReceive('table')->with('member_activity_flags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total_flagged' => 5, 'inactive_count' => 3, 'dormant_count' => 1,
            'at_risk_count' => 1, 'notified_count' => 2,
        ]);

        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(50);

        $result = $this->service->getInactivityStats(2);

        $this->assertSame(50, $result['total_active_members']);
        $this->assertSame(5, $result['total_flagged']);
        $this->assertSame(0.1, $result['inactivity_rate']);
    }

    public function test_markNotified_empty_ids_returns_zero(): void
    {
        $result = $this->service->markNotified(2, []);
        $this->assertSame(0, $result);
    }

    public function test_markNotified_updates_records(): void
    {
        DB::shouldReceive('table')->with('member_activity_flags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(3);

        $result = $this->service->markNotified(2, [1, 2, 3]);
        $this->assertSame(3, $result);
    }
}
