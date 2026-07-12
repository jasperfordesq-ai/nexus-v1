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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the fail-closed attendance-credit boundary.
 *
 * Check-in records attendance, but it must never charge the acting organiser
 * or administrator while the immutable event-credit claim ledger is absent.
 */
class EventCheckInIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private function userInTenant(float $balance, array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'balance' => $balance,
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function eventOwnedBy(int $organizerId): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $organizerId,
            'title' => 'Check-in containment event',
            'description' => 'Event used to exercise the attendance boundary.',
            'start_time' => now()->subMinutes(10),
            'end_time' => now()->addMinutes(110),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rsvp(int $eventId, int $attendeeId, string $status = 'going'): void
    {
        DB::table('event_rsvps')->insert([
            'event_id' => $eventId,
            'user_id' => $attendeeId,
            'status' => $status,
            'tenant_id' => $this->testTenantId,
            'created_at' => now(),
        ]);
    }

    private function assertNoCreditTransfer(User $actor, User $attendee, float $actorBalance): void
    {
        $this->assertEqualsWithDelta(
            $actorBalance,
            (float) DB::table('users')->where('id', $actor->id)->value('balance'),
            0.001
        );
        $this->assertEqualsWithDelta(
            0.0,
            (float) DB::table('users')->where('id', $attendee->id)->value('balance'),
            0.001
        );
        $this->assertSame(0, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'event_checkin')
            ->where('sender_id', $actor->id)
            ->where('receiver_id', $attendee->id)
            ->count());
    }

    public function test_service_credit_writer_is_disabled_by_default_without_mutating_state(): void
    {
        Config::set('events.attendance_credit_mode', 'off');
        $organizer = $this->userInTenant(100);
        $attendee = $this->userInTenant(0);
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $this->rsvp($eventId, (int) $attendee->id);

        $outcome = TenantContext::runForTenant($this->testTenantId, fn (): string =>
            EventService::recordCheckInCredit(
                $eventId,
                (int) $attendee->id,
                (int) $organizer->id,
                2.0,
                'Community Cleanup'
            )
        );

        $this->assertSame('credit_disabled', $outcome);
        $this->assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
        $this->assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
        $this->assertNoCreditTransfer($organizer, $attendee, 100.0);
    }

    public function test_unknown_credit_mode_fails_closed(): void
    {
        Config::set('events.attendance_credit_mode', 'legacy');
        $organizer = $this->userInTenant(100);
        $attendee = $this->userInTenant(0);
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $this->rsvp($eventId, (int) $attendee->id);

        $outcome = TenantContext::runForTenant($this->testTenantId, fn (): string =>
            EventService::recordCheckInCredit(
                $eventId,
                (int) $attendee->id,
                (int) $organizer->id,
                2.0,
                'Community Cleanup'
            )
        );

        $this->assertSame('credit_disabled', $outcome);
        $this->assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
        $this->assertNoCreditTransfer($organizer, $attendee, 100.0);
    }

    public function test_organizer_check_in_records_attendance_without_crediting_hours_or_wallets(): void
    {
        Config::set('events.attendance_credit_mode', 'off');
        $organizer = $this->userInTenant(100);
        $attendee = $this->userInTenant(0);
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $this->rsvp($eventId, (int) $attendee->id);
        Sanctum::actingAs($organizer, ['*']);

        $response = $this->apiPost("/v2/events/{$eventId}/attendees/{$attendee->id}/check-in");

        $response->assertOk()
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.credit_status', 'disabled')
            ->assertJsonPath('data.hours_credited', null);
        $this->assertSame('attended', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
        $this->assertNull(DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('hours_credited'));
        $this->assertNoCreditTransfer($organizer, $attendee, 100.0);
    }

    public function test_admin_check_in_never_uses_admin_as_implicit_payer(): void
    {
        Config::set('events.attendance_credit_mode', 'off');
        $organizer = $this->userInTenant(100);
        $admin = $this->userInTenant(50, ['role' => 'admin']);
        $attendee = $this->userInTenant(0);
        $eventId = $this->eventOwnedBy((int) $organizer->id);
        $this->rsvp($eventId, (int) $attendee->id);
        Sanctum::actingAs($admin, ['*']);

        $response = $this->apiPost("/v2/events/{$eventId}/attendees/{$attendee->id}/check-in");

        $response->assertOk()
            ->assertJsonPath('data.credit_status', 'disabled')
            ->assertJsonPath('data.hours_credited', null);
        $this->assertSame(1, DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->count());
        $this->assertNoCreditTransfer($admin, $attendee, 50.0);
        $this->assertEqualsWithDelta(
            100.0,
            (float) DB::table('users')->where('id', $organizer->id)->value('balance'),
            0.001
        );
    }
}
