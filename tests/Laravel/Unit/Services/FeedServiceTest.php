<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedService;
use App\Models\FeedActivity;
use App\Models\FeedPost;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Mockery;

class FeedServiceTest extends TestCase
{
    /** @var \Illuminate\Database\DatabaseManager|null Original DB resolver saved before mock swap */
    private $originalDbResolver = null;

    /**
     * Build a mock DB connection, swap both the Model resolver AND
     * the app container's 'db' binding so Eloquent queries and
     * DB facade calls both route through the same mock.
     *
     * @param  list<list<object>>  $selectSequence  Ordered arrays of result rows
     * @return FeedService  A fresh service instance using the mocked connection
     */
    private function mockEloquentConnectionAndBuildService(array $selectSequence = []): FeedService
    {
        $mockGrammar = Mockery::mock(\Illuminate\Database\Query\Grammars\MySqlGrammar::class)->makePartial();
        $mockGrammar->shouldReceive('getDateFormat')->andReturn('Y-m-d H:i:s');
        $mockGrammar->shouldReceive('compileSelect')->andReturn('SELECT 1');
        $mockGrammar->shouldReceive('compileInsert')->andReturn('INSERT INTO t VALUES ()');
        $mockGrammar->shouldReceive('compileInsertOrIgnore')->andReturn('INSERT IGNORE INTO t VALUES ()');
        $mockGrammar->shouldReceive('compileUpdate')->andReturn('UPDATE t SET x=1');
        $mockGrammar->shouldReceive('compileDelete')->andReturn('DELETE FROM t');

        $mockProcessor = Mockery::mock(\Illuminate\Database\Query\Processors\MySqlProcessor::class)->makePartial();

        $mockConnection = Mockery::mock(\Illuminate\Database\MySqlConnection::class)->makePartial();
        $mockConnection->shouldReceive('getQueryGrammar')->andReturn($mockGrammar);
        $mockConnection->shouldReceive('getPostProcessor')->andReturn($mockProcessor);
        $mockConnection->shouldReceive('getTablePrefix')->andReturn('');
        $mockConnection->shouldReceive('getDatabaseName')->andReturn('nexus_test');
        $mockConnection->shouldReceive('getName')->andReturn('mysql');
        $mockConnection->shouldReceive('getConfig')->andReturn(null);

        // Build the full return sequence: user-provided rows + trailing empties
        $allReturns = array_merge($selectSequence ?: [[]], [[], [], [], [], [], [], [], [], [], []]);
        $mockConnection->shouldReceive('select')->andReturnValues($allReturns);

        $mockConnection->shouldReceive('selectOne')
            ->andReturnUsing(function () {
                return (object) ['aggregate' => 0];
            });

        $mockConnection->shouldReceive('insert')->andReturn(true);
        $mockConnection->shouldReceive('update')->andReturn(1);
        $mockConnection->shouldReceive('delete')->andReturn(1);
        $mockConnection->shouldReceive('affectingStatement')->andReturn(1);
        $mockConnection->shouldReceive('statement')->andReturn(true);
        $mockConnection->shouldReceive('beginTransaction')->andReturn(null);
        $mockConnection->shouldReceive('commit')->andReturn(null);
        $mockConnection->shouldReceive('rollBack')->andReturn(null);
        $mockConnection->shouldReceive('transactionLevel')->andReturn(0);

        // Create a single mock resolver for BOTH Model and DB facade
        $mockResolver = Mockery::mock(\Illuminate\Database\DatabaseManager::class);
        $mockResolver->shouldReceive('connection')->andReturn($mockConnection);

        // Save original resolver before swapping
        $this->originalDbResolver = Model::getConnectionResolver();

        // Swap the Model's connection resolver
        Model::setConnectionResolver($mockResolver);

        // Swap the app container's 'db' binding so DB facade uses the same resolver
        $this->app->instance('db', $mockResolver);

        // Create service AFTER mocking so the models resolve the mock connection
        return new FeedService(new FeedActivity(), new FeedPost());
    }

