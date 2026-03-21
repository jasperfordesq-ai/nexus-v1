<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BadgeService;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Mockery;

class BadgeServiceTest extends TestCase
{
    private BadgeService $service;
    private $mockUserBadge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUserBadge = Mockery::mock(UserBadge::class);
        $this->service = new BadgeService($this->mockUserBadge);
    }

    public function test_getAll_returns_badges_for_tenant(): void
    {
        DB::shouldReceive('table')->with('badges')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orWhereNull')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getAll(2);
        $this->assertIsArray($result);
    }

    public function test_award_returns_false_when_badge_already_exists(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('exists')->andReturn(true);

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->award(1, 1, 2);
        $this->assertFalse($result);
    }

    public function test_award_returns_true_and_inserts_when_not_exists(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('exists')->andReturn(false);
        $mockQuery->shouldReceive('insert')->once();

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->award(1, 1, 2, 10);
        $this->assertTrue($result);
    }

    public function test_revoke_returns_true_on_success(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(1);

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->revoke(1, 1, 2);
        $this->assertTrue($result);
    }

    public function test_revoke_returns_false_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(0);

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->revoke(1, 999, 2);
        $this->assertFalse($result);
    }

    public function test_getUserBadges_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getUserBadges(1, 2);
        $this->assertIsArray($result);
    }
}
