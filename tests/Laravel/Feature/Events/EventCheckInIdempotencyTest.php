<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: event check-in mints a credit transfer (organizer -> attendee),
 * and must do so AT MOST ONCE per attendee. The controller's "already checked
 * in" guard was a TOCTOU read taken BEFORE the credit transaction, so two
 * racing/duplicate check-ins could each mint — double-charging the organizer
 * and double-crediting the attendee. The transfer now sits behind an atomic
 * RSVP-status claim inside the transaction, so a second check-in is collapsed.
 */
class EventCheckInIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private function userInTenant(float $balance): User
    {
        $u = User::factory()->forTenant($this->testTenantId)->create(['balance' => $balance]);
        DB::table('users')->where('id', $u->id)->update(['tenant_id' => $this->testTenantId]);

        return $u;
    }

    private function eventOwnedBy(int $organizerId): int
    {
        // Insert directly — the EventFactory targets columns (start_date/end_date)
        // that don't exist in the nexus_test schema (which uses start_time/end_time).
        return (int) DB::table('events')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $organizerId,
            'title'       => 'Check-in idempotency event',
            'description' => 'Event used to exercise the check-in credit path.',
            'start_time'  => now(),
            'end_time'    => now()->addHours(2),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function test_double_check_in_credits_exactly_once(): void
    {
        $organizer = $this->userInTenant(100);
        $attendee  = $this->userInTenant(0);
        $eventId   = $this->eventOwnedBy((int) $organizer->id);

        DB::table('event_rsvps')->insert([
            'event_id'   => $eventId,
            'user_id'    => $attendee->id,
            'status'     => 'going',
            'tenant_id'  => $this->testTenantId,
            'created_at' => now(),
        ]);

        $r1 = TenantContext::runForTenant($this->testTenantId, fn () =>
            EventService::recordCheckInCredit($eventId, (int) $attendee->id, (int) $organizer->id, 2.0, 'Community Cleanup'));
        $r2 = TenantContext::runForTenant($this->testTenantId, fn () =>
            EventService::recordCheckInCredit($eventId, (int) $attendee->id, (int) $organizer->id, 2.0, 'Community Cleanup'));

        $this->assertSame('credited', $r1, 'first check-in must mint');
        $this->assertSame('already', $r2, 'second check-in must be collapsed (no second mint)');

        $this->assertEqualsWithDelta(98, (float) DB::table('users')->where('id', $organizer->id)->value('balance'), 0.001, 'organizer debited exactly once');
        $this->assertEqualsWithDelta(2, (float) DB::table('users')->where('id', $attendee->id)->value('balance'), 0.001, 'attendee credited exactly once');

        $txCount = DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'event_checkin')
            ->where('sender_id', $organizer->id)
            ->where('receiver_id', $attendee->id)
            ->count();
        $this->assertSame(1, $txCount, 'exactly one event_checkin transaction must exist');
    }

    public function test_insufficient_organizer_balance_does_not_mint(): void
    {
        $organizer = $this->userInTenant(1); // cannot cover 2h
        $attendee  = $this->userInTenant(0);
        $eventId   = $this->eventOwnedBy((int) $organizer->id);

        DB::table('event_rsvps')->insert([
            'event_id'   => $eventId,
            'user_id'    => $attendee->id,
            'status'     => 'going',
            'tenant_id'  => $this->testTenantId,
            'created_at' => now(),
        ]);

        $r = TenantContext::runForTenant($this->testTenantId, fn () =>
            EventService::recordCheckInCredit($eventId, (int) $attendee->id, (int) $organizer->id, 2.0, 'Event'));

        $this->assertSame('insufficient', $r);
        $this->assertEqualsWithDelta(1, (float) DB::table('users')->where('id', $organizer->id)->value('balance'), 0.001);
        $this->assertEqualsWithDelta(0, (float) DB::table('users')->where('id', $attendee->id)->value('balance'), 0.001);
        // RSVP must NOT have been flipped to attended when nothing was minted.
        $this->assertSame('going', DB::table('event_rsvps')->where('event_id', $eventId)->where('user_id', $attendee->id)->value('status'));
    }
}
