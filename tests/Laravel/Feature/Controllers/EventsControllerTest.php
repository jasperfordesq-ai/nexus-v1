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
 * Feature tests for EventsController.
 *
 * Covers CRUD, RSVP, attendees, cancel, waitlist, nearby, series.
 */
class EventsControllerTest extends TestCase
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

    private function seedCategory(): int
    {
        DB::table('categories')->insertOrIgnore([
            'id' => 10,
            'tenant_id' => $this->testTenantId,
            'name' => 'Community',
            'slug' => 'community',
            'type' => 'event',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return 10;
    }

    /**
     * Create an event directly in the database for testing.
     */
    private function createEvent(int $userId, array $overrides = []): int
    {
        return DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Test Event',
            'description' => 'A community test event.',
            'location' => 'Dublin',
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(7)->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ================================================================
    // INDEX — Happy path
    // ================================================================

    public function test_index_returns_events_collection(): void
    {
        $user = $this->authenticatedUser();
        $this->createEvent($user->id);

        $response = $this->apiGet('/v2/events');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['per_page', 'has_more'],
        ]);
    }

    // ================================================================
    // INDEX — Authentication required
    // ================================================================

    public function test_index_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/events');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SHOW — Happy path
    // ================================================================

    public function test_show_returns_event_data(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id);

        $response = $this->apiGet("/v2/events/{$eventId}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // SHOW — Not found
    // ================================================================

    public function test_show_returns_404_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/events/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // STORE — Happy path
    // ================================================================

    public function test_store_creates_event(): void
    {
        $this->authenticatedUser();
        $categoryId = $this->seedCategory();

        $response = $this->apiPost('/v2/events', [
            'title' => 'Community Gathering',
            'description' => 'A fun community gathering for all members.',
            'location' => 'Community Hall, Dublin',
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(14)->addHours(3)->format('Y-m-d H:i:s'),
            'category_id' => $categoryId,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ================================================================
    // STORE — Authentication required
    // ================================================================

    public function test_store_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/events', [
            'title' => 'Unauthorized Event',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // STORE — Validation errors
    // ================================================================

    public function test_store_returns_validation_error_without_title(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/events', [
            'description' => 'No title provided.',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ================================================================
    // UPDATE — Happy path
    // ================================================================

    public function test_organizer_can_update_event(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id);

        $response = $this->apiPut("/v2/events/{$eventId}", [
            'title' => 'Updated Event Title',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    // ================================================================
    // UPDATE — Authorization (403)
    // ================================================================

    public function test_non_organizer_cannot_update_event(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->createEvent($organizer->id);

        $this->authenticatedUser();

        $response = $this->apiPut("/v2/events/{$eventId}", [
            'title' => 'Hijacked Title',
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ================================================================
    // UPDATE — Not found (404)
    // ================================================================

    public function test_update_returns_404_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/events/999999', [
            'title' => 'Ghost Event',
        ]);

        $this->assertContains($response->getStatusCode(), [404, 422]);
    }

    // ================================================================
    // DELETE — Happy path
    // ================================================================

    public function test_organizer_can_delete_event(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id);

        $response = $this->apiDelete("/v2/events/{$eventId}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    // ================================================================
    // DELETE — Authorization (403)
    // ================================================================

    public function test_non_organizer_cannot_delete_event(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create();
        $eventId = $this->createEvent($organizer->id);

        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/events/{$eventId}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ================================================================
    // DELETE — Not found (404)
    // ================================================================

    public function test_delete_returns_404_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/events/999999');

        $this->assertContains($response->getStatusCode(), [400, 404]);
    }

    // ================================================================
    // RSVP — Happy path
    // ================================================================

    public function test_rsvp_requires_status_field(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id);

        $response = $this->apiPost("/v2/events/{$eventId}/rsvp", []);

        $response->assertStatus(400);
    }

    public function test_rsvp_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/events/1/rsvp', [
            'status' => 'going',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // RSVP — Not found
    // ================================================================

    public function test_rsvp_returns_error_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/events/999999/rsvp', [
            'status' => 'going',
        ]);

        $this->assertContains($response->getStatusCode(), [404, 422]);
    }

    // ================================================================
    // ATTENDEES — Not found
    // ================================================================

    public function test_attendees_returns_404_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/events/999999/attendees');

        $response->assertStatus(404);
    }

    public function test_attendees_returns_collection_for_existing_event(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id);

        $response = $this->apiGet("/v2/events/{$eventId}/attendees");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // CANCEL — Authentication required
    // ================================================================

    public function test_cancel_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/events/1/cancel');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // CANCEL — Not found
    // ================================================================

    public function test_cancel_returns_404_for_nonexistent_event(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/events/999999/cancel');

        $this->assertContains($response->getStatusCode(), [400, 404]);
    }

    // ================================================================
    // NEARBY — Validation
    // ================================================================

    public function test_nearby_returns_400_without_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/events/nearby');

        $response->assertStatus(400);
    }

    public function test_nearby_returns_400_for_invalid_latitude(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/events/nearby?lat=999&lon=0');

        $response->assertStatus(400);
    }

    public function test_nearby_returns_data_with_valid_coordinates(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/events/nearby?lat=53.35&lon=-6.26');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // TENANT ISOLATION
    // ================================================================

    public function test_cannot_access_event_from_different_tenant(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => 999, 'name' => 'Other', 'slug' => 'other',
            'is_active' => true, 'depth' => 0, 'allows_subtenants' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherUser = User::factory()->forTenant(999)->create();
        $otherEventId = DB::table('events')->insertGetId([
            'tenant_id' => 999,
            'user_id' => $otherUser->id,
            'title' => 'Other Tenant Event',
            'description' => 'Should not be visible.',
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->authenticatedUser();

        $response = $this->apiGet("/v2/events/{$otherEventId}");

        // Should return 404 because event belongs to different tenant
        $this->assertContains($response->getStatusCode(), [404, 403]);
    }
}