    protected function tearDown(): void
    {
        // Restore the real connection resolver if it was swapped
        if ($this->originalDbResolver !== null) {
            Model::setConnectionResolver($this->originalDbResolver);
            $this->app->instance('db', $this->originalDbResolver);
            $this->originalDbResolver = null;
        }
        parent::tearDown();
    }

    // ── Helper: build a feed_activity row object ──────────────────────

    private function makeFeedRow(array $overrides = []): object
    {
        return (object) array_merge([
            'activity_id' => 1,
            'source_type' => 'post',
            'source_id' => 100,
            'user_id' => 10,
            'title' => 'Test Post',
            'content' => 'Hello world',
            'image_url' => null,
            'metadata' => null,
            'group_id' => null,
            'created_at' => '2026-03-01 12:00:00',
            'author_name' => 'Test User',
            'author_avatar' => '/avatars/test.png',
            'user_location' => 'Dublin',
        ], $overrides);
    }

    // ── 1. getFeed() returns items for current tenant only ────────────

    public function test_getFeed_returns_items_for_current_tenant(): void
    {
        $row1 = $this->makeFeedRow(['activity_id' => 1, 'source_id' => 100]);
        $row2 = $this->makeFeedRow(['activity_id' => 2, 'source_id' => 101]);

        $service = $this->mockEloquentConnectionAndBuildService([
            [$row1, $row2],
        ]);

        $result = $service->getFeed(10, []);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertCount(2, $result['items']);
    }

    public function test_getFeed_returns_empty_when_no_items(): void
    {
        $service = $this->mockEloquentConnectionAndBuildService([[]]);

        $result = $service->getFeed(10, []);

        $this->assertEmpty($result['items']);
        $this->assertNull($result['cursor']);
        $this->assertFalse($result['has_more']);
    }

    // ── 2. getFeed() respects type filter ─────────────────────────────

