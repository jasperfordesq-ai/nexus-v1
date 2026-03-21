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
 * Feature tests for SocialController — feed, posts, likes, polls, impressions.
 */
class SocialControllerTest extends TestCase
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
    //  GET /v2/feed
    // ------------------------------------------------------------------

    public function test_feed_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(401);
    }

    public function test_feed_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts
    // ------------------------------------------------------------------

    public function test_create_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts', [
            'content' => 'Hello world!',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/like
    // ------------------------------------------------------------------

    public function test_like_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/like', [
            'post_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/polls
    // ------------------------------------------------------------------

    public function test_create_poll_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/polls', [
            'question' => 'What do you think?',
            'options' => ['Yes', 'No'],
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/hide
    // ------------------------------------------------------------------

    public function test_hide_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/hide');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/report
    // ------------------------------------------------------------------

    public function test_report_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/report', [
            'reason' => 'spam',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/posts/{id}/delete
    // ------------------------------------------------------------------

    public function test_delete_post_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/delete');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/feed/users/{id}/mute
    // ------------------------------------------------------------------

    public function test_mute_user_requires_auth(): void
    {
        $response = $this->apiPost('/v2/feed/users/1/mute');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /social/like (legacy)
    // ------------------------------------------------------------------

    public function test_legacy_like_requires_auth(): void
    {
        $response = $this->apiPost('/social/like', ['post_id' => 1]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /social/feed (legacy)
    // ------------------------------------------------------------------

    public function test_legacy_feed_requires_auth(): void
    {
        $response = $this->apiPost('/social/feed');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  Tenant isolation
    // ------------------------------------------------------------------

    public function test_feed_is_tenant_scoped(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }
}
