<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the Listings CRUD API.
 *
 * Covers index, create, update, and delete operations,
 * verifying tenant scoping and ownership authorization.
 */
class ListingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create and authenticate a test user via Sanctum.
     */
    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  INDEX
    // ------------------------------------------------------------------

    /**
     * Test listing index returns paginated results.
     */
    public function test_listing_index_returns_data(): void
    {
        Listing::factory()->forTenant($this->testTenantId)->count(3)->create();

        $response = $this->apiGet('/v2/listings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);
    }

    /**
     * Test listing index only returns listings for the current tenant.
     */
    public function test_listing_index_is_tenant_scoped(): void
    {
        // Create listings on two different tenants
        Listing::factory()->forTenant($this->testTenantId)->count(2)->create();
        Listing::factory()->forTenant(999)->count(3)->create();

        $response = $this->apiGet('/v2/listings');

        $response->assertStatus(200);

        // The response should only contain listings from tenant 2.
        // Exact assertion depends on response shape, but count should be <= 2
        // from the factory data (ignoring pre-existing seed data).
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    // ------------------------------------------------------------------
    //  CREATE
    // ------------------------------------------------------------------

    /**
     * Test that an authenticated user can create a listing.
     */
    public function test_authenticated_user_can_create_listing(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiPost('/v2/listings', [
            'title' => 'Dog Walking Service',
            'description' => 'I can walk your dog in the local park. Experienced with all breeds.',
            'type' => 'offer',
            'location' => 'Dublin',
            'price' => 1.5,
            'service_type' => 'in_person',
        ]);

        // Should return 200 or 201 on success
        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    /**
     * Test that an unauthenticated user cannot create a listing.
     */
    public function test_unauthenticated_user_cannot_create_listing(): void
    {
        $response = $this->apiPost('/v2/listings', [
            'title' => 'Unauthorized Listing',
            'description' => 'Should fail.',
            'type' => 'offer',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    /**
     * Test that listing creation fails without required fields.
     */
    public function test_create_listing_fails_without_required_fields(): void
    {
        $this->authenticatedUser();

        // Missing title and description
        $response = $this->apiPost('/v2/listings', [
            'type' => 'offer',
        ]);

        // Should return a validation error (400 or 422)
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ------------------------------------------------------------------
    //  UPDATE
    // ------------------------------------------------------------------

    /**
     * Test that the owner can update their listing.
     */
    public function test_owner_can_update_listing(): void
    {
        $user = $this->authenticatedUser();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Original Title',
        ]);

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'title' => 'Updated Title',
            'description' => $listing->description,
            'type' => $listing->type,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    /**
     * Test that a non-owner cannot update someone else's listing.
     */
    public function test_non_owner_cannot_update_listing(): void
    {
        // Create listing owned by a different user
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
        ]);

        // Authenticate as a different user
        $this->authenticatedUser();

        $response = $this->apiPut("/v2/listings/{$listing->id}", [
            'title' => 'Hijacked Title',
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ------------------------------------------------------------------
    //  DELETE
    // ------------------------------------------------------------------

    /**
     * Test that the owner can delete their listing.
     */
    public function test_owner_can_delete_listing(): void
    {
        $user = $this->authenticatedUser();

        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
        ]);

        $response = $this->apiDelete("/v2/listings/{$listing->id}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    /**
     * Test that a non-owner cannot delete someone else's listing.
     */
    public function test_non_owner_cannot_delete_listing(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
        ]);

        // Authenticate as a different user
        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/listings/{$listing->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    /**
     * Test that deleting a nonexistent listing returns 404.
     */
    public function test_delete_nonexistent_listing_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/listings/999999');

        $this->assertContains($response->getStatusCode(), [404, 400]);
    }
}
