<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PollService;
use App\Models\Poll;
use Illuminate\Support\Facades\DB;
use Mockery;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class PollServiceTest extends TestCase
{
    private $pollAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pollAlias = Mockery::mock('alias:' . Poll::class);
    }

    // ── getById ──

    public function test_getById_returns_null_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('find')->with(0)->andReturn(null);
        $this->pollAlias->shouldReceive('query')->andReturn($mockQuery);

        $result = PollService::getById(0);
        $this->assertNull($result);
    }

    // ── vote ──

    public function test_vote_returns_false_if_already_voted(): void
    {
        DB::shouldReceive('table->where->where->exists')->andReturn(true);

        $result = PollService::vote(1, 10, 1);
        $this->assertFalse($result);
    }

    public function test_vote_inserts_and_returns_true(): void
    {
        DB::shouldReceive('table->where->where->exists')->andReturn(false);
        DB::shouldReceive('table->insert')->once();

        $result = PollService::vote(1, 10, 1);
        $this->assertTrue($result);
    }

    // ── delete ──

    public function test_delete_returns_false_for_wrong_owner(): void
    {
        $poll = (object) ['user_id' => 5];

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(1)->andReturn($poll);
        $this->pollAlias->shouldReceive('query')->andReturn($mockQuery);

        $result = PollService::delete(1, 99);
        $this->assertFalse($result);
    }

    // ── getErrors ──

    public function test_getErrors_returns_empty_array(): void
    {
        $result = PollService::getErrors();
        $this->assertEquals([], $result);
    }

    // ── getCategories ──

    public function test_getCategories_returns_array(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('distinct')->andReturnSelf();
        $mockQuery->shouldReceive('pluck')->andReturn(collect(['general', 'tech']));
        $this->pollAlias->shouldReceive('query')->andReturn($mockQuery);

        $result = PollService::getCategories();
        $this->assertIsArray($result);
    }
}
