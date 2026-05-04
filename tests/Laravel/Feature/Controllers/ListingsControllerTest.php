<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Events\ListingCreated;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for ListingsController.
 *
 * Covers CRUD, search, save/unsave, tags, nearby, featured, analytics, renew.
 */
class ListingsControllerTest extends TestCase
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
    // INDEX — Happy path
    // ================================================================

    public function test_index_returns_paginated_listings(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->count(3)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet('/v2/listings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['per_page', 'has_more'],
        ]);
    }

    public function test_index_rejects_per_page_above_max(): void
    {
        // ListListingsRequest caps per_page at 100.
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings?per_page=500');

        $response->assertStatus(422);
    }

    public function test_index_rejects_negative_page(): void
    {
        // ListListingsRequest requires page >= 1.
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings?page=0');

        $response->assertStatus(422);
    }

    public function test_index_accepts_valid_pagination(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings?page=1&per_page=10');

        $response->assertStatus(200);
    }

    public function test_index_supports_type_filter(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->offer()->create(['user_id' => $user->id]);
        Listing::factory()->forTenant($this->testTenantId)->request()->create(['user_id' => $user->id]);

        $response = $this->apiGet('/v2/listings?type=offer');

        $response->assertStatus(200);
    }

    public function test_index_supports_search_query_parameter_alias(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Needle listing audit token',
            'description' => 'A unique service for search alias coverage.',
            'hours_estimate' => 1,
        ]);
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Unrelated haystack listing',
            'description' => 'This should not match the unique query.',
            'hours_estimate' => 1,
        ]);

        $response = $this->apiGet('/v2/listings?search=Needle%20listing%20audit%20token&min_hours=0.1');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Needle listing audit token', $titles);
        $this->assertNotContains('Unrelated haystack listing', $titles);
    }

    public function test_index_returns_empty_for_unknown_category_slug(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Visible listing with no matching category slug',
        ]);

        $response = $this->apiGet('/v2/listings?category=missing-category-audit-slug');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total_items'));
    }

    // ================================================================
    // INDEX — Tenant isolation
    // ================================================================

    public function test_index_only_returns_current_tenant_listings(): void
    {
        $user = $this->authenticatedUser();
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create(['user_id' => $user->id]);

        // Create listings for a different tenant
        DB::table('tenants')->insertOrIgnore([
            'id' => 999, 'name' => 'Other', 'slug' => 'other',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherUser = User::factory()->forTenant(999)->create();
        Listing::factory()->forTenant(999)->count(3)->create(['user_id' => $otherUser->id]);

        $response = $this->apiGet('/v2/listings');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
        // All returned listings should belong to test tenant
        foreach ($data as $listing) {
            if (isset($listing['tenant_id'])) {
                $this->assertEquals($this->testTenantId, $listing['tenant_id']);
            }
        }
    }

    // ================================================================
    // SHOW — Happy path
    // ================================================================

    public function test_show_returns_listing_with_data_envelope(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiGet("/v2/listings/{$listing->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SHOW — Not found
    // ================================================================

    public function test_show_returns_404_for_nonexistent_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // STORE — Happy path
    // ================================================================

    public function test_store_creates_listing_and_returns_201(): void
    {
        Event::fake([ListingCreated::class]);
        $user = $this->authenticatedUser(['email' => '']);

        // Ensure a category exists for the listing
        DB::table('categories')->insertOrIgnore([
            'id' => 1, 'tenant_id' => $this->testTenantId,
            'name' => 'General', 'slug' => 'general', 'type' => 'listing',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->apiPost('/v2/listings', [
            'title' => 'Test Listing',
            'description' => 'A detailed description of the service offered.',
            'type' => 'offer',
            'category_id' => 1,
            'location' => 'Dublin',
            'price' => 2.0,
            'service_type' => 'physical_only',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        Event::assertDispatched(ListingCreated::class);
    }

    // ================================================================
    // STORE — Authentication required (401)
    // ================================================================

    public function test_store_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/listings', [
            'title' => 'Unauthorized',
            'description' => 'Should fail',
            'type' => 'offer',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // STORE — Validation errors (422)
    // ================================================================

    public function test_store_returns_validation_error_without_required_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/listings', [
            'type' => 'offer',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ================================================================
    // UPDATE — Happy path
    // ================================================================

    public function test_owner_can_update_listing(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Original',
        ]);

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'title' => 'Updated Title',
            'description' => $listing->description,
            'type' => $listing->type,
        ]);

        $response->assertStatus(200);
    }

    public function test_owner_can_pause_listing(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'status' => 'paused',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'tenant_id' => $this->testTenantId,
            'status' => 'paused',
        ]);
    }

    // ================================================================
    // UPDATE — Authorization (403)
    // ================================================================

    public function test_non_owner_cannot_update_listing(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
        ]);

        $this->authenticatedUser();

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'title' => 'Hijacked',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // UPDATE — Not found (404)
    // ================================================================

    public function test_update_returns_404_for_nonexistent_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/listings/999999', [
            'title' => 'Ghost',
        ]);

        $response->assertStatus(404);
    }

    // ================================================================
    // DELETE — Happy path
    // ================================================================

    public function test_owner_can_delete_listing(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiDelete("/v2/listings/{$listing->id}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    // ================================================================
    // DELETE — Authorization (403)
    // ================================================================

    public function test_non_owner_cannot_delete_listing(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
        ]);

        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/listings/{$listing->id}");

        $response->assertStatus(403);
    }

    // ================================================================
    // DELETE — Not found (404)
    // ================================================================

    public function test_delete_returns_404_for_nonexistent_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/listings/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // DELETE — Authentication required (401)
    // ================================================================

    public function test_delete_returns_401_without_auth(): void
    {
        $response = $this->apiDelete('/v2/listings/1');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SAVED LISTINGS — Auth required
    // ================================================================

    public function test_saved_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/listings/saved');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_saved_listings_returns_data_when_authenticated(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/saved');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SAVE / UNSAVE
    // ================================================================

    public function test_save_listing_requires_auth(): void
    {
        $response = $this->apiPost('/v2/listings/1/save');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_duplicate_save_does_not_increment_save_count_twice(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'save_count' => 0,
        ]);
        $this->authenticatedUser();

        $first = $this->apiPost("/v2/listings/{$listing->id}/save");
        $second = $this->apiPost("/v2/listings/{$listing->id}/save");

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame(1, (int) DB::table('listings')->where('id', $listing->id)->value('save_count'));
    }

    public function test_unsave_without_saved_record_does_not_decrement_save_count(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'save_count' => 3,
        ]);
        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/listings/{$listing->id}/save");

        $response->assertStatus(200);
        $this->assertSame(3, (int) DB::table('listings')->where('id', $listing->id)->value('save_count'));
    }

    // ================================================================
    // FEATURED
    // ================================================================

    public function test_featured_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/featured');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // POPULAR TAGS
    // ================================================================

    public function test_popular_tags_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/tags/popular');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // AUTOCOMPLETE TAGS — short query returns empty
    // ================================================================

    public function test_autocomplete_tags_returns_empty_for_short_query(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/tags/autocomplete?q=a');

        $response->assertStatus(200);
        $response->assertJson(['data' => []]);
    }

    // ================================================================
    // NEARBY — Validation
    // ================================================================

    public function test_nearby_returns_400_without_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/nearby');

        $response->assertStatus(400);
    }

    public function test_nearby_returns_400_for_invalid_latitude(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/nearby?lat=999&lon=0');

        $response->assertStatus(400);
    }

    public function test_nearby_returns_data_with_valid_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/nearby?lat=53.35&lon=-6.26');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CROSS-TENANT ISOLATION — UPDATE
    // ================================================================

    public function test_user_cannot_update_listing_from_different_tenant(): void
    {
        // Create a listing in tenant 999 (the "other" tenant)
        $otherUser = User::factory()->forTenant(999)->create();
        $listing = Listing::factory()->forTenant(999)->create([
            'user_id' => $otherUser->id,
            'title' => 'Other Tenant Listing',
        ]);

        // Authenticate as a user in the default test tenant
        $this->authenticatedUser();

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'title' => 'Cross-Tenant Hijack Attempt',
        ]);

        // Should be blocked — listing not visible to this tenant
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ================================================================
    // CROSS-TENANT ISOLATION — DELETE
    // ================================================================

    public function test_user_cannot_delete_listing_from_different_tenant(): void
    {
        // Create a listing in tenant 999
        $otherUser = User::factory()->forTenant(999)->create();
        $listing = Listing::factory()->forTenant(999)->create([
            'user_id' => $otherUser->id,
        ]);

        // Authenticate as a user in the default test tenant
        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/listings/{$listing->id}");

        // Should be blocked — listing not visible to this tenant
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ================================================================
    // CROSS-TENANT ISOLATION — SAVED LISTINGS
    // ================================================================

    public function test_saved_listings_are_tenant_scoped(): void
    {
        // Create and save a listing in tenant 999
        $otherUser = User::factory()->forTenant(999)->create();
        $otherListing = Listing::factory()->forTenant(999)->create([
            'user_id' => $otherUser->id,
        ]);

        // Manually insert a saved-listing record for the other tenant
        DB::table('user_saved_listings')->insertOrIgnore([
            'user_id' => $otherUser->id,
            'listing_id' => $otherListing->id,
            'tenant_id' => 999,
            'created_at' => now(),
        ]);

        // Authenticate as a user in the default test tenant
        $user = $this->authenticatedUser();

        $response = $this->apiGet('/v2/listings/saved');

        $response->assertStatus(200);

        // The saved listings response should not include the other tenant's listing
        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach ($data as $saved) {
            if (isset($saved['id'])) {
                $this->assertNotEquals($otherListing->id, $saved['id']);
            }
        }
    }
}
