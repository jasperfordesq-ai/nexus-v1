<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\EventService;
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

    private function createPoll(int $userId, array $overrides = []): int
    {
        return DB::table('polls')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'question' => 'Which activity should we choose?',
            'description' => 'Community planning poll.',
            'is_active' => 1,
            'poll_type' => 'standard',
            'category' => 'events',
            'created_at' => now(),
        ], $overrides));
    }

    private function setDailyNotificationPreference(int $userId): void
    {
        DB::table('notification_settings')->insert([
            'user_id' => $userId,
            'context_type' => 'global',
            'context_id' => 0,
            'frequency' => 'daily',
        ]);
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
    // INDEX — Public (no auth required)
    // ================================================================

    public function test_index_is_public_without_auth(): void
    {
        // GET /v2/events is intentionally public (->withoutMiddleware('auth:sanctum')
        // in routes/api.php) — community event listings are browsable by anyone,
        // consistent with the public show/nearby/attendees endpoints.
        $response = $this->apiGet('/v2/events');

        $response->assertStatus(200);
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

    public function test_store_cannot_link_poll_owned_by_another_member(): void
    {
        $user = $this->authenticatedUser();
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();
        $otherPollId = $this->createPoll($otherUser->id);

        $response = $this->apiPost('/v2/events', [
            'title' => 'Community Gathering',
            'description' => 'A fun community gathering for all members.',
            'location' => 'Community Hall',
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(14)->addHours(3)->format('Y-m-d H:i:s'),
            'poll_ids' => [$otherPollId],
        ]);

        $response->assertStatus(403);
        $this->assertNull(DB::table('polls')->where('id', $otherPollId)->value('event_id'));
        $this->assertSame(0, DB::table('events')->where('tenant_id', $this->testTenantId)->where('user_id', $user->id)->where('title', 'Community Gathering')->count());
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

    public function test_update_cannot_link_poll_owned_by_another_member(): void
    {
        $user = $this->authenticatedUser();
        $eventId = $this->createEvent($user->id, ['title' => 'Original Event Title']);
        $otherUser = User::factory()->forTenant($this->testTenantId)->create();
        $otherPollId = $this->createPoll($otherUser->id);

        $response = $this->apiPut("/v2/events/{$eventId}", [
            'title' => 'Hijack Poll Association',
            'poll_ids' => [$otherPollId],
        ]);

        $response->assertStatus(403);
        $this->assertNull(DB::table('polls')->where('id', $otherPollId)->value('event_id'));
        $this->assertSame('Original Event Title', DB::table('events')->where('id', $eventId)->value('title'));
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
    // DELETE — Recurring series notifies future attendees (cancel parity)
    // ================================================================

    public function test_series_delete_notifies_future_attendees_once_and_skips_past_and_cancelled(): void
    {
        $organizer = $this->authenticatedUser();
        $futureAttendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'series-delete-future-' . uniqid('', true) . '@example.test',
        ]);
        $waitlisted = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'series-delete-waitlist-' . uniqid('', true) . '@example.test',
        ]);
        $pastAttendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'series-delete-past-' . uniqid('', true) . '@example.test',
        ]);
        $cancelledAttendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'series-delete-cancelled-' . uniqid('', true) . '@example.test',
        ]);

        $templateId = $this->createEvent((int) $organizer->id, [
            'title' => 'Recurring Series',
            'is_recurring_template' => 1,
        ]);
        $futureOccurrenceA = $this->createEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(14)->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $futureOccurrenceB = $this->createEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'start_time' => now()->addDays(21)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(21)->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $pastOccurrence = $this->createEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'start_time' => now()->subDays(7)->format('Y-m-d H:i:s'),
            'end_time' => now()->subDays(7)->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        // Already cancelled future occurrence — its attendees were notified when it
        // was cancelled; deleting the series must NOT notify them a second time.
        $cancelledOccurrence = $this->createEvent((int) $organizer->id, [
            'parent_event_id' => $templateId,
            'status' => 'cancelled',
            'start_time' => now()->addDays(28)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(28)->addHours(2)->format('Y-m-d H:i:s'),
        ]);

        // Future attendee RSVPs to BOTH future occurrences — must be notified exactly once.
        foreach ([$futureOccurrenceA, $futureOccurrenceB] as $occurrenceId) {
            DB::table('event_rsvps')->insert([
                'tenant_id' => $this->testTenantId,
                'event_id' => $occurrenceId,
                'user_id' => $futureAttendee->id,
                'status' => 'going',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('event_waitlist')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $futureOccurrenceA,
            'user_id' => $waitlisted->id,
            'position' => 1,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $pastOccurrence,
            'user_id' => $pastAttendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $cancelledOccurrence,
            'user_id' => $cancelledAttendee->id,
            'status' => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->setDailyNotificationPreference((int) $futureAttendee->id);
        $this->setDailyNotificationPreference((int) $waitlisted->id);

        $response = $this->apiDelete("/v2/events/{$templateId}");

        $this->assertContains($response->getStatusCode(), [200, 204]);

        // Series rows removed: template + future occurrences gone, past detached but kept.
        $this->assertNull(DB::table('events')->where('id', $templateId)->value('id'));
        $this->assertNull(DB::table('events')->where('id', $futureOccurrenceA)->value('id'));
        $this->assertNull(DB::table('events')->where('id', $futureOccurrenceB)->value('id'));
        $this->assertNotNull(DB::table('events')->where('id', $pastOccurrence)->value('id'));
        $this->assertNull(DB::table('events')->where('id', $pastOccurrence)->value('parent_event_id'));

        // Future attendee + waitlisted user each get EXACTLY ONE cancellation bell.
        $bells = fn (int $userId) => DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $userId)
            ->where('link', "/events/{$templateId}")
            ->where('type', 'event')
            ->count();
        $this->assertSame(1, $bells((int) $futureAttendee->id));
        $this->assertSame(1, $bells((int) $waitlisted->id));

        // Past and already-cancelled attendees get nothing.
        $this->assertSame(0, $bells((int) $pastAttendee->id));
        $this->assertSame(0, $bells((int) $cancelledAttendee->id));

        // Email channel mirrors the cancel path: one queued digest entry each
        // (daily frequency), none for past/cancelled attendees.
        $queued = fn (int $userId) => DB::table('notification_queue')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $userId)
            ->where('link', "/events/{$templateId}")
            ->where('activity_type', 'event_cancellation')
            ->count();
        $this->assertSame(1, $queued((int) $futureAttendee->id));
        $this->assertSame(1, $queued((int) $waitlisted->id));
        $this->assertSame(0, $queued((int) $pastAttendee->id));
        $this->assertSame(0, $queued((int) $cancelledAttendee->id));
    }

    public function test_non_recurring_delete_sends_no_cancellation_notifications(): void
    {
        $organizer = $this->authenticatedUser();
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'plain-delete-attendee-' . uniqid('', true) . '@example.test',
        ]);
        $eventId = $this->createEvent((int) $organizer->id);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->setDailyNotificationPreference((int) $attendee->id);

        $response = $this->apiDelete("/v2/events/{$eventId}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
        $this->assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $attendee->id)
            ->where('link', "/events/{$eventId}")
            ->count());
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

    public function test_cancel_notifies_rsvp_and_waitlisted_users_after_statuses_change(): void
    {
        $organizer = $this->authenticatedUser();
        $attendee = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'event-cancel-attendee-' . uniqid('', true) . '@example.test',
        ]);
        $waitlisted = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'email' => 'event-cancel-waitlisted-' . uniqid('', true) . '@example.test',
        ]);
        $eventId = $this->createEvent((int) $organizer->id);

        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $attendee->id,
            'status' => 'going',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_waitlist')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => $waitlisted->id,
            'position' => 1,
            'status' => 'waiting',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->setDailyNotificationPreference((int) $attendee->id);
        $this->setDailyNotificationPreference((int) $waitlisted->id);

        $response = $this->apiPost("/v2/events/{$eventId}/cancel", [
            'reason' => 'Weather',
        ]);

        $response->assertStatus(200);
        $this->assertSame('cancelled', DB::table('event_rsvps')->where('event_id', $eventId)->where('user_id', $attendee->id)->value('status'));
        $this->assertSame('cancelled', DB::table('event_waitlist')->where('event_id', $eventId)->where('user_id', $waitlisted->id)->value('status'));
        $this->assertSame(2, DB::table('notifications')->where('tenant_id', $this->testTenantId)->where('link', "/events/{$eventId}")->where('type', 'event')->count());
        $this->assertSame(2, DB::table('notification_queue')->where('tenant_id', $this->testTenantId)->where('link', "/events/{$eventId}")->where('activity_type', 'event_cancellation')->count());
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
    // NEARBY — Radius filter actually filters (regression guard)
    // ================================================================

    public function test_index_with_near_lat_excludes_distant_events(): void
    {
        // Dublin city centre ≈ 53.3498, -6.2603
        // Cork city centre ≈ 51.8985, -8.4756  (~258 km away)
        $user = $this->authenticatedUser();

        $nearId = $this->createEvent($user->id, [
            'title'     => 'Near Dublin event',
            'latitude'  => 53.3490,
            'longitude' => -6.2600,
        ]);

        $farId = $this->createEvent($user->id, [
            'title'     => 'Far Cork event',
            'latitude'  => 51.8985,
            'longitude' => -8.4756,
        ]);

        // 10 km radius centred on Dublin — Cork must be excluded
        $response = $this->apiGet('/v2/events?near_lat=53.3498&near_lng=-6.2603&radius_km=10');

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $ids = $data->pluck('id');
        $this->assertTrue($ids->contains($nearId), 'Nearby event should be included in results');
        $this->assertFalse($ids->contains($farId), 'Distant event (Cork) must be excluded by radius filter');
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

    // ================================================================
    // RECURRING — series collapse + image cascade
    // ================================================================

    public function test_get_all_collapses_recurring_series_to_next_occurrence(): void
    {
        $user = $this->authenticatedUser();

        // A standalone event must still pass through un-collapsed.
        $standalone = $this->createEvent($user->id, [
            'start_time' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        // A recurring series: template (soonest) + two future occurrences.
        $templateId = $this->createEvent($user->id, [
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'is_recurring_template' => 1,
        ]);
        $occ1 = $this->createEvent($user->id, [
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'parent_event_id' => $templateId,
        ]);
        $occ2 = $this->createEvent($user->id, [
            'start_time' => now()->addDays(21)->format('Y-m-d H:i:s'),
            'parent_event_id' => $templateId,
        ]);

        // Scope to this user so baseline fixtures in the test DB don't interfere.
        $result = EventService::getAll(['when' => 'upcoming', 'limit' => 50, 'user_id' => $user->id]);
        $ids = array_column($result['items'], 'id');

        // Exactly one representative for the series (the soonest = the template),
        // plus the standalone event.
        $this->assertContains($standalone, $ids);
        $this->assertContains($templateId, $ids);
        $this->assertNotContains($occ1, $ids);
        $this->assertNotContains($occ2, $ids);
        $this->assertCount(2, $result['items']);

        $rep = collect($result['items'])->firstWhere('id', $templateId);
        $this->assertTrue($rep['is_series'] ?? false);
        $this->assertSame(3, $rep['series_count'] ?? null);
    }

    public function test_update_image_cascades_cover_to_whole_series(): void
    {
        $user = $this->authenticatedUser();

        $templateId = $this->createEvent($user->id, [
            'start_time' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'is_recurring_template' => 1,
        ]);
        $occ1 = $this->createEvent($user->id, [
            'start_time' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'parent_event_id' => $templateId,
        ]);
        $occ2 = $this->createEvent($user->id, [
            'start_time' => now()->addDays(21)->format('Y-m-d H:i:s'),
            'parent_event_id' => $templateId,
        ]);

        // Uploading the cover to the template (as the frontend does) must land on
        // every occurrence, not just the row the upload targeted.
        $this->assertTrue(EventService::updateImage($templateId, $user->id, '/uploads/cover.jpg'));

        $this->assertSame('/uploads/cover.jpg', DB::table('events')->where('id', $templateId)->value('cover_image'));
        $this->assertSame('/uploads/cover.jpg', DB::table('events')->where('id', $occ1)->value('cover_image'));
        $this->assertSame('/uploads/cover.jpg', DB::table('events')->where('id', $occ2)->value('cover_image'));
    }
}
