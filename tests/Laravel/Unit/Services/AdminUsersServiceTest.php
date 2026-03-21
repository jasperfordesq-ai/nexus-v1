<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AdminUsersService;
use App\Models\User;
use Mockery;

class AdminUsersServiceTest extends TestCase
{
    private AdminUsersService $service;
    private $mockUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUser = Mockery::mock(User::class);
        $this->service = new AdminUsersService($this->mockUser);
    }

    public function test_getAll_returns_items_and_total(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('offset')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $mockQuery->shouldReceive('toArray')->andReturn([]);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getAll(2);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_getAll_clamps_limit_to_100(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('offset')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->with(100)->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $mockQuery->shouldReceive('toArray')->andReturn([]);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->service->getAll(2, ['limit' => 200]);
        $this->assertTrue(true);
    }

    public function test_getAll_applies_status_filter(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        $mockQuery->shouldReceive('where')->with('status', 'active')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('offset')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $mockQuery->shouldReceive('toArray')->andReturn([]);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->service->getAll(2, ['status' => 'active']);
        $this->assertTrue(true);
    }

    public function test_ban_returns_true_on_success(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(1);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->assertTrue($this->service->ban(1, 2, 'Spammer'));
    }

    public function test_ban_returns_false_when_user_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(0);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->assertFalse($this->service->ban(999, 2));
    }

    public function test_unban_returns_true_on_success(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(1);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->assertTrue($this->service->unban(1, 2));
    }

    public function test_unban_returns_false_when_not_banned(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('update')->andReturn(0);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $this->assertFalse($this->service->unban(1, 2));
    }

    public function test_getStats_returns_expected_keys(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('selectRaw')->andReturnSelf();
        $mockQuery->shouldReceive('groupBy')->andReturnSelf();
        $mockQuery->shouldReceive('pluck')->andReturn(collect(['active' => 10]));
        $mockQuery->shouldReceive('all')->andReturn(['active' => 10]);
        $mockQuery->shouldReceive('count')->andReturn(5);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getStats(2);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('active_last_week', $result);
    }
}
