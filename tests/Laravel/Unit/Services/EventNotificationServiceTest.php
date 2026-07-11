<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EventNotificationService;
use App\Services\SafeguardingInteractionPolicy;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class EventNotificationServiceTest extends TestCase
{
    private EventNotificationService $service;
    private $notificationAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class);
        $this->service = new EventNotificationService();
    }

    // =========================================================================
    // notifyAttendees()
    // =========================================================================

    public function test_notifyAttendees_returns_zero_when_event_not_found(): void
    {
        DB::shouldReceive('table->where->where->select->first')->andReturn(null);

        $result = $this->service->notifyAttendees(2, 999, 'Hello');
        $this->assertEquals(0, $result);
    }

    public function test_notifyAttendees_returns_zero_when_no_attendees(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test Event', 'user_id' => 10];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        DB::shouldReceive('table->join->where->where->whereIn->select->distinct->get')
            ->andReturn(collect([]));

        $result = $this->service->notifyAttendees(2, 1, 'Hello');
        $this->assertEquals(0, $result);
    }

    public function test_notifyAttendees_skips_organizer(): void
    {
        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertManyLocalContactsAllowed')
            ->once()
            ->with(10, [20, 30], 2, 'event_broadcast');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $event = (object) ['id' => 1, 'title' => 'Test Event', 'user_id' => 10];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        // Attendees query: table('event_rsvps as r')->join->where->where->whereIn->select->distinct->get()
        // returns a Collection of user objects (10 is the organizer and must be skipped).
        DB::shouldReceive('table->join->where->where->whereIn->select->distinct->get')
            ->andReturn(collect([
                (object) ['user_id' => 10, 'email' => null, 'name' => 'Org', 'first_name' => 'Org', 'preferred_language' => 'en'],
                (object) ['user_id' => 20, 'email' => null, 'name' => 'A', 'first_name' => 'A', 'preferred_language' => 'en'],
                (object) ['user_id' => 30, 'email' => null, 'name' => 'B', 'first_name' => 'B', 'preferred_language' => 'en'],
            ]));

        $this->notificationAlias->shouldReceive('create')->twice(); // 20 and 30 only

        $result = $this->service->notifyAttendees(2, 1, 'Event updated');
        $this->assertEquals(2, $result);
    }

    public function test_notifyAttendees_catches_exceptions(): void
    {
        DB::shouldReceive('table->where->where->select->first')
            ->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->notifyAttendees(2, 1, 'Hello');
        $this->assertEquals(0, $result);
    }

    // =========================================================================
    // notifyCancellation()
    // =========================================================================

    public function test_notifyCancellation_returns_zero_when_event_not_found(): void
    {
        DB::shouldReceive('table->where->where->select->first')->andReturn(null);

        $result = $this->service->notifyCancellation(2, 999);
        $this->assertEquals(0, $result);
    }

    public function test_notifyCancellation_notifies_rsvp_and_waitlisted_users(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test Event'];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        // RSVP users: table('event_rsvps as r')->join->where->where->whereIn->select->distinct->get()
        DB::shouldReceive('table->join->where->where->whereIn->select->distinct->get')
            ->andReturn(collect([
                (object) ['user_id' => 10, 'email' => null, 'name' => 'A', 'first_name' => 'A', 'preferred_language' => 'en'],
                (object) ['user_id' => 20, 'email' => null, 'name' => 'B', 'first_name' => 'B', 'preferred_language' => 'en'],
            ]));
        // Waitlisted users: table('event_waitlist as w')->join->where->where->where->select->distinct->get()
        DB::shouldReceive('table->join->where->where->where->select->distinct->get')
            ->andReturn(collect([
                (object) ['user_id' => 30, 'email' => null, 'name' => 'C', 'first_name' => 'C', 'preferred_language' => 'en'],
            ]));

        $this->notificationAlias->shouldReceive('create')->times(3);

        $result = $this->service->notifyCancellation(2, 1, 'Weather issues');
        $this->assertEquals(3, $result);
    }

    public function test_notifyCancellation_includes_reason_in_message(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test Event'];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        DB::shouldReceive('table->join->where->where->whereIn->select->distinct->get')
            ->andReturn(collect([
                (object) ['user_id' => 10, 'email' => null, 'name' => 'A', 'first_name' => 'A', 'preferred_language' => 'en'],
            ]));
        DB::shouldReceive('table->join->where->where->where->select->distinct->get')
            ->andReturn(collect([]));

        $this->notificationAlias->shouldReceive('create')
            ->withArgs(function ($args) {
                return is_array($args) && str_contains($args['message'], 'Reason: Weather');
            })
            ->once();

        $result = $this->service->notifyCancellation(2, 1, 'Weather');
        $this->assertEquals(1, $result);
    }

    // =========================================================================
    // notifyRsvp()
    // =========================================================================

    public function test_notifyRsvp_ignores_non_going_or_interested_status(): void
    {
        $this->notificationAlias->shouldReceive('create')->never();

        $this->service->notifyRsvp(1, 5, 'declined');
    }

    public function test_notifyRsvp_does_not_notify_self_rsvp(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test', 'user_id' => 5];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);

        $this->notificationAlias->shouldReceive('create')->never();

        $this->service->notifyRsvp(1, 5, 'going'); // user 5 is the organizer
    }

    // =========================================================================
    // notifyEventUpdated()
    // =========================================================================

    public function test_notifyEventUpdated_ignores_non_meaningful_changes(): void
    {
        DB::shouldReceive('table')->never();

        $this->service->notifyEventUpdated(1, ['description' => 'Updated desc']);
    }

    public function test_notifyEventUpdated_notifies_on_time_change(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test', 'start_time' => '2026-04-01 10:00', 'location' => 'Cork', 'user_id' => 10];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        // Attendees (organizer 10 excluded): 20 and 30 receive a notification.
        DB::shouldReceive('table->join->where->where->whereIn->select->distinct->get')
            ->andReturn(collect([
                (object) ['user_id' => 20, 'email' => null, 'name' => 'A', 'first_name' => 'A', 'preferred_language' => 'en'],
                (object) ['user_id' => 30, 'email' => null, 'name' => 'B', 'first_name' => 'B', 'preferred_language' => 'en'],
            ]));

        $this->notificationAlias->shouldReceive('create')->twice();

        $this->service->notifyEventUpdated(1, ['start_time' => '2026-04-01 14:00']);
    }

    // =========================================================================
    // buildReminderMessage() — locale-aware date + location rendering
    // =========================================================================

    /**
     * Invoke the private buildReminderMessage() under a given locale.
     */
    private function buildReminderMessageIn(string $locale, object $event, string $type = '24h'): string
    {
        $method = new \ReflectionMethod(EventNotificationService::class, 'buildReminderMessage');

        return \App\I18n\LocaleContext::withLocale(
            $locale,
            fn () => $method->invoke($this->service, $event, $type)
        );
    }

    public function test_reminder_message_renders_date_in_recipient_locale(): void
    {
        // 2026-07-15 is a Wednesday ("Mittwoch" in German, "Juli" for the month).
        $event = (object) [
            'id' => 1,
            'title' => 'Test Event',
            'start_time' => '2026-07-15 15:00:00',
            'is_online' => 0,
            'online_link' => null,
            'location' => null,
        ];

        $german = $this->buildReminderMessageIn('de', $event);
        $this->assertStringContainsString('Mittwoch', $german, 'German recipients must get a German weekday name');
        $this->assertStringNotContainsString('Wednesday', $german, 'English weekday name must not leak into German reminders');

        $english = $this->buildReminderMessageIn('en', $event);
        $this->assertStringContainsString('Wednesday', $english);
    }

    public function test_reminder_message_location_connectors_come_from_translations(): void
    {
        $withLocation = (object) [
            'id' => 1,
            'title' => 'Test Event',
            'start_time' => '2026-07-15 15:00:00',
            'is_online' => 0,
            'online_link' => null,
            'location' => 'Cork',
        ];
        $message = $this->buildReminderMessageIn('en', $withLocation);
        $this->assertStringContainsString(
            ' ' . __('notifications.event_reminder_location_at', ['location' => 'Cork']),
            $message
        );

        $online = (object) [
            'id' => 1,
            'title' => 'Test Event',
            'start_time' => '2026-07-15 15:00:00',
            'is_online' => 1,
            'online_link' => 'https://example.org/meet',
            'location' => null,
        ];
        $message = $this->buildReminderMessageIn('en', $online);
        $this->assertStringContainsString(
            ' ' . __('notifications.event_reminder_location_online'),
            $message
        );
    }
}
