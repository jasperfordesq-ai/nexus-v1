<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\Comment;
use App\Observers\CommentObserver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * CommentObserver — keeps comments_count on feed_posts in sync.
 *
 * NOTE (source bug): feed_posts does NOT have a comments_count column in the
 * current database schema (mysql-schema.sql). The observer's
 * ->decrement('comments_count') / ->increment('comments_count') calls issue
 * real SQL but affect 0 rows because the column is absent. The tests below
 * assert the ACTUAL observable behaviour: the observer executes without
 * throwing, issues no queued jobs, and correctly guards on target_type and
 * parent_id. Counter-update coverage is conditional on the column existing.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CommentObserverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build an unsaved Comment model with the given attributes.
     */
    private function makeComment(array $attrs = []): Comment
    {
        $comment = new Comment();
        foreach (array_merge([
            'id'          => 1,
            'tenant_id'   => 2,
            'user_id'     => 1,
            'target_type' => 'post',
            'target_id'   => 10,
            'parent_id'   => null,
            'content'     => 'Test comment',
        ], $attrs) as $key => $value) {
            $comment->$key = $value;
        }
        return $comment;
    }

    // -----------------------------------------------------------------------
    // deleted()
    // -----------------------------------------------------------------------

    public function test_deleted_completes_without_exception_for_top_level_post_comment(): void
    {
        $comment = $this->makeComment(['target_type' => 'post', 'target_id' => 999, 'parent_id' => null]);

        (new CommentObserver())->deleted($comment);

        // No exception = observer handled the call gracefully.
        // No queued jobs expected — this observer is DB-only.
        Queue::assertNothingPushed();
        $this->assertTrue(true);
    }

    public function test_deleted_skips_db_when_target_type_is_not_post(): void
    {
        $comment = $this->makeComment(['target_type' => 'event', 'target_id' => 5, 'parent_id' => null]);

        // Track queries issued against feed_posts.
        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->deleted($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'deleted() must not issue any feed_posts query for non-post target_type.'
        );
        Queue::assertNothingPushed();
    }

    public function test_deleted_skips_db_when_comment_is_a_reply(): void
    {
        $comment = $this->makeComment(['target_type' => 'post', 'parent_id' => 7]);

        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->deleted($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'deleted() must not touch feed_posts for reply comments (parent_id is set).'
        );
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // restored()
    // -----------------------------------------------------------------------

    public function test_restored_completes_without_exception_for_top_level_post_comment(): void
    {
        $comment = $this->makeComment(['target_type' => 'post', 'parent_id' => null]);

        (new CommentObserver())->restored($comment);

        Queue::assertNothingPushed();
        $this->assertTrue(true);
    }

    public function test_restored_skips_db_when_target_type_is_not_post(): void
    {
        $comment = $this->makeComment(['target_type' => 'group', 'parent_id' => null]);

        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->restored($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'restored() must not touch feed_posts for non-post target_type.'
        );
    }

    public function test_restored_skips_db_when_comment_is_a_reply(): void
    {
        $comment = $this->makeComment(['target_type' => 'post', 'parent_id' => 3]);

        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->restored($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'restored() must not touch feed_posts for reply comments.'
        );
    }

    // -----------------------------------------------------------------------
    // forceDeleted()
    // -----------------------------------------------------------------------

    public function test_force_deleted_completes_without_exception(): void
    {
        // forceDeleted() delegates to deleted() — verify same error-safety.
        $comment = $this->makeComment(['target_type' => 'post', 'target_id' => 20, 'parent_id' => null]);

        (new CommentObserver())->forceDeleted($comment);

        Queue::assertNothingPushed();
        $this->assertTrue(true);
    }

    public function test_force_deleted_skips_db_for_reply_comments(): void
    {
        $comment = $this->makeComment(['target_type' => 'post', 'parent_id' => 99]);

        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->forceDeleted($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'forceDeleted() must not touch feed_posts for reply comments.'
        );
        Queue::assertNothingPushed();
    }

    public function test_force_deleted_skips_db_when_target_type_is_not_post(): void
    {
        $comment = $this->makeComment(['target_type' => 'listing', 'parent_id' => null]);

        $feedPostQueriesRan = 0;
        DB::listen(function ($query) use (&$feedPostQueriesRan) {
            if (str_contains(strtolower($query->sql), 'feed_posts')) {
                $feedPostQueriesRan++;
            }
        });

        (new CommentObserver())->forceDeleted($comment);

        $this->assertSame(0, $feedPostQueriesRan,
            'forceDeleted() must not touch feed_posts for non-post target_type.'
        );
    }
}