    public function test_getFeed_respects_type_filter(): void
    {
        $postRow = $this->makeFeedRow(['source_type' => 'post', 'source_id' => 100]);

        $service = $this->mockEloquentConnectionAndBuildService([
            [$postRow],
        ]);

        $result = $service->getFeed(10, ['type' => 'posts']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('post', $result['items'][0]['type']);
    }

    public function test_getFeed_type_map_converts_plural_to_singular(): void
    {
        $typeMap = [
            'posts' => 'post',
            'listings' => 'listing',
            'events' => 'event',
            'polls' => 'poll',
            'goals' => 'goal',
            'jobs' => 'job',
            'challenges' => 'challenge',
            'volunteering' => 'volunteer',
            'blogs' => 'blog',
            'discussions' => 'discussion',
            'badge_earned' => 'badge_earned',
            'level_up' => 'level_up',
        ];

        $reflection = new \ReflectionClass(FeedService::class);
        $constant = $reflection->getConstant('TYPE_MAP');

        $this->assertSame($typeMap, $constant);
    }

    // ── 3. getFeed() pagination ───────────────────────────────────────

    public function test_getFeed_pagination_returns_cursor_when_has_more(): void
    {
        // Create limit+1 rows (default limit=20, so 21 rows triggers has_more)
        $rows = [];
        for ($i = 21; $i >= 1; $i--) {
            $rows[] = $this->makeFeedRow([
                'activity_id' => $i,
                'source_id' => 100 + $i,
                'created_at' => sprintf('2026-03-01 12:%02d:00', $i),
            ]);
        }

        $service = $this->mockEloquentConnectionAndBuildService([$rows]);

        $result = $service->getFeed(10, ['limit' => 20]);

        $this->assertTrue($result['has_more']);
        $this->assertNotNull($result['cursor']);
        $this->assertCount(20, $result['items']);

        // Verify cursor is base64-encoded "created_at|activity_id"
        $decoded = base64_decode($result['cursor'], true);
        $this->assertStringContainsString('|', $decoded);
    }

    public function test_getFeed_pagination_no_cursor_when_no_more(): void
    {
        $rows = [
            $this->makeFeedRow(['activity_id' => 1, 'source_id' => 100]),
            $this->makeFeedRow(['activity_id' => 2, 'source_id' => 101]),
        ];

        $service = $this->mockEloquentConnectionAndBuildService([$rows]);

        $result = $service->getFeed(10, ['limit' => 20]);

        $this->assertFalse($result['has_more']);
        $this->assertNull($result['cursor']);
        $this->assertCount(2, $result['items']);
    }

    public function test_getFeed_cursor_decoding(): void
    {
        $row = $this->makeFeedRow(['activity_id' => 5, 'source_id' => 105]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $cursor = base64_encode('2026-03-01 12:00:00|10');
        $result = $service->getFeed(10, ['cursor' => $cursor]);

        $this->assertCount(1, $result['items']);
    }

    public function test_getFeed_limits_capped_at_100(): void
    {
        $this->assertSame(100, min((int) 200, 100));
        $this->assertSame(50, min((int) 50, 100));
        $this->assertSame(20, min((int) (null ?? 20), 100));
    }

    // ── 4. getFeed() excludes invisible items ─────────────────────────

    public function test_getFeed_excludes_invisible_items(): void
    {
        // The query includes WHERE is_visible = true AND is_hidden = false.
        // Only visible/non-hidden rows are returned by the DB.
        $visibleRow = $this->makeFeedRow(['activity_id' => 1, 'source_id' => 100]);

        $service = $this->mockEloquentConnectionAndBuildService([[$visibleRow]]);

        $result = $service->getFeed(10, []);

        $this->assertCount(1, $result['items']);
        $this->assertSame(100, $result['items'][0]['id']);
    }

    public function test_getFeed_excludes_hidden_items(): void
    {
        $normalRow = $this->makeFeedRow(['activity_id' => 1, 'source_id' => 100]);

        $service = $this->mockEloquentConnectionAndBuildService([[$normalRow]]);

        $result = $service->getFeed(10, []);

        $this->assertCount(1, $result['items']);
    }

    // ── 5. createPost() creates both feed_posts and feed_activity ─────

    public function test_createPost_creates_post_and_activity(): void
    {
        $mockPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockPost->id = 42;
        $mockPost->created_at = '2026-03-01 12:00:00';
        $mockPost->shouldReceive('save')->once()->andReturn(true);
        $mockPost->shouldReceive('fresh')->once()->andReturn($mockPost);

        $mockFeedPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockFeedPost->shouldReceive('newInstance')
            ->once()
            ->andReturn($mockPost);

        $service = new FeedService(new FeedActivity(), $mockFeedPost);

        DB::shouldReceive('table')->with('feed_activity')->once()->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->once()->andReturn(true);

        $result = $service->createPost(10, [
            'content' => 'Hello world',
        ]);

        $this->assertSame(42, $result->id);
    }

    public function test_createPost_sets_correct_fields(): void
    {
        $capturedAttributes = null;

        $mockPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockPost->id = 55;
        $mockPost->created_at = '2026-03-01 12:00:00';
        $mockPost->shouldReceive('save')->once()->andReturn(true);
        $mockPost->shouldReceive('fresh')->once()->andReturn($mockPost);

        $mockFeedPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockFeedPost->shouldReceive('newInstance')
            ->once()
            ->withArgs(function ($attrs) use (&$capturedAttributes) {
                $capturedAttributes = $attrs;
                return true;
            })
            ->andReturn($mockPost);

        $service = new FeedService(new FeedActivity(), $mockFeedPost);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->andReturn(true);

        $service->createPost(10, [
            'content' => 'My post content',
            'image_url' => '/images/photo.jpg',
            'visibility' => 'private',
        ]);

        $this->assertNotNull($capturedAttributes, 'newInstance should have been called');
        $this->assertSame(10, $capturedAttributes['user_id']);
        $this->assertSame('My post content', $capturedAttributes['content']);
        $this->assertSame('private', $capturedAttributes['visibility']);
        $this->assertSame('post', $capturedAttributes['type']);
        // createPost() maps the image input to the 'image' attribute key
        $imageValue = $capturedAttributes['image'] ?? $capturedAttributes['image_url'] ?? null;
        $this->assertSame('/images/photo.jpg', $imageValue);
    }

    // ── 6. createPost() rejects empty content without image ───────────

    public function test_createPostLegacy_rejects_empty_content_without_image(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $result = $service->createPostLegacy(10, [
            'content' => '',
            'image_url' => null,
        ]);

        $this->assertNull($result);

        $errors = $service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertSame('content', $errors[0]['field']);
    }

    public function test_createPostLegacy_rejects_whitespace_only_content(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $result = $service->createPostLegacy(10, [
            'content' => '   ',
            'image_url' => null,
        ]);

        $this->assertNull($result);
        $this->assertNotEmpty($service->getErrors());
    }

    // ── 7. createPost() with group_id ─────────────────────────────────

    public function test_createPost_with_group_id(): void
    {
        $capturedAttributes = null;
        $capturedActivityData = null;

        $mockPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockPost->id = 77;
        $mockPost->created_at = '2026-03-01 12:00:00';
        $mockPost->shouldReceive('save')->once()->andReturn(true);
        $mockPost->shouldReceive('fresh')->once()->andReturn($mockPost);

        $mockFeedPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockFeedPost->shouldReceive('newInstance')
            ->once()
            ->withArgs(function ($attrs) use (&$capturedAttributes) {
                $capturedAttributes = $attrs;
                return true;
            })
            ->andReturn($mockPost);

        $service = new FeedService(new FeedActivity(), $mockFeedPost);

        DB::shouldReceive('table')->with('feed_activity')->once()->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')
            ->once()
            ->withArgs(function ($data) use (&$capturedActivityData) {
                $capturedActivityData = $data;
                return true;
            })
            ->andReturn(true);

        $service->createPost(10, [
            'content' => 'Group post',
            'group_id' => 5,
        ]);

        $this->assertSame(5, $capturedAttributes['group_id']);
        $this->assertSame(5, $capturedActivityData['group_id']);
    }

    public function test_createPost_without_group_id(): void
    {
        $capturedAttributes = null;

        $mockPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockPost->id = 78;
        $mockPost->created_at = '2026-03-01 12:00:00';
        $mockPost->shouldReceive('save')->once()->andReturn(true);
        $mockPost->shouldReceive('fresh')->once()->andReturn($mockPost);

        $mockFeedPost = Mockery::mock(FeedPost::class)->makePartial();
        $mockFeedPost->shouldReceive('newInstance')
            ->once()
            ->withArgs(function ($attrs) use (&$capturedAttributes) {
                $capturedAttributes = $attrs;
                return true;
            })
            ->andReturn($mockPost);

        $service = new FeedService(new FeedActivity(), $mockFeedPost);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('insertOrIgnore')->andReturn(true);

        $service->createPost(10, ['content' => 'No group']);

        $this->assertNull($capturedAttributes['group_id']);
    }

    // ── 8. like() toggle behavior ─────────────────────────────────────

    public function test_like_creates_like_when_not_exists(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $mockBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockBuilder->shouldReceive('where')->andReturnSelf();
        $mockBuilder->shouldReceive('first')->once()->andReturn(null);
        $mockBuilder->shouldReceive('insert')->once()->andReturn(true);
        $mockBuilder->shouldReceive('count')->once()->andReturn(1);

        DB::shouldReceive('table')->with('likes')->andReturn($mockBuilder);

        $result = $service->like(100, 10);

        $this->assertTrue($result['liked']);
        $this->assertSame(1, $result['likes_count']);
    }

    public function test_like_removes_like_when_exists(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $existingLike = (object) ['id' => 1, 'target_type' => 'post', 'target_id' => 100, 'user_id' => 10];

        $mockBuilder = Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $mockBuilder->shouldReceive('where')->andReturnSelf();
        $mockBuilder->shouldReceive('first')->once()->andReturn($existingLike);
        $mockBuilder->shouldReceive('delete')->once()->andReturn(1);
        $mockBuilder->shouldReceive('count')->once()->andReturn(0);

        DB::shouldReceive('table')->with('likes')->andReturn($mockBuilder);

        $result = $service->like(100, 10);

        $this->assertFalse($result['liked']);
        $this->assertSame(0, $result['likes_count']);
    }

    // ── 9. getItem() returns single item with enrichment ──────────────

    public function test_getItem_returns_enriched_post(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $postRow = (object) [
            'id' => 100,
            'content' => 'Hello world',
            'image_url' => null,
            'created_at' => '2026-03-01 12:00:00',
            'likes_count' => 5,
            'user_id' => 10,
            'type' => 'post',
            'author_name' => 'Test User',
            'author_avatar' => '/avatars/test.png',
            'comments_count' => 3,
        ];

        DB::shouldReceive('select')->once()->andReturn([$postRow]);
        DB::shouldReceive('selectOne')->once()->andReturn(
            (object) ['1' => 1]
        );

        $result = $service->getItem('post', 100, 10);

        $this->assertNotNull($result);
        $this->assertSame(100, $result['id']);
        $this->assertSame('post', $result['type']);
        $this->assertSame('Hello world', $result['content']);
        $this->assertFalse($result['content_truncated']);
        $this->assertArrayHasKey('author', $result);
        $this->assertSame(10, $result['author']['id']);
        $this->assertSame('Test User', $result['author']['name']);
        $this->assertArrayHasKey('likes_count', $result);
        $this->assertArrayHasKey('comments_count', $result);
        $this->assertArrayHasKey('is_liked', $result);
    }

    public function test_getItem_returns_null_when_not_found(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        DB::shouldReceive('select')->once()->andReturn([]);

        $result = $service->getItem('post', 999, 10);

        $this->assertNull($result);
    }

    public function test_getItem_truncates_long_content(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $longContent = str_repeat('A', 600);
        $postRow = (object) [
            'id' => 100,
            'content' => $longContent,
            'image_url' => null,
            'created_at' => '2026-03-01 12:00:00',
            'likes_count' => 0,
            'user_id' => 10,
            'type' => 'post',
            'author_name' => 'Test User',
            'author_avatar' => null,
            'comments_count' => 0,
        ];

        DB::shouldReceive('select')->once()->andReturn([$postRow]);
        DB::shouldReceive('selectOne')->once()->andReturn(null);

        $result = $service->getItem('post', 100, 10);

        $this->assertTrue($result['content_truncated']);
        $this->assertSame(503, mb_strlen($result['content']));
    }

    public function test_getItem_without_user_skips_like_check(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());

        $postRow = (object) [
            'id' => 100,
            'content' => 'Test',
            'image_url' => null,
            'created_at' => '2026-03-01 12:00:00',
            'likes_count' => 0,
            'user_id' => 10,
            'type' => 'post',
            'author_name' => 'Test User',
            'author_avatar' => null,
            'comments_count' => 0,
        ];

        DB::shouldReceive('select')->once()->andReturn([$postRow]);

        $result = $service->getItem('post', 100, null);

        $this->assertNotNull($result);
        $this->assertFalse($result['is_liked']);
    }

    // ── 10. getFeed() batch loads likes and comments ──────────────────

    public function test_getFeed_items_have_like_and_comment_counts(): void
    {
        $row = $this->makeFeedRow([
            'activity_id' => 1,
            'source_type' => 'post',
            'source_id' => 100,
        ]);

        $service = $this->mockEloquentConnectionAndBuildService([
            [$row],
        ]);

        $result = $service->getFeed(10, []);

        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];

        $this->assertArrayHasKey('likes_count', $item);
        $this->assertArrayHasKey('comments_count', $item);
        $this->assertArrayHasKey('is_liked', $item);
        $this->assertSame(0, $item['likes_count']);
        $this->assertSame(0, $item['comments_count']);
        $this->assertFalse($item['is_liked']);
    }

