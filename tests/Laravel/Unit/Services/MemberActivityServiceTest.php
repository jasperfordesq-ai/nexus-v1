<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Connection;
use App\Models\FeedPost;
use App\Models\Transaction;
use App\Services\MemberActivityService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MemberActivityServiceTest extends TestCase
{
    private MemberActivityService $service;
    private $transactionAlias;
    private $connectionAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionAlias = Mockery::mock('alias:' . Transaction::class);
        $this->connectionAlias = Mockery::mock('alias:' . Connection::class);
        $this->service = new MemberActivityService();
    }

    public function test_getHours_returns_given_received_balance(): void
    {
        $givenQuery = Mockery::mock();
        $givenQuery->shouldReceive('where')->andReturnSelf();
        $givenQuery->shouldReceive('sum')->andReturn(10.0);
        $this->transactionAlias->shouldReceive('query')->andReturn($givenQuery);

        $receivedQuery = Mockery::mock();
        $receivedQuery->shouldReceive('where')->andReturnSelf();
        $receivedQuery->shouldReceive('sum')->andReturn(5.0);
        $this->transactionAlias->shouldReceive('query')->andReturn($receivedQuery);

        $result = $this->service->getHours(1);

        $this->assertArrayHasKey('given', $result);
        $this->assertArrayHasKey('received', $result);
        $this->assertArrayHasKey('balance', $result);
    }

    public function test_getHoursSummary_returns_full_structure(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('sum')->andReturn(10.0);
        $query->shouldReceive('count')->andReturn(3);
        $this->transactionAlias->shouldReceive('query')->andReturn($query);

        $result = $this->service->getHoursSummary(1);

        $this->assertArrayHasKey('hours_given', $result);
        $this->assertArrayHasKey('hours_received', $result);
        $this->assertArrayHasKey('transactions_given', $result);
        $this->assertArrayHasKey('transactions_received', $result);
        $this->assertArrayHasKey('net_balance', $result);
    }

    public function test_getConnectionStats_returns_counts(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orWhere')->andReturnSelf();
        $query->shouldReceive('count')->andReturn(5);
        $this->connectionAlias->shouldReceive('query')->andReturn($query);

        $pendingQuery = Mockery::mock();
        $pendingQuery->shouldReceive('where')->andReturnSelf();
        $pendingQuery->shouldReceive('count')->andReturn(2);
        $this->connectionAlias->shouldReceive('query')->andReturn($pendingQuery);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(3);

        $result = $this->service->getConnectionStats(1);

        $this->assertArrayHasKey('total_connections', $result);
        $this->assertArrayHasKey('pending_requests', $result);
        $this->assertArrayHasKey('groups_joined', $result);
    }
}
