<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ReactionService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Unit tests for ReactionService — toggle, get reactions, get reactors,
 * and batch post reactions.
 *
 * Uses Mockery DB facade expectations to isolate from the database.
 */
class ReactionServiceTest extends TestCase
{
    private ReactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReactionService();

        // Allow DB::raw() calls used in select/groupBy expressions
        DB::shouldReceive('raw')->andReturnUsing(
            fn ($v) => new \Illuminate\Database\Query\Expression($v)
        );
    }

    // ======================================================================
    //  VALID_TYPES constant
    // ======================================================================

    public function test_valid_types_contains_all_expected_types(): void
    {
        $expected = ['love', 'like', 'laugh', 'wow', 'sad', 'celebrate', 'clap', 'time_credit'];
        $this->assertEquals($expected, ReactionService::VALID_TYPES);
    }

    public function test_valid_types_has_eight_entries(): void
    {
        $this->assertCount(8, ReactionService::VALID_TYPES);
    }

    // ======================================================================
    //  toggleReaction — add new
    // ======================================================================

    public function test_toggleReaction_adds_new_reaction(): void
    {
        // 4 where() calls: tenant_id, target_type, target_id, user_id → first()
        DB::shouldReceive('table->where->where->where->where->first')->once()->andReturn(null);

        // Insert
        DB::shouldReceive('table->insert')->once();

        // getReactions internal call — counts query: 3 where() calls + select + groupBy + get
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([(object) ['emoji' => 'love', 'count' => 1]]));

        // getReactions — user reaction: 4 where() calls + first
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn((object) ['emoji' => 'love']);

        // getReactions — top reactors
        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([['id' => 5, 'name' => 'Test User', 'avatar_url' => null]]);

        $result = $this->service->toggleReaction(1, 'post', 'love', 5);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals('love', $result['reaction_type']);
        $this->assertArrayHasKey('reactions', $result);
    }

    public function test_toggleReaction_removes_same_type(): void
    {
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn((object) ['id' => 42, 'emoji' => 'love']);

        // Delete: where(id)->where(tenant_id)->delete
        DB::shouldReceive('table->where->where->delete')->once();

        // getReactions — counts
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([]));

        // getReactions — user reaction
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn(null);

        // getReactions — top reactors
        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([]);

        $result = $this->service->toggleReaction(1, 'post', 'love', 5);

        $this->assertEquals('removed', $result['action']);
        $this->assertNull($result['reaction_type']);
    }

    public function test_toggleReaction_updates_different_type(): void
    {
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn((object) ['id' => 42, 'emoji' => 'love']);

        // Update: where(id)->where(tenant_id)->update
        DB::shouldReceive('table->where->where->update')->once();

        // getReactions — counts
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([(object) ['emoji' => 'laugh', 'count' => 1]]));

        // getReactions — user reaction
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn((object) ['emoji' => 'laugh']);

        // getReactions — top reactors
        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([]);

        $result = $this->service->toggleReaction(1, 'post', 'laugh', 5);

        $this->assertEquals('updated', $result['action']);
        $this->assertEquals('laugh', $result['reaction_type']);
    }

    public function test_toggleReaction_throws_for_invalid_entity_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entity type: invalid');

        $this->service->toggleReaction(1, 'invalid', 'love', 5);
    }

    public function test_toggleReaction_works_for_comment_entity(): void
    {
        DB::shouldReceive('table->where->where->where->where->first')->once()->andReturn(null);
        DB::shouldReceive('table->insert')->once();
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()->andReturn(collect([(object) ['emoji' => 'clap', 'count' => 1]]));
        DB::shouldReceive('table->where->where->where->where->first')
            ->once()->andReturn((object) ['emoji' => 'clap']);
        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()->andReturn([]);

        $result = $this->service->toggleReaction(10, 'comment', 'clap', 5);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals('clap', $result['reaction_type']);
    }

    // ======================================================================
    //  getReactions
    // ======================================================================

    public function test_getReactions_returns_counts_and_user_reaction(): void
    {
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([
                (object) ['emoji' => 'love', 'count' => 3],
                (object) ['emoji' => 'laugh', 'count' => 1],
            ]));

        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn((object) ['emoji' => 'love']);

        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'User One', 'avatar_url' => null],
                ['id' => 2, 'name' => 'User Two', 'avatar_url' => null],
            ]);

        $result = $this->service->getReactions(1, 'post', 5);

        $this->assertEquals(['love' => 3, 'laugh' => 1], $result['counts']);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals('love', $result['user_reaction']);
        $this->assertCount(2, $result['top_reactors']);
    }

    public function test_getReactions_returns_null_user_reaction_when_no_user(): void
    {
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([(object) ['emoji' => 'love', 'count' => 2]]));

        // No user query when userId is null
        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([]);

        $result = $this->service->getReactions(1, 'post', null);

        $this->assertEquals(['love' => 2], $result['counts']);
        $this->assertEquals(2, $result['total']);
        $this->assertNull($result['user_reaction']);
    }

    public function test_getReactions_returns_empty_when_no_reactions(): void
    {
        DB::shouldReceive('table->where->where->where->select->groupBy->get')
            ->once()
            ->andReturn(collect([]));

        DB::shouldReceive('table->where->where->where->where->first')
            ->once()
            ->andReturn(null);

        DB::shouldReceive('table->join->where->where->where->orderByDesc->limit->select->get->map->all')
            ->once()
            ->andReturn([]);

        $result = $this->service->getReactions(1, 'post', 5);

        $this->assertEquals([], $result['counts']);
        $this->assertEquals(0, $result['total']);
        $this->assertNull($result['user_reaction']);
        $this->assertEmpty($result['top_reactors']);
    }

    public function test_getReactions_throws_for_invalid_entity_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entity type: message');

        $this->service->getReactions(1, 'message', 5);
    }

    // ======================================================================
    //  getReactors
    // ======================================================================

    public function test_getReactors_returns_paginated_users(): void
    {
        // count() — 5 where calls: join + 4 where
        DB::shouldReceive('table->join->where->where->where->where->count')
            ->once()
            ->andReturn(5);

        // paginated results — clone adds orderByDesc, offset, limit, select, get->map->all
        DB::shouldReceive('table->join->where->where->where->where->orderByDesc->offset->limit->select->get->map->all')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'User A', 'avatar_url' => null, 'reacted_at' => '2026-03-23 10:00:00'],
                ['id' => 2, 'name' => 'User B', 'avatar_url' => null, 'reacted_at' => '2026-03-23 09:00:00'],
            ]);

        $result = $this->service->getReactors(1, 'post', 'love', 1, 2);

        $this->assertCount(2, $result['users']);
        $this->assertEquals(5, $result['total']);
        $this->assertTrue($result['has_more']);
    }

    public function test_getReactors_returns_no_more_when_last_page(): void
    {
        DB::shouldReceive('table->join->where->where->where->where->count')
            ->once()
            ->andReturn(2);

        DB::shouldReceive('table->join->where->where->where->where->orderByDesc->offset->limit->select->get->map->all')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'User A', 'avatar_url' => null, 'reacted_at' => '2026-03-23 10:00:00'],
                ['id' => 2, 'name' => 'User B', 'avatar_url' => null, 'reacted_at' => '2026-03-23 09:00:00'],
            ]);

        $result = $this->service->getReactors(1, 'post', 'love', 1, 20);

        $this->assertFalse($result['has_more']);
        $this->assertEquals(2, $result['total']);
    }

    public function test_getReactors_returns_empty_for_no_reactions(): void
    {
        DB::shouldReceive('table->join->where->where->where->where->count')
            ->once()
            ->andReturn(0);

        DB::shouldReceive('table->join->where->where->where->where->orderByDesc->offset->limit->select->get->map->all')
            ->once()
            ->andReturn([]);

        $result = $this->service->getReactors(1, 'post', 'love', 1, 20);

        $this->assertEmpty($result['users']);
        $this->assertEquals(0, $result['total']);
        $this->assertFalse($result['has_more']);
    }

    public function test_getReactors_throws_for_invalid_entity_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid entity type: photo');

        $this->service->getReactors(1, 'photo', 'love');
    }

    // ======================================================================
    //  getReactionsForPosts (batch) — uses DB::select() with raw SQL
    // ======================================================================

    public function test_getReactionsForPosts_returns_empty_for_empty_input(): void
    {
        $result = $this->service->getReactionsForPosts([], 5);
        $this->assertEquals([], $result);
    }

    public function test_getReactionsForPosts_returns_grouped_counts(): void
    {
        // Counts query
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['target_id' => 1, 'emoji' => 'love', 'count' => 3],
                (object) ['target_id' => 1, 'emoji' => 'laugh', 'count' => 1],
                (object) ['target_id' => 2, 'emoji' => 'wow', 'count' => 2],
            ]);

        // User reactions query
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['target_id' => 1, 'emoji' => 'love'],
            ]);

        $result = $this->service->getReactionsForPosts([1, 2, 3], 5);

        $this->assertEquals(['love' => 3, 'laugh' => 1], $result[1]['counts']);
        $this->assertEquals(4, $result[1]['total']);
        $this->assertEquals('love', $result[1]['user_reaction']);

        $this->assertEquals(['wow' => 2], $result[2]['counts']);
        $this->assertEquals(2, $result[2]['total']);
        $this->assertNull($result[2]['user_reaction']);

        $this->assertEquals([], $result[3]['counts']);
        $this->assertEquals(0, $result[3]['total']);
        $this->assertNull($result[3]['user_reaction']);
    }

    public function test_getReactionsForPosts_without_user_id(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([
                (object) ['target_id' => 10, 'emoji' => 'clap', 'count' => 5],
            ]);

        $result = $this->service->getReactionsForPosts([10], null);

        $this->assertEquals(['clap' => 5], $result[10]['counts']);
        $this->assertEquals(5, $result[10]['total']);
        $this->assertNull($result[10]['user_reaction']);
    }

    public function test_getReactionsForPosts_initialises_all_post_ids(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andReturn([]);

        $result = $this->service->getReactionsForPosts([100, 200, 300], null);

        $this->assertArrayHasKey(100, $result);
        $this->assertArrayHasKey(200, $result);
        $this->assertArrayHasKey(300, $result);

        foreach ([100, 200, 300] as $pid) {
            $this->assertEquals([], $result[$pid]['counts']);
            $this->assertEquals(0, $result[$pid]['total']);
            $this->assertNull($result[$pid]['user_reaction']);
        }
    }
}
