<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GoalProgressService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Mockery;

class GoalProgressServiceTest extends TestCase
{
    private GoalProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoalProgressService();
    }

    public function test_getProgressHistory_returns_expected_structure(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'goal_id' => 1, 'event_type' => 'checkin'],
        ]);

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->with('goal_id', 1)->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->with('id')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->with(51)->andReturn($rows);

        DB::shouldReceive('table')->with('goal_progress_history')->andReturn($mockQuery);

        $this->markTestIncomplete('Complex query builder mock chain — requires integration test');
    }

    public function test_getSummary_returns_expected_structure(): void
    {
        // getSummary() prechecks tenant ownership via Goal::findOrFail() (HasTenantScope
        // global scope), so a real Goal row plus history rows must exist for the
        // pinned test tenant (2). Seed them and run against the real DB.
        $tenantId = TenantContext::getId();

        $goalId = DB::table('goals')->insertGetId([
            'tenant_id'     => $tenantId,
            'user_id'       => 1,
            'title'         => 'Summary test goal',
            'status'        => 'active',
            'current_value' => 0,
            'target_value'  => 0,
            'created_at'    => now(),
        ]);

        $rows = [
            ['checkin', 'checkin'], ['checkin', 'checkin'], ['checkin', 'checkin'],
            ['milestone', 'milestone'], ['milestone', 'milestone'],
        ];
        foreach ($rows as [$type]) {
            DB::table('goal_progress_history')->insert([
                'goal_id'    => $goalId,
                'tenant_id'  => $tenantId,
                'event_type' => $type,
                'description' => 'evt',
                'created_at' => now(),
            ]);
        }

        // Factory/observer side effects may reset the tenant context to 1.
        TenantContext::setById($tenantId);

        $result = $this->service->getSummary($goalId);
        $this->assertArrayHasKey('total_events', $result);
        $this->assertArrayHasKey('events_by_type', $result);
        $this->assertEquals(5, $result['total_events']);
        $this->assertEquals(3, $result['events_by_type']['checkin']);
        $this->assertEquals(2, $result['events_by_type']['milestone']);
    }
}
