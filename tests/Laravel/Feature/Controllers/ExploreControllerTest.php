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
 * Feature tests for ExploreController — discover/explore page API endpoints.
 *
 * Endpoints:
 *   GET  /api/v2/explore                    index (public, optional auth)
 *   GET  /api/v2/explore/for-you            forYou (public, optional auth)
 *   GET  /api/v2/explore/trending           trending (public)
 *   GET  /api/v2/explore/popular-listings   popularListings (public)
 *   GET  /api/v2/explore/category/{slug}    category (public)
 *   POST /api/v2/explore/track              track (auth required)
 *   POST /api/v2/explore/dismiss            dismiss (auth required)
 */
class ExploreControllerTest extends TestCase
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

    /**
     * Seed a minimal feed_post for trending queries.
     */
    private function seedFeedPost(int $userId, array $overrides = []): int
    {
        return DB::table('feed_posts')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'content' => 'Test trending post content for explore',
            'is_hidden' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Seed a listing for popular/category queries.
     */
    private function seedListing(int $userId, array $overrides = []): int
    {
        return DB::table('listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Test Listing',
            'type' => 'offer',
            'status' => 'active',
            'view_count' => 10,
            'save_count' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Seed a category for category browsing.
     */
    private function seedCategory(array $overrides = []): int
    {
        return DB::table('categories')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'name' => 'Gardening',
            'slug' => 'gardening',
            'color' => '#22c55e',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    //  INDEX — GET /api/v2/explore
    // ------------------------------------------------------------------

    public function test_index_returns_200_for_unauthenticated_user(): void
    {
        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_returns_200_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_contains_expected_sections(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $data = $response->json('data');

        // All expected sections should be present (may be empty arrays)
        $this->assertArrayHasKey('trending_posts', $data);
        $this->assertArrayHasKey('popular_listings', $data);
        $this->assertArrayHasKey('active_groups', $data);
        $this->assertArrayHasKey('upcoming_events', $data);
        $this->assertArrayHasKey('top_contributors', $data);
        $this->assertArrayHasKey('trending_hashtags', $data);
        $this->assertArrayHasKey('new_members', $data);
        $this->assertArrayHasKey('featured_challenges', $data);
        $this->assertArrayHasKey('community_stats', $data);
        $this->assertArrayHasKey('recommended_listings', $data);
    }

    public function test_index_community_stats_has_expected_keys(): void
    {
        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $stats = $response->json('data.community_stats');

        $this->assertArrayHasKey('total_members', $stats);
        $this->assertArrayHasKey('exchanges_this_month', $stats);
        $this->assertArrayHasKey('hours_exchanged', $stats);
        $this->assertArrayHasKey('active_listings', $stats);
    }

    public function test_index_returns_trending_posts_with_seeded_data(): void
    {
        $user = $this->authenticatedUser();
        $this->seedFeedPost($user->id);

        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $trending = $response->json('data.trending_posts');
        $this->assertIsArray($trending);
    }

    public function test_index_returns_meta_with_base_url(): void
    {
        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $response->assertJsonStructure(['meta' => ['base_url']]);
    }

    // ------------------------------------------------------------------
    //  TRENDING — GET /api/v2/explore/trending
    // ------------------------------------------------------------------

    public function test_trending_returns_200(): void
    {
        $response = $this->apiGet('/v2/explore/trending');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'total_pages', 'has_more'],
        ]);
    }

    public function test_trending_returns_paginated_results(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        // Seed multiple posts
        for ($i = 0; $i < 5; $i++) {
            $this->seedFeedPost($user->id);
        }

        $response = $this->apiGet('/v2/explore/trending?page=1&per_page=3');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(3, $meta['per_page']);
    }

    public function test_trending_defaults_to_page_1_per_page_20(): void
    {
        $response = $this->apiGet('/v2/explore/trending');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(20, $meta['per_page']);
    }

    public function test_trending_clamps_per_page_to_max_100(): void
    {
        $response = $this->apiGet('/v2/explore/trending?per_page=200');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(100, $meta['per_page']);
    }

    public function test_trending_clamps_page_to_min_1(): void
    {
        $response = $this->apiGet('/v2/explore/trending?page=0');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
    }

    public function test_trending_returns_empty_data_for_empty_tenant(): void
    {
        $response = $this->apiGet('/v2/explore/trending');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    public function test_trending_post_items_have_expected_fields(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedFeedPost($user->id);

        $response = $this->apiGet('/v2/explore/trending');

        $response->assertStatus(200);
        $items = $response->json('data');

        if (count($items) > 0) {
            $item = $items[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('user_id', $item);
            $this->assertArrayHasKey('excerpt', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('author_name', $item);
            $this->assertArrayHasKey('likes_count', $item);
            $this->assertArrayHasKey('comments_count', $item);
        }
    }

    // ------------------------------------------------------------------
    //  POPULAR LISTINGS — GET /api/v2/explore/popular-listings
    // ------------------------------------------------------------------

    public function test_popular_listings_returns_200(): void
    {
        $response = $this->apiGet('/v2/explore/popular-listings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'total_pages', 'has_more'],
        ]);
    }

    public function test_popular_listings_returns_paginated_results(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($user->id, [
                'title' => "Listing {$i}",
                'view_count' => 50 - ($i * 5),
            ]);
        }

        $response = $this->apiGet('/v2/explore/popular-listings?page=1&per_page=3');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(3, $meta['per_page']);
    }

    public function test_popular_listings_defaults_pagination(): void
    {
        $response = $this->apiGet('/v2/explore/popular-listings');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(20, $meta['per_page']);
    }

    public function test_popular_listings_clamps_per_page_max(): void
    {
        $response = $this->apiGet('/v2/explore/popular-listings?per_page=500');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(100, $meta['per_page']);
    }

    public function test_popular_listings_items_have_expected_fields(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id);

        $response = $this->apiGet('/v2/explore/popular-listings');

        $response->assertStatus(200);
        $items = $response->json('data');

        if (count($items) > 0) {
            $item = $items[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('view_count', $item);
            $this->assertArrayHasKey('save_count', $item);
            $this->assertArrayHasKey('category_name', $item);
            $this->assertArrayHasKey('author_name', $item);
        }
    }

    public function test_popular_listings_excludes_inactive_listings(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id, ['status' => 'closed', 'title' => 'Closed Listing']);

        $response = $this->apiGet('/v2/explore/popular-listings');

        $response->assertStatus(200);
        $items = $response->json('data');
        $titles = array_column($items, 'title');
        $this->assertNotContains('Closed Listing', $titles);
    }

    // ------------------------------------------------------------------
    //  CATEGORY — GET /api/v2/explore/category/{slug}
    // ------------------------------------------------------------------

    public function test_category_returns_200_with_valid_slug(): void
    {
        $categoryId = $this->seedCategory();
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id, ['category_id' => $categoryId]);

        $response = $this->apiGet('/v2/explore/category/gardening');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total', 'total_pages', 'has_more'],
        ]);
    }

    public function test_category_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->apiGet('/v2/explore/category/nonexistent-slug-xyz');

        $response->assertStatus(404);
        $response->assertJsonStructure(['errors']);
    }

    public function test_category_returns_listings_in_category(): void
    {
        $categoryId = $this->seedCategory(['slug' => 'cooking', 'name' => 'Cooking']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id, ['category_id' => $categoryId, 'title' => 'Cooking Class']);

        $response = $this->apiGet('/v2/explore/category/cooking');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertEquals('Cooking Class', $items[0]['title']);
    }

    public function test_category_supports_pagination(): void
    {
        $categoryId = $this->seedCategory(['slug' => 'tech', 'name' => 'Tech']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($user->id, [
                'category_id' => $categoryId,
                'title' => "Tech Listing {$i}",
            ]);
        }

        $response = $this->apiGet('/v2/explore/category/tech?page=1&per_page=2');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(2, $meta['per_page']);
        $this->assertGreaterThanOrEqual(5, $meta['total']);
        $this->assertTrue($meta['has_more']);
    }

    public function test_category_listing_items_have_expected_fields(): void
    {
        $categoryId = $this->seedCategory(['slug' => 'music', 'name' => 'Music']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id, ['category_id' => $categoryId]);

        $response = $this->apiGet('/v2/explore/category/music');

        $response->assertStatus(200);
        $items = $response->json('data');

        if (count($items) > 0) {
            $item = $items[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('view_count', $item);
            $this->assertArrayHasKey('author_name', $item);
        }
    }

    public function test_category_excludes_inactive_listings(): void
    {
        $categoryId = $this->seedCategory(['slug' => 'art', 'name' => 'Art']);
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $this->seedListing($user->id, ['category_id' => $categoryId, 'status' => 'closed', 'title' => 'Closed Art']);
        $this->seedListing($user->id, ['category_id' => $categoryId, 'status' => 'active', 'title' => 'Open Art']);

        $response = $this->apiGet('/v2/explore/category/art');

        $response->assertStatus(200);
        $items = $response->json('data');
        $titles = array_column($items, 'title');
        $this->assertContains('Open Art', $titles);
        $this->assertNotContains('Closed Art', $titles);
    }

    // ------------------------------------------------------------------
    //  TENANT ISOLATION
    // ------------------------------------------------------------------

    public function test_trending_does_not_leak_other_tenant_posts(): void
    {
        $ownUser = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $otherUser = User::factory()->forTenant(999)->create(['status' => 'active']);

        $this->seedFeedPost($ownUser->id, ['content' => 'Own tenant post']);

        // Seed a post in the other tenant
        DB::table('feed_posts')->insert([
            'tenant_id' => 999,
            'user_id' => $otherUser->id,
            'content' => 'Other tenant post that should not appear',
            'is_hidden' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/explore/trending');

        $response->assertStatus(200);
        $items = $response->json('data');
        foreach ($items as $item) {
            $this->assertStringNotContainsString('Other tenant post', $item['excerpt'] ?? '');
        }
    }

    public function test_popular_listings_does_not_leak_other_tenant_data(): void
    {
        $ownUser = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $otherUser = User::factory()->forTenant(999)->create(['status' => 'active']);

        $this->seedListing($ownUser->id, ['title' => 'Own tenant listing']);

        DB::table('listings')->insert([
            'tenant_id' => 999,
            'user_id' => $otherUser->id,
            'title' => 'Leaked listing from other tenant',
            'type' => 'offer',
            'status' => 'active',
            'view_count' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/explore/popular-listings');

        $response->assertStatus(200);
        $items = $response->json('data');
        $titles = array_column($items, 'title');
        $this->assertNotContains('Leaked listing from other tenant', $titles);
    }

    public function test_category_does_not_leak_other_tenant_categories(): void
    {
        // Category in another tenant with same slug
        DB::table('categories')->insert([
            'tenant_id' => 999,
            'name' => 'Secret Category',
            'slug' => 'secret-cross-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/explore/category/secret-cross-tenant');

        // Should not find the category from another tenant
        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    //  API RESPONSE FORMAT
    // ------------------------------------------------------------------

    public function test_all_endpoints_return_json(): void
    {
        $endpoints = [
            '/v2/explore',
            '/v2/explore/trending',
            '/v2/explore/popular-listings',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->apiGet($endpoint);
            $response->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_endpoints_include_api_version_header(): void
    {
        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $response->assertHeader('API-Version', '2.0');
    }

    public function test_endpoints_include_tenant_id_header(): void
    {
        $response = $this->apiGet('/v2/explore');

        $response->assertStatus(200);
        $response->assertHeader('X-Tenant-ID', (string) $this->testTenantId);
    }

    // ------------------------------------------------------------------
    //  EDGE CASES
    // ------------------------------------------------------------------

    public function test_trending_page_beyond_results_returns_empty(): void
    {
        $response = $this->apiGet('/v2/explore/trending?page=9999');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function test_popular_listings_page_beyond_results_returns_empty(): void
    {
        $response = $this->apiGet('/v2/explore/popular-listings?page=9999');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function test_category_page_beyond_results_returns_empty(): void
    {
        $categoryId = $this->seedCategory(['slug' => 'sparse', 'name' => 'Sparse']);

        $response = $this->apiGet('/v2/explore/category/sparse?page=9999');

        $response->assertStatus(200);
        $items = $response->json('data');
        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function test_category_slug_with_special_characters_handled(): void
    {
        // URL-encoded special characters in slug
        $response = $this->apiGet('/v2/explore/category/no%20such%20category');

        // Should 404, not 500
        $response->assertStatus(404);
    }
}
