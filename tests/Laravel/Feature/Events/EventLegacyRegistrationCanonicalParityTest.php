<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Models\User;
use App\Services\EventRegistrationService;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class EventLegacyRegistrationCanonicalParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.key', 'base64:HfQEDtbtr90JIXhsaAhSFWnzIo1f31VZ2e5qLqKKnls=');
        Config::set('events.notification_delivery.mode', 'direct');
        Config::set('events.registration.default_capacity_pool_key', 'event');
        Config::set('events.registration.legacy_dual_read', true);
        Config::set('events.registration.legacy_dual_write', true);
        Config::set('events.registration.timed_waitlist_offers_enabled', false);
        Config::set('event_waitlist.envelope.active_key_version', 'legacy-parity-v1');
        Config::set('event_waitlist.envelope.active_key', null);
        Config::set('event_waitlist.envelope.fallback_to_app_key', true);
    }

    public function test_legacy_rsvp_status_cycles_have_one_authoritative_versioned_history(): void
    {
        $organizer = $this->member('Legacy Organizer');
        $member = $this->member('Legacy Member', ['xp' => 0]);
        $eventId = $this->event((int) $organizer->id, 5);
        Sanctum::actingAs($member, ['*']);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->assertRegistrationLedger($eventId, (int) $member->id, 'confirmed', 1, 1);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'interested'])->assertOk();
        self::assertSame('interested', DB::table('event_rsvps')
            ->where('event_id', $eventId)->where('user_id', $member->id)->value('status'));
        $this->assertRegistrationLedger($eventId, (int) $member->id, 'cancelled', 2, 2);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->assertRegistrationLedger($eventId, (int) $member->id, 'confirmed', 3, 3);
        $this->apiDelete("/v2/events/{$eventId}/rsvp")->assertNoContent();
        $this->apiDelete("/v2/events/{$eventId}/rsvp")->assertNoContent();
        self::assertFalse(DB::table('event_rsvps')
            ->where('event_id', $eventId)->where('user_id', $member->id)->exists());
        $this->assertRegistrationLedger($eventId, (int) $member->id, 'cancelled', 4, 4);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])->assertOk();
        $this->assertRegistrationLedger($eventId, (int) $member->id, 'confirmed', 5, 5);
        self::assertSame(1, DB::table('user_xp_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $member->id)
            ->where('action', 'attend_event')
            ->where('source_reference', 'event:' . $eventId)
            ->count());
        self::assertSame(
            GamificationService::XP_VALUES['attend_event'],
            (int) DB::table('users')->where('id', $member->id)->value('xp'),
        );
    }

    public function test_legacy_full_and_waitlist_endpoints_write_one_canonical_queue_ledger(): void
    {
        $organizer = $this->member('Legacy Waitlist Organizer');
        $holder = $this->member('Legacy Waitlist Holder');
        $waiter = $this->member('Legacy Waitlist Subject');
        $eventId = $this->event((int) $organizer->id, 1);
        (new EventRegistrationService())->confirm(
            $eventId,
            (int) $holder->id,
            $holder,
            'legacy-parity-fill-capacity',
        );
        Sanctum::actingAs($waiter, ['*']);

        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waitlisted');
        $this->apiPost("/v2/events/{$eventId}/rsvp", ['status' => 'going'])
            ->assertOk()
            ->assertJsonPath('data.status', 'waitlisted');
        $this->apiPost("/v2/events/{$eventId}/waitlist")->assertOk();
        self::assertSame('waiting', DB::table('event_waitlist_entries')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->value('queue_state'));
        self::assertSame(1, DB::table('event_waitlist_entry_history')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)->where('action', 'event.waitlist.joined')->count());

        $this->apiDelete("/v2/events/{$eventId}/waitlist")->assertNoContent();
        $this->apiDelete("/v2/events/{$eventId}/waitlist")->assertNoContent();
        self::assertSame('cancelled', DB::table('event_waitlist_entries')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->value('queue_state'));
        self::assertSame(2, DB::table('event_waitlist_entry_history')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)->where('action', 'event.waitlist.withdrawn')->count());

        $this->apiPost("/v2/events/{$eventId}/waitlist")->assertOk();
        self::assertSame('waiting', DB::table('event_waitlist_entries')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->value('queue_state'));
        self::assertSame(3, DB::table('event_waitlist_entry_history')
            ->where('event_id', $eventId)->where('user_id', $waiter->id)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)->where('action', 'event.waitlist.rejoined')->count());
    }

    public function test_reusing_an_explicit_key_after_a_state_cycle_is_rejected_not_silently_replayed(): void
    {
        $organizer = $this->member('Legacy Key Organizer');
        $member = $this->member('Legacy Key Member');
        $eventId = $this->event((int) $organizer->id, 5);
        Sanctum::actingAs($member, ['*']);

        $this->apiPost(
            "/v2/events/{$eventId}/rsvp",
            ['status' => 'going'],
            ['Idempotency-Key' => 'explicit-cycle-key'],
        )->assertOk();
        $this->apiPost(
            "/v2/events/{$eventId}/rsvp",
            ['status' => 'interested'],
            ['Idempotency-Key' => 'explicit-withdraw-key'],
        )->assertOk();
        $this->apiPost(
            "/v2/events/{$eventId}/rsvp",
            ['status' => 'going'],
            ['Idempotency-Key' => 'explicit-cycle-key'],
        )->assertStatus(422)
            ->assertJsonPath('errors.0.code', 'RSVP_FAILED');
        self::assertSame('cancelled', DB::table('event_registrations')
            ->where('event_id', $eventId)->where('user_id', $member->id)->value('registration_state'));
        self::assertSame('interested', DB::table('event_rsvps')
            ->where('event_id', $eventId)->where('user_id', $member->id)->value('status'));
    }

    private function assertRegistrationLedger(
        int $eventId,
        int $userId,
        string $state,
        int $version,
        int $historyCount,
    ): void {
        $registration = DB::table('event_registrations')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();
        self::assertSame($state, $registration?->registration_state);
        self::assertSame($version, (int) $registration?->registration_version);
        self::assertSame($historyCount, DB::table('event_registration_history')
            ->where('event_id', $eventId)->where('user_id', $userId)->count());
        self::assertSame($historyCount, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'like', 'event.registration.%')
            ->count());
    }

    /** @param array<string,mixed> $overrides */
    private function member(string $name, array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'name' => $name,
            'first_name' => $name,
            'role' => 'member',
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function event(int $organizerId, int $capacity): int
    {
        $start = now()->addWeek();

        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Legacy canonical parity fixture',
            'description' => 'Legacy response and canonical ledger fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'legacy-parity:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'max_attendees' => $capacity,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
