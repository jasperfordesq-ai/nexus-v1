<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CommentService;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Mockery;

class CommentServiceTest extends TestCase
{
    public function test_countAll_counts_nested_comments(): void
    {
        $comments = [
            ['replies' => [
                ['replies' => []],
                ['replies' => [
                    ['replies' => []],
                ]],
            ]],
            ['replies' => []],
        ];

        $this->assertSame(5, CommentService::countAll($comments));
    }

    public function test_countAll_returns_zero_for_empty_array(): void
    {
        $this->assertSame(0, CommentService::countAll([]));
    }

    public function test_getAvailableReactions_returns_array(): void
    {
        $reactions = CommentService::getAvailableReactions();
        $this->assertIsArray($reactions);
        $this->assertNotEmpty($reactions);
    }

    public function test_getErrors_returns_empty_array(): void
    {
        $this->assertSame([], CommentService::getErrors());
    }

    public function test_create_creates_comment(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_update_returns_false_when_not_owner(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_false_when_not_owner(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_addComment_returns_error_when_content_empty(): void
    {
        $result = CommentService::addComment(1, 2, 'post', 1, '   ');
        $this->assertFalse($result['success']);
        $this->assertSame('Comment cannot be empty', $result['error']);
    }

    public function test_addComment_returns_error_when_parent_not_found(): void
    {
        DB::shouldReceive('table')->with('comments')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $result = CommentService::addComment(1, 2, 'post', 1, 'Reply', 999);
        $this->assertFalse($result['success']);
        $this->assertSame('Parent comment not found', $result['error']);
    }

    public function test_deleteComment_returns_error_when_not_found(): void
    {
        DB::shouldReceive('table')->with('comments')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = CommentService::deleteComment(999, 1);
        $this->assertFalse($result['success']);
    }

    public function test_deleteComment_returns_error_when_unauthorized(): void
    {
        DB::shouldReceive('table')->with('comments')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'user_id' => 5]);

        $result = CommentService::deleteComment(1, 999);
        $this->assertFalse($result['success']);
        $this->assertSame('Unauthorized', $result['error']);
    }

    public function test_editComment_returns_error_when_content_empty(): void
    {
        $result = CommentService::editComment(1, 1, '  ');
        $this->assertFalse($result['success']);
    }

    public function test_toggleReaction_adds_reaction(): void
    {
        // toggleReaction now uses the unified 'reactions' table
        DB::shouldReceive('table')->with('reactions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();
        DB::shouldReceive('insert')->once();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('all')->andReturn([]);

        $result = CommentService::toggleReaction(1, 2, 1, '👍');
        $this->assertSame('added', $result['action']);
    }

    public function test_searchUsersForMention_returns_array(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = CommentService::searchUsersForMention('john', 2);
        $this->assertIsArray($result);
    }
}
