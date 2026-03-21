<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
