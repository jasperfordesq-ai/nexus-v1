<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GoalProgressService;
use Illuminate\Support\Facades\DB;

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
        DB::shouldReceive('table->where->count')->andReturn(5);
        DB::shouldReceive('table->where->selectRaw->groupBy->pluck->all')
            ->andReturn(['checkin' => 3, 'milestone' => 2]);

        $result = $this->service->getSummary(1);
        $this->assertArrayHasKey('total_events', $result);
        $this->assertArrayHasKey('events_by_type', $result);
        $this->assertEquals(5, $result['total_events']);
    }
}
