<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\FeedSocialService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class FeedSocialServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FeedSocialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedSocialService();
        // Factories / observers can reset TenantContext to tenant 1; re-pin tenant 2.
        TenantContext::setById($this->testTenantId);
    }

    /**
     * Seed a real user (feed_posts.user_id has an FK to users.id).
     */
    private function seedUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'email'      => 'feeduser' . uniqid() . '@t.test',
            'name'       => 'Feed User',
            'first_name' => 'Feed',
            'last_name'  => 'User',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_sharePost_returns_share_id(): void
    {
        $tenantId = $this->testTenantId;
        $userId = $this->seedUser();

        // Seed a public, viewable post so FeedItemTables::canView('post', ...) passes.
        $postId = DB::table('feed_posts')->insertGetId([
            'tenant_id'      => $tenantId,
            'user_id'        => $userId,
            'content'        => 'Great post!',
            'visibility'     => 'public',
            'publish_status' => 'published',
            'is_hidden'      => 0,
            'share_count'    => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $result = $this->service->sharePost($postId, $userId, 'Great post!');

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        // Verify the share row landed scoped to the tenant.
        $this->assertDatabaseHas('post_shares', [
            'id'               => $result,
            'tenant_id'        => $tenantId,
            'original_type'    => 'post',
            'original_post_id' => $postId,
            'user_id'          => $userId,
        ]);

        // Verify the share counter incremented.
        $this->assertSame(1, (int) DB::table('feed_posts')->where('id', $postId)->value('share_count'));
    }

    public function test_sharePost_throws_when_post_not_viewable(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('post_not_found');

        // No post seeded for this id → canView returns false.
        $this->service->sharePost(999999, $this->seedUser(), 'nope');
    }

    public function test_getTrendingHashtags_returns_array(): void
    {
        $tenantId = $this->testTenantId;

        $hashtagId = DB::table('hashtags')->insertGetId([
            'tenant_id'  => $tenantId,
            'tag'        => 'timebank',
            'post_count' => 0,
            'created_at' => now(),
        ]);

        $postId = DB::table('feed_posts')->insertGetId([
            'tenant_id'      => $tenantId,
            'user_id'        => $this->seedUser(),
            'content'        => '#timebank rocks',
            'visibility'     => 'public',
            'publish_status' => 'published',
            'is_hidden'      => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        DB::table('post_hashtags')->insert([
            'tenant_id'  => $tenantId,
            'post_id'    => $postId,
            'hashtag_id' => $hashtagId,
            'created_at' => now(),
        ]);

        $result = $this->service->getTrendingHashtags();

        $this->assertIsArray($result);
        $tags = array_column($result, 'hashtag');
        $this->assertContains('timebank', $tags);

        $row = $result[array_search('timebank', $tags, true)];
        $this->assertSame(1, (int) $row['usage_count']);
    }
}
