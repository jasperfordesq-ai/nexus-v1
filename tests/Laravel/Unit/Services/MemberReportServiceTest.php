<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MemberReportService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MemberReportServiceTest extends TestCase
{
    private MemberReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MemberReportService();
    }

    public function test_getActiveMembers_returns_structure(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getActiveMembers(2, 30);

        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('period_days', $result);
    }

    public function test_getNewRegistrations_returns_structure(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));
        DB::shouldReceive('count')->andReturn(0);

        $result = $this->service->getNewRegistrations(2, 'monthly');

        $this->assertSame('monthly', $result['period_type']);
        $this->assertArrayHasKey('total_registrations', $result);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_getMemberRetention_returns_cohorts(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereBetween')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(10, 5); // joined, retained for each cohort

        $result = $this->service->getMemberRetention(2, 1);

        $this->assertArrayHasKey('cohorts', $result);
        $this->assertArrayHasKey('overall', $result);
        $this->assertCount(1, $result['cohorts']);
        $this->assertSame(0.5, $result['cohorts'][0]['retention_rate']);
    }

    public function test_getMemberRetention_zero_joined_no_division_error(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereBetween')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0, 0);

        $result = $this->service->getMemberRetention(2, 1);
        $this->assertSame(0, $result['cohorts'][0]['retention_rate']);
    }

    public function test_getTopContributors_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('havingRaw')->andReturnSelf();
        DB::shouldReceive('orderByRaw')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getTopContributors(2);
        $this->assertSame([], $result);
    }

    public function test_getLeastActiveMembers_returns_structure(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getLeastActiveMembers(2);
        $this->assertArrayHasKey('members', $result);
        $this->assertArrayHasKey('total', $result);
    }
}