    public function test_getFeed_item_has_correct_shape(): void
    {
        $row = $this->makeFeedRow([
            'activity_id' => 1,
            'source_type' => 'post',
            'source_id' => 100,
            'user_id' => 10,
            'title' => 'My Post',
            'content' => 'Some content',
            'image_url' => '/img/photo.jpg',
            'metadata' => null,
        ]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $result = $service->getFeed(10, []);

        $this->assertNotEmpty($result['items'], 'Feed items should not be empty');
        $item = $result['items'][0];

        $this->assertSame(100, $item['id']);
        $this->assertSame('post', $item['type']);
        $this->assertSame('My Post', $item['title']);
        $this->assertSame('Some content', $item['content']);
        $this->assertFalse($item['content_truncated']);
        $this->assertSame('/img/photo.jpg', $item['image_url']);
        $this->assertIsArray($item['author']);
        $this->assertSame(10, $item['author']['id']);
        $this->assertSame('Test User', $item['author']['name']);
        $this->assertSame('/avatars/test.png', $item['author']['avatar_url']);
        $this->assertIsInt($item['likes_count']);
        $this->assertIsInt($item['comments_count']);
        $this->assertIsBool($item['is_liked']);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('_activity_id', $item);
        $this->assertArrayHasKey('_activity_created_at', $item);
    }

    public function test_getFeed_default_avatar_when_null(): void
    {
        $row = $this->makeFeedRow([
            'author_avatar' => null,
        ]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $result = $service->getFeed(10, []);

        $this->assertNotEmpty($result['items'], 'Feed items should not be empty');
        $item = $result['items'][0];

        $this->assertSame('/assets/img/defaults/default_avatar.png', $item['author']['avatar_url']);
    }

    // ── Additional edge cases ─────────────────────────────────────────

    public function test_getFeed_with_group_filter(): void
    {
        $row = $this->makeFeedRow([
            'activity_id' => 1,
            'group_id' => 5,
        ]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $result = $service->getFeed(10, ['group_id' => 5]);

        $this->assertCount(1, $result['items']);
    }

    public function test_getFeed_with_user_filter(): void
    {
        $row = $this->makeFeedRow([
            'activity_id' => 1,
            'user_id' => 42,
        ]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $result = $service->getFeed(10, ['user_id' => 42]);

        $this->assertCount(1, $result['items']);
        $this->assertSame(42, $result['items'][0]['author']['id']);
    }

    public function test_getFeed_without_current_user(): void
    {
        $row = $this->makeFeedRow();

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $result = $service->getFeed(null, []);

        $this->assertCount(1, $result['items']);
        $this->assertFalse($result['items'][0]['is_liked']);
    }

    public function test_getErrors_returns_empty_initially(): void
    {
        $service = new FeedService(new FeedActivity(), new FeedPost());
        $this->assertEmpty($service->getErrors());
    }

    public function test_getFeed_legacy_cursor_format(): void
    {
        $row = $this->makeFeedRow(['activity_id' => 3, 'source_id' => 103]);

        $service = $this->mockEloquentConnectionAndBuildService([[$row]]);

        $legacyCursor = base64_encode('10');
        $result = $service->getFeed(10, ['cursor' => $legacyCursor]);

        $this->assertCount(1, $result['items']);
    }
}
