<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EventNotificationService;
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
        DB::shouldReceive('table->where->where->whereIn->distinct->pluck->map->all')->andReturn([]);

        $result = $this->service->notifyAttendees(2, 1, 'Hello');
        $this->assertEquals(0, $result);
    }

    public function test_notifyAttendees_skips_organizer(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test Event', 'user_id' => 10];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        DB::shouldReceive('table->where->where->whereIn->distinct->pluck->map->all')
            ->andReturn([10, 20, 30]); // 10 is organizer

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
        DB::shouldReceive('table->where->where->whereIn->distinct->pluck->map->all')
            ->andReturn([10, 20]); // RSVP users
        DB::shouldReceive('table->where->where->where->distinct->pluck->map->all')
            ->andReturn([30]); // Waitlisted

        $this->notificationAlias->shouldReceive('create')->times(3);

        $result = $this->service->notifyCancellation(2, 1, 'Weather issues');
        $this->assertEquals(3, $result);
    }

    public function test_notifyCancellation_includes_reason_in_message(): void
    {
        $event = (object) ['id' => 1, 'title' => 'Test Event'];
        DB::shouldReceive('table->where->where->select->first')->andReturn($event);
        DB::shouldReceive('table->where->where->whereIn->distinct->pluck->map->all')
            ->andReturn([10]);
        DB::shouldReceive('table->where->where->where->distinct->pluck->map->all')
            ->andReturn([]);

        $this->notificationAlias->shouldReceive('create')
            ->withArgs(function ($args) {
                return str_contains($args['message'], 'Reason: Weather');
            })
            ->once();

        $this->service->notifyCancellation(2, 1, 'Weather');
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
        DB::shouldReceive('table->where->where->whereIn->distinct->pluck->map->all')
            ->andReturn([20, 30]);

        $this->notificationAlias->shouldReceive('create')->twice();

        $this->service->notifyEventUpdated(1, ['start_time' => '2026-04-01 14:00']);
    }
}
