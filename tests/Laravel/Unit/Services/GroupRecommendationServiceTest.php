<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupRecommendationService;
use Illuminate\Support\Facades\DB;

class GroupRecommendationServiceTest extends TestCase
{
    private GroupRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupRecommendationService();
    }

    public function test_getRecommendations_excludes_joined_groups(): void
    {
        DB::shouldReceive('table->where->pluck->all')->andReturn([1, 2, 3]);

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('whereNotIn')->with('g.id', [1, 2, 3])->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get->map->all')->andReturn([]);

        DB::shouldReceive('table->leftJoin->where->where->select')->andReturn($mockQuery);

        $this->markTestIncomplete('Complex DB query builder chain — requires integration test');
    }

    public function test_track_inserts_recommendation_event(): void
    {
        DB::shouldReceive('table->insert')->once();

        $this->service->track(1, 5, 'click');
    }
}
