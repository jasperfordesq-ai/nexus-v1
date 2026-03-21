<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\NexusScoreCache;
use App\Models\User;
use App\Services\NexusScoreCacheService;
use App\Services\NexusScoreService;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Laravel\TestCase;

class NexusScoreCacheServiceTest extends TestCase
{
    private NexusScoreCacheService $service;
    private $mockScoreService;
    private $mockCache;
    private $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockCache = Mockery::mock(NexusScoreCache::class)->makePartial();
        $this->mockScoreService = Mockery::mock(NexusScoreService::class);
        $this->mockUser = Mockery::mock(User::class)->makePartial();

        $this->service = new NexusScoreCacheService(
            $this->mockCache,
            $this->mockScoreService,
            $this->mockUser,
        );
    }

    public function test_get_returns_null_when_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(false);

        $this->assertNull($this->service->get(2, 1));
    }

    public function test_get_returns_null_when_no_cached_data(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn(null);
        $this->mockCache->shouldReceive('newQuery')->andReturn($query);

        $this->assertNull($this->service->get(2, 1));
    }

    public function test_get_returns_float_when_cached(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(true);

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn((object) ['total_score' => 450.5]);
        $this->mockCache->shouldReceive('newQuery')->andReturn($query);

        $this->assertSame(450.5, $this->service->get(2, 1));
    }

    public function test_set_does_nothing_when_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(false);

        $this->service->set(2, 1, 500.0);
        // No exception = pass
        $this->assertTrue(true);
    }

    public function test_invalidate_does_nothing_when_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(false);

        $this->service->invalidate(2, 1);
        $this->assertTrue(true);
    }

    public function test_getCachedRank_returns_defaults_when_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(false);
        $this->mockScoreService->shouldReceive('calculateNexusScore')->andReturn(['total_score' => 100]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('pluck')->andReturn(collect([]));
        $this->mockUser->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getCachedRank(1, 2);
        $this->assertArrayHasKey('rank', $result);
        $this->assertArrayHasKey('score', $result);
    }

    public function test_getCachedLeaderboard_returns_defaults_when_table_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('nexus_score_cache')->andReturn(false);

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockUser->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getCachedLeaderboard(2, 10);
        $this->assertArrayHasKey('top_users', $result);
        $this->assertArrayHasKey('community_average', $result);
    }
}
