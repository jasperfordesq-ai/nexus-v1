<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Observers;

use App\Models\FeedPost;
use App\Observers\FeedPostObserver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * FeedPostObserver — keeps feed_activity visibility in sync with post soft-delete lifecycle.
 *
 * Tests are DB-backed (DatabaseTransactions) because the observer's entire job is
 * to update the feed_activity table — there are no queued jobs to assert against.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FeedPostObserverTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Insert a feed_activity row referencing a fake post and return the row id.
     * Uses a high fake post_id (9_000_000+) to avoid FK clashes during tests.
     */
    private function insertFeedActivity(int $postId, bool $isVisible = true): int
    {
        // feed_activity.user_id has no FK constraint enforced in test env —
        // use a plausible value that won't violate NOT NULL.
        DB::table('feed_activity')->insert([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => 1,
            'source_type' => 'post',
            'source_id'   => $postId,
            'title'       => 'Test post',
            'is_visible'  => $isVisible ? 1 : 0,
            'created_at'  => now()->toDateTimeString(),
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * Build an unsaved FeedPost model with given attributes.
     */
    private function makePost(array $attrs = []): FeedPost
    {
        $post = new FeedPost();
        foreach (array_merge([
            'id'        => 9_000_001,
            'tenant_id' => self::TENANT_ID,
            'user_id'   => 1,
            'content'   => 'Hello world',
            'is_hidden' => 0,
        ], $attrs) as $key => $value) {
            $post->$key = $value;
        }
        return $post;
    }

    // -----------------------------------------------------------------------
    // deleted()
    // -----------------------------------------------------------------------

    public function test_deleted_hides_feed_activity_for_the_post(): void
    {
        $postId = 9_000_010;
        $this->insertFeedActivity($postId, isVisible: true);

        $post = $this->makePost(['id' => $postId]);
        (new FeedPostObserver())->deleted($post);

        $row = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $postId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertNotNull($row, 'feed_activity row must still exist after soft-delete (not hard-deleted).');
        $this->assertSame(0, (int) $row->is_visible, 'deleted() must set is_visible=false on the feed_activity row.');
        Queue::assertNothingPushed();
    }

    public function test_deleted_only_hides_rows_for_the_correct_tenant(): void
    {
        $postId = 9_000_011;
        // Row belonging to THIS tenant — should be hidden.
        $this->insertFeedActivity($postId, isVisible: true);

        // Row for a different post — must NOT be touched.
        $otherPostId = 9_000_012;
        $this->insertFeedActivity($otherPostId, isVisible: true);

        $post = $this->makePost(['id' => $postId]);
        (new FeedPostObserver())->deleted($post);

        $otherRow = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $otherPostId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertSame(1, (int) $otherRow->is_visible,
            'deleted() must not touch feed_activity rows for other posts.'
        );
    }

    public function test_deleted_does_not_throw_when_no_feed_activity_row_exists(): void
    {
        $post = $this->makePost(['id' => 9_000_099]);

        // Should complete silently — no feed_activity row for this post.
        (new FeedPostObserver())->deleted($post);

        $this->assertTrue(true, 'deleted() must not throw when no feed_activity row exists.');
        Queue::assertNothingPushed();
    }

    // -----------------------------------------------------------------------
    // restored()
    // -----------------------------------------------------------------------

    public function test_restored_makes_feed_activity_visible_again(): void
    {
        $postId = 9_000_020;
        $this->insertFeedActivity($postId, isVisible: false);

        $post = $this->makePost(['id' => $postId, 'is_hidden' => 0]);
        (new FeedPostObserver())->restored($post);

        $row = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $postId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertSame(1, (int) $row->is_visible,
            'restored() must set is_visible=true when the post is not moderation-hidden.'
        );
        Queue::assertNothingPushed();
    }

    public function test_restored_skips_visibility_restore_for_moderation_hidden_post(): void
    {
        $postId = 9_000_021;
        $this->insertFeedActivity($postId, isVisible: false);

        // is_hidden = 1 means moderation hid the post — un-deleting must not bypass that.
        $post = $this->makePost(['id' => $postId, 'is_hidden' => 1]);
        (new FeedPostObserver())->restored($post);

        $row = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $postId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertSame(0, (int) $row->is_visible,
            'restored() must not restore visibility when post is moderation-hidden (is_hidden=1).'
        );
    }

    // -----------------------------------------------------------------------
    // forceDeleted()
    // -----------------------------------------------------------------------

    public function test_force_deleted_permanently_removes_feed_activity_rows(): void
    {
        $postId = 9_000_030;
        $this->insertFeedActivity($postId, isVisible: true);

        $post = $this->makePost(['id' => $postId]);
        (new FeedPostObserver())->forceDeleted($post);

        $row = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $postId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertNull($row,
            'forceDeleted() must permanently DELETE feed_activity rows (not merely hide them).'
        );
        Queue::assertNothingPushed();
    }

    public function test_force_deleted_does_not_remove_activity_for_other_posts(): void
    {
        $postId = 9_000_031;
        $otherPostId = 9_000_032;
        $this->insertFeedActivity($postId, isVisible: true);
        $this->insertFeedActivity($otherPostId, isVisible: true);

        $post = $this->makePost(['id' => $postId]);
        (new FeedPostObserver())->forceDeleted($post);

        $otherRow = DB::table('feed_activity')
            ->where('source_type', 'post')
            ->where('source_id', $otherPostId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertNotNull($otherRow,
            'forceDeleted() must only delete feed_activity for the target post, not other posts.'
        );
    }
}
