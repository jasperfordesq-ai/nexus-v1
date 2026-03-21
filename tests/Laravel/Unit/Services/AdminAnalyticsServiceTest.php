<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AdminAnalyticsService;
use App\Models\User;
use App\Models\Transaction;
use Mockery;

class AdminAnalyticsServiceTest extends TestCase
{
    private AdminAnalyticsService $service;
    private $mockUser;
    private $mockTransaction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUser = Mockery::mock(User::class);
        $this->mockTransaction = Mockery::mock(Transaction::class);
        $this->service = new AdminAnalyticsService($this->mockUser, $this->mockTransaction);
    }

    public function test_getDashboard_returns_expected_keys(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('sum')->andReturn(100.0);
        $mockQuery->shouldReceive('count')->andReturn(5);
        $mockQuery->shouldReceive('avg')->andReturn(20.0);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);
        $this->mockTransaction->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getDashboard(2);

        $this->assertArrayHasKey('total_credits_circulation', $result);
        $this->assertArrayHasKey('transaction_volume_30d', $result);
        $this->assertArrayHasKey('transaction_count_30d', $result);
        $this->assertArrayHasKey('new_users_30d', $result);
        $this->assertArrayHasKey('avg_transaction_size', $result);
    }

    public function test_getUserStats_returns_expected_keys(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('selectRaw')->andReturnSelf();
        $mockQuery->shouldReceive('groupBy')->andReturnSelf();
        $mockQuery->shouldReceive('pluck')->andReturn(collect(['active' => 10, 'pending' => 2]));
        $mockQuery->shouldReceive('all')->andReturn(['active' => 10, 'pending' => 2]);
        $mockQuery->shouldReceive('count')->andReturn(3);

        $this->mockUser->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getUserStats(2);

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('active_last_week', $result);
        $this->assertSame(12, $result['total']);
    }

    public function test_getOverallStats_returns_expected_structure(): void
    {
        $this->markTestIncomplete('Requires integration test — uses raw DB facade extensively');
    }

    public function test_getMonthlyTrends_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses raw DB facade with selectRaw');
    }

    public function test_getWeeklyTrends_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses raw DB facade with selectRaw');
    }

    public function test_getTopEarners_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses raw DB facade with join');
    }

    public function test_getTopSpenders_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses raw DB facade with join');
    }
}
