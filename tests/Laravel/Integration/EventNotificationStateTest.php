<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\User;
use App\Core\TenantContext;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class EventNotificationStateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rsvp_state_changes_only_when_status_changes(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        TenantContext::setById($this->testTenantId);
        $eventId = $this->createEvent((int) $user->id);

        $this->assertTrue(EventService::rsvp($eventId, (int) $user->id, 'going'));
        $this->assertTrue(EventService::wasLastRsvpChanged());

        $this->assertTrue(EventService::rsvp($eventId, (int) $user->id, 'going'));
        $this->assertFalse(EventService::wasLastRsvpChanged());

        $this->assertTrue(EventService::rsvp($eventId, (int) $user->id, 'interested'));
        $this->assertTrue(EventService::wasLastRsvpChanged());
    }

    public function test_event_update_tracks_only_persisted_meaningful_changes(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        TenantContext::setById($this->testTenantId);
        $eventId = $this->createEvent((int) $organizer->id, [
            'title' => 'Original title',
            'location' => 'Community hall',
            'start_time' => now()->addDays(5)->setSecond(0)->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue(EventService::update($eventId, (int) $organizer->id, [
            'description' => 'A harmless description edit.',
        ]));
        $this->assertSame([], EventService::getLastMeaningfulUpdateChanges());

        $this->assertTrue(EventService::update($eventId, (int) $organizer->id, [
            'title' => 'Original title',
        ]));
        $this->assertSame([], EventService::getLastMeaningfulUpdateChanges());

        $this->assertTrue(EventService::update($eventId, (int) $organizer->id, [
            'location' => 'Library room',
        ]));
        $this->assertSame(['location' => 'Library room'], EventService::getLastMeaningfulUpdateChanges());
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createEvent(int $organizerId, array $overrides = []): int
    {
        return (int) DB::table('events')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Reliability test event',
            'description' => 'Event notification reliability coverage.',
            'location' => 'Main room',
            'start_time' => now()->addDays(3)->setSecond(0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(3)->addHour()->setSecond(0)->format('Y-m-d H:i:s'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
