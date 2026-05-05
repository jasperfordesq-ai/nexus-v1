<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for FeedSocialController — sharing, hashtags.
 */
class FeedSocialControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/share
    // ------------------------------------------------------------------

    public function test_share_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/share');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/feed/posts/{id}/share
    // ------------------------------------------------------------------

    public function test_unshare_post_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/feed/posts/1/share');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/feed/posts/{id}/sharers
    // ------------------------------------------------------------------

    public function test_get_sharers_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/posts/1/sharers');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/feed/hashtags/trending
    // ------------------------------------------------------------------

    public function test_trending_hashtags_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/hashtags/trending');

        $response->assertStatus(401);
    }

    public function test_trending_hashtags_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed/hashtags/trending');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/feed/hashtags/search
    // ------------------------------------------------------------------

    public function test_search_hashtags_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/hashtags/search?q=test');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/feed/hashtags/{tag}
    // ------------------------------------------------------------------

    public function test_hashtag_posts_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/hashtags/community');

        $response->assertStatus(401);
    }

    public function test_share_rejects_hidden_post(): void
    {
        $viewer = $this->authenticatedUser();
        $author = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $postId = DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $author->id,
            'content' => 'Hidden post should not be shareable',
            'type' => 'post',
            'visibility' => 'public',
            'is_hidden' => 1,
            'publish_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->apiPost('/v2/shares', [
            'type' => 'post',
            'id' => $postId,
        ])->assertStatus(404);

        $this->assertDatabaseMissing('post_shares', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $viewer->id,
            'original_type' => 'post',
            'original_post_id' => $postId,
        ]);
    }

    public function test_hashtag_posts_exclude_hidden_posts(): void
    {
        $user = $this->authenticatedUser();
        $hashtagId = DB::table('hashtags')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'tag' => 'audit',
            'post_count' => 2,
            'last_used_at' => now(),
            'created_at' => now(),
        ]);

        $visiblePostId = DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'content' => 'Visible hashtag post',
            'type' => 'post',
            'visibility' => 'public',
            'is_hidden' => 0,
            'publish_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hiddenPostId = DB::table('feed_posts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'content' => 'Hidden hashtag post',
            'type' => 'post',
            'visibility' => 'public',
            'is_hidden' => 1,
            'publish_status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$visiblePostId, $hiddenPostId] as $postId) {
            DB::table('post_hashtags')->insert([
                'tenant_id' => $this->testTenantId,
                'post_id' => $postId,
                'hashtag_id' => $hashtagId,
                'created_at' => now(),
            ]);
        }

        $response = $this->apiGet('/v2/feed/hashtags/audit');
        $response->assertStatus(200);

        $ids = collect($response->json('data') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $visiblePostId, $ids);
        $this->assertNotContains((int) $hiddenPostId, $ids);
        $this->assertSame(1, (int) $response->json('meta.total_items'));
    }
}
