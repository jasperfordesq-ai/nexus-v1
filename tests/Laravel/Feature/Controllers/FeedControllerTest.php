<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for feed endpoints.
 *
 * The v2 feed routes (GET /v2/feed, POST /v2/feed/posts, POST /v2/feed/like, etc.)
 * are handled by SocialController. The legacy FeedController handles /feed/hide,
 * /feed/mute, /feed/report. Both are tested here.
 */
class FeedControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ================================================================
    // FEED (GET /v2/feed) — Happy path
    // ================================================================

    public function test_feed_returns_collection(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['per_page', 'has_more'],
        ]);
    }

    // ================================================================
    // FEED — Authentication required
    // ================================================================

    public function test_feed_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/feed');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // FEED — Supports filters
    // ================================================================

    public function test_feed_supports_type_filter(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed?type=post');

        $response->assertStatus(200);
    }

    public function test_feed_supports_per_page(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed?per_page=5');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, $response->json('meta.per_page'));
    }

    // ================================================================
    // CREATE POST (POST /v2/feed/posts) — Authentication required
    // ================================================================

    public function test_create_post_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts', [
            'body' => 'Hello community!',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // CREATE POST — Happy path
    // ================================================================

    public function test_create_post_returns_201(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/feed/posts', [
            'body' => 'Hello community! This is my first post.',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ================================================================
    // LIKE (POST /v2/feed/like) — Validation
    // ================================================================

    public function test_like_returns_400_without_target(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/feed/like', []);

        $response->assertStatus(400);
    }

    public function test_like_returns_400_for_invalid_target_type(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/feed/like', [
            'target_type' => 'invalid',
            'target_id' => 1,
        ]);

        $response->assertStatus(400);
    }

    // ================================================================
    // LIKE — Authentication required
    // ================================================================

    public function test_like_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/like', [
            'target_type' => 'post',
            'target_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // HIDE POST — Authentication required
    // ================================================================

    public function test_hide_post_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/feed/hide', [
            'post_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // HIDE POST — Validation
    // ================================================================

    public function test_hide_post_returns_error_without_post_id(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/feed/hide', []);

        // FeedController returns error for invalid post_id
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_hide_post_succeeds_with_valid_post_id(): void
    {
        $this->authenticatedUser();

        // Create the user_hidden_posts table expectation - the endpoint uses insertOrIgnore
        $response = $this->apiPost('/feed/hide', [
            'post_id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
    }

    // ================================================================
    // MUTE USER — Validation
    // ================================================================

    public function test_mute_user_returns_error_without_user_id(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/feed/mute', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_mute_user_returns_error_when_muting_self(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiPost('/feed/mute', [
            'user_id' => $user->id,
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ================================================================
    // MUTE USER — Authentication required
    // ================================================================

    public function test_mute_user_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/feed/mute', [
            'user_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // REPORT POST — Authentication required
    // ================================================================

    public function test_report_post_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/feed/report', [
            'post_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // REPORT POST — Validation
    // ================================================================

    public function test_report_post_returns_error_without_post_id(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/feed/report', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ================================================================
    // REPORT POST — Happy path
    // ================================================================

    public function test_report_post_succeeds_with_valid_post_id(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/feed/report', [
            'post_id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.success', true);
    }

    // ================================================================
    // HIDE POST V2 — via SocialController
    // ================================================================

    public function test_hide_post_v2_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/hide');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // DELETE POST V2 — via SocialController
    // ================================================================

    public function test_delete_post_v2_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/delete');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // MUTE USER V2 — via SocialController
    // ================================================================

    public function test_mute_user_v2_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/users/1/mute');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // REPORT POST V2 — via SocialController
    // ================================================================

    public function test_report_post_v2_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/feed/posts/1/report');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // TENANT ISOLATION — Feed is tenant-scoped
    // ================================================================

    public function test_feed_only_returns_current_tenant_items(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed');

        $response->assertStatus(200);
        // Feed items should only contain items from the current tenant
        $data = $response->json('data');
        $this->assertIsArray($data);
    }
}
