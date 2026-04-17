<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\MemberRankingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class MemberRankingServiceTest extends TestCase
{
    private MemberRankingService $service;
    private $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUser = Mockery::mock(User::class)->makePartial();
        $this->service = new MemberRankingService($this->mockUser);
    }

    public function test_rankMembers_empty_users_returns_empty(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf()->atLeast()->times(3);
        $query->shouldReceive('select')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockUser->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->rankMembers(2);
        $this->assertSame(['items' => [], 'total' => 0], $result);
    }

    public function test_isEnabled_returns_true_by_default(): void
    {
        DB::shouldReceive('table')->with('communityrank_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $this->assertTrue($this->service->isEnabled());
    }

    public function test_getConfig_returns_defaults(): void
    {
        DB::shouldReceive('table')->with('communityrank_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $config = $this->service->getConfig();
        $this->assertTrue($config['enabled']);
        $this->assertSame(30, $config['activity_lookback_days']);
        $this->assertSame(0.25, $config['activity_weight']);
        $this->assertSame(0.20, $config['connectivity_weight']);
        $this->assertSame(0.10, $config['proximity_weight']);
    }

    public function test_getConfig_reads_dedicated_communityrank_settings(): void
    {
        DB::shouldReceive('table')->with('communityrank_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'is_enabled' => 0,
            'activity_weight' => 0.5,
            'contribution_weight' => 0.3,
            'reputation_weight' => 0.2,
            'connectivity_weight' => 0.15,
            'proximity_weight' => 0.1,
        ]);

        $config = $this->service->getConfig();
        $this->assertFalse($config['enabled']);
        $this->assertSame(0.5, $config['activity_weight']);
        $this->assertSame(0.15, $config['connectivity_weight']);
    }

    public function test_clearCache_clears_key(): void
    {
        Cache::shouldReceive('forget')->once()->with('community_rank:2');
        $this->service->clearCache();
    }
}
