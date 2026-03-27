<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\AdminListingsService;
use Illuminate\Support\Facades\DB;

class AdminListingsServiceTest extends TestCase
{
    private AdminListingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminListingsService();
    }

    public function test_getPending_returns_expected_structure(): void
    {
        $mockQuery = \Mockery::mock('Illuminate\Database\Query\Builder');
        $mockQuery->shouldReceive('leftJoin')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(2);
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('offset')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([
            (object) ['id' => 1, 'title' => 'Pending One', 'status' => 'pending', 'author_name' => 'Alice'],
            (object) ['id' => 2, 'title' => 'Pending Two', 'status' => 'pending', 'author_name' => 'Bob'],
        ]));

        DB::shouldReceive('table')->with('listings as l')->andReturn($mockQuery);

        $result = $this->service->getPending(2);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals('Pending One', $result['items'][0]['title']);
        $this->assertEquals('Alice', $result['items'][0]['author_name']);
    }

    public function test_approve_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $result = $this->service->approve(1, 2, 10);
        $this->assertTrue($result);
    }

    public function test_approve_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(0);

        $result = $this->service->approve(999, 2, 10);
        $this->assertFalse($result);
    }

    public function test_reject_returns_true_on_success(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);

        $result = $this->service->reject(1, 2, 10, 'Spam');
        $this->assertTrue($result);
    }

    public function test_reject_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(0);

        $result = $this->service->reject(999, 2, 10);
        $this->assertFalse($result);
    }

    public function test_getStats_returns_expected_keys(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['active' => 5, 'pending' => 3]));
        DB::shouldReceive('all')->andReturn(['active' => 5, 'pending' => 3]);

        $result = $this->service->getStats(2);

        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('rejected', $result);
        $this->assertArrayHasKey('expired', $result);
        $this->assertArrayHasKey('total', $result);
    }
}
