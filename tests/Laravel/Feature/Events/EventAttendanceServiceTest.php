<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Exceptions\EventAttendanceException;
use App\Models\EventAttendanceActivity;
use App\Models\EventAttendanceCreditClaim;
use App\Models\User;
use App\Services\EventAttendanceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Laravel\TestCase;

final class EventAttendanceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EventAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        Config::set('events.attendance_credit_mode', 'off');
        $this->service = app(EventAttendanceService::class);
    }

    public function test_records_one_durable_attendance_fact_activity_and_outbox_without_credit(): void
    {
        $organizer = $this->user();
        $attendee = $this->user(['balance' => 0]);
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);

        $result = $this->record(
            $eventId,
            (int) $attendee->id,
            $organizer,
            null,
            'Arrived at the registration desk.',
            'device-request-1',
        );

        self::assertSame('checked_in', $result->outcome);
        self::assertSame('disabled', $result->creditStatus);
        self::assertNotNull($result->activityId);
        self::assertNotNull($result->outboxId);
        self::assertSame('attended', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));

        $attendance = DB::table('event_attendance')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->first();
        self::assertNotNull($attendance);
        self::assertSame('checked_in', $attendance->attendance_status);
        self::assertSame(1, (int) $attendance->attendance_version);
        self::assertNull($attendance->hours_credited);
        self::assertSame((int) $organizer->id, (int) $attendance->checked_in_by);

        self::assertDatabaseHas('event_attendance_activity', [
            'id' => $result->activityId,
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'attendance_id' => $attendance->id,
            'user_id' => $attendee->id,
            'actor_user_id' => $organizer->id,
            'attendance_version' => 1,
            'action' => 'check_in',
            'to_status' => 'checked_in',
        ]);
        self::assertDatabaseHas('event_domain_outbox', [
            'id' => $result->outboxId,
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'action' => 'event.attendance.recorded',
        ]);
        self::assertSame(0, DB::table('event_attendance_credit_claims')->count());
        self::assertSame(0, DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('transaction_type', 'event_checkin')
            ->count());
    }

    public function test_hours_override_fails_closed_without_side_effects(): void
    {
        $organizer = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);

        $this->assertRejected(
            'event_attendance_hours_unavailable',
            fn () => $this->record(
                $eventId,
                (int) $attendee->id,
                $organizer,
                2.0,
            ),
        );
        self::assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(0, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
    }

    public function test_canonical_registration_is_authoritative_over_legacy_projection(): void
    {
        $organizer = $this->user();
        $canonicalOnly = $this->user();
        $cancelled = $this->user();
        $canonicalEvent = $this->event($organizer);
        $staleLegacyEvent = $this->event($organizer);
        Config::set('events.registration.legacy_dual_write', false);
        $this->canonicalRegistration($canonicalEvent, $canonicalOnly, 'confirmed');

        $canonicalResult = $this->record(
            $canonicalEvent,
            (int) $canonicalOnly->id,
            $organizer,
        );
        self::assertSame('checked_in', $canonicalResult->outcome);
        self::assertSame('confirmed', DB::table('event_registrations')
            ->where('event_id', $canonicalEvent)
            ->where('user_id', $canonicalOnly->id)
            ->value('registration_state'));
        self::assertFalse(DB::table('event_rsvps')
            ->where('event_id', $canonicalEvent)
            ->where('user_id', $canonicalOnly->id)
            ->exists());

        $this->canonicalRegistration($staleLegacyEvent, $cancelled, 'cancelled');
        $this->rsvp($staleLegacyEvent, $cancelled);
        $this->assertRejected(
            'event_attendance_registration_required',
            fn () => $this->record(
                $staleLegacyEvent,
                (int) $cancelled->id,
                $organizer,
            ),
        );
        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $staleLegacyEvent)
            ->where('user_id', $cancelled->id)
            ->value('status'));
        self::assertFalse(DB::table('event_attendance')
            ->where('event_id', $staleLegacyEvent)
            ->where('user_id', $cancelled->id)
            ->exists());
    }

    public function test_legacy_fallback_accepts_only_going_or_attended(): void
    {
        $organizer = $this->user();
        foreach (['going', 'attended'] as $status) {
            $attendee = $this->user();
            $eventId = $this->event($organizer);
            $this->rsvp($eventId, $attendee, $status);
            self::assertSame('checked_in', $this->record(
                $eventId,
                (int) $attendee->id,
                $organizer,
            )->outcome);
        }

        foreach (['interested', 'invited'] as $status) {
            $attendee = $this->user();
            $eventId = $this->event($organizer);
            $this->rsvp($eventId, $attendee, $status);
            $this->assertRejected(
                'event_attendance_registration_required',
                fn () => $this->record(
                    $eventId,
                    (int) $attendee->id,
                    $organizer,
                ),
            );
            self::assertFalse(DB::table('event_attendance')
                ->where('event_id', $eventId)
                ->where('user_id', $attendee->id)
                ->exists());
        }
    }

    public function test_repeat_and_different_actor_calls_return_original_fact_without_mutation(): void
    {
        $organizer = $this->user();
        $admin = $this->user(['role' => 'admin']);
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);

        $first = $this->record($eventId, (int) $attendee->id, $organizer);
        $original = DB::table('event_attendance')->where('id', $first->attendance->id)->first();
        $replay = $this->record(
            $eventId,
            (int) $attendee->id,
            $admin,
            null,
            'This must not overwrite the original record.',
            'another-request',
        );

        self::assertSame('already_checked_in', $replay->outcome);
        self::assertNull($replay->activityId);
        self::assertNull($replay->outboxId);
        $stored = DB::table('event_attendance')->where('id', $first->attendance->id)->first();
        self::assertSame($original->checked_in_at, $stored->checked_in_at);
        self::assertSame($original->checked_in_by, $stored->checked_in_by);
        self::assertSame($original->notes, $stored->notes);
        self::assertSame(1, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
        self::assertSame(1, DB::table('event_domain_outbox')
            ->where('event_id', $eventId)
            ->where('action', 'event.attendance.recorded')
            ->count());
    }

    public function test_authorization_registration_lifecycle_and_occurrence_guards_fail_without_writes(): void
    {
        $organizer = $this->user();
        $outsider = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);

        $this->assertRejected(
            'event_attendance_authorization_denied',
            fn () => $this->record($eventId, (int) $attendee->id, $outsider),
        );
        $this->assertRejected(
            'event_attendance_registration_required',
            fn () => $this->record($eventId, (int) $attendee->id, $organizer),
        );

        $this->rsvp($eventId, $attendee);
        DB::table('events')->where('id', $eventId)->update([
            'status' => 'cancelled',
            'operational_status' => 'cancelled',
        ]);
        $this->assertRejected(
            'event_attendance_event_unavailable',
            fn () => $this->record($eventId, (int) $attendee->id, $organizer),
        );

        DB::table('events')->where('id', $eventId)->update([
            'status' => 'active',
            'operational_status' => 'scheduled',
            'is_recurring_template' => 1,
        ]);
        $this->assertRejected(
            'event_attendance_concrete_occurrence_required',
            fn () => $this->record($eventId, (int) $attendee->id, $organizer),
        );

        self::assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(0, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
    }

    public function test_outbox_identity_conflict_rolls_back_attendance_rsvp_and_activity(): void
    {
        $organizer = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);
        $key = "event:{$this->testTenantId}:{$eventId}:attendance:{$attendee->id}:v1";
        DB::table('event_domain_outbox')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'aggregate_version' => 1,
            'action' => 'event.attendance.conflict',
            'idempotency_key' => $key,
            'production_mode' => 'direct',
            'status' => 'direct',
            'payload' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'processed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->record($eventId, (int) $attendee->id, $organizer);
            self::fail('Conflicting attendance outbox identity did not abort the transaction.');
        } catch (LogicException $exception) {
            self::assertSame(
                'Event outbox idempotency key was reused for a different mutation.',
                $exception->getMessage(),
            );
        }

        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $eventId)
            ->where('user_id', $attendee->id)
            ->value('status'));
        self::assertSame(0, DB::table('event_attendance')->where('event_id', $eventId)->count());
        self::assertSame(0, DB::table('event_attendance_activity')->where('event_id', $eventId)->count());
    }

    public function test_activity_and_credit_claim_records_cannot_be_deleted(): void
    {
        $organizer = $this->user();
        $attendee = $this->user();
        $eventId = $this->event($organizer);
        $this->rsvp($eventId, $attendee);
        $result = $this->record($eventId, (int) $attendee->id, $organizer);

        $activity = EventAttendanceActivity::withoutGlobalScopes()->findOrFail($result->activityId);
        $activity->forceFill(['reason' => 'rewrite-attempt']);
        try {
            $activity->save();
            self::fail('Eloquent rewrote attendance activity.');
        } catch (LogicException $exception) {
            self::assertSame('Event attendance activity is immutable.', $exception->getMessage());
        }

        $claimId = (int) DB::table('event_attendance_credit_claims')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'attendance_id' => $result->attendance->id,
            'user_id' => $attendee->id,
            'claim_type' => 'attendance_reward',
            'idempotency_key' => "claim:{$eventId}:{$attendee->id}",
            'funding_source_type' => 'disabled_fixture',
            'amount' => 1,
            'unit' => 'time_credit',
            'status' => 'failed',
            'failure_code' => 'disabled',
            'metadata' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'failed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $claim = EventAttendanceCreditClaim::withoutGlobalScopes()->findOrFail($claimId);
        try {
            $claim->delete();
            self::fail('Eloquent deleted an attendance credit claim.');
        } catch (LogicException $exception) {
            self::assertSame('Event attendance credit claims cannot be deleted.', $exception->getMessage());
        }

        foreach (['event_attendance_activity' => $result->activityId, 'event_attendance_credit_claims' => $claimId] as $table => $id) {
            try {
                DB::table($table)->where('id', $id)->delete();
                self::fail("Direct delete bypassed {$table} immutability.");
            } catch (QueryException $exception) {
                self::assertStringContainsString('immutable', $exception->getMessage());
            }
        }
    }

    public function test_attendance_ledger_schema_and_indexes_are_present(): void
    {
        foreach (['attendance_status', 'attendance_version', 'status_changed_at', 'status_changed_by'] as $column) {
            self::assertTrue(Schema::hasColumn('event_attendance', $column));
        }
        self::assertTrue(Schema::hasTable('event_attendance_activity'));
        self::assertTrue(Schema::hasTable('event_attendance_credit_claims'));

        $activityIndex = DB::select(
            "SHOW INDEX FROM `event_attendance_activity` WHERE Key_name = 'uq_event_attendance_activity_key'"
        );
        self::assertSame(
            ['tenant_id', 'idempotency_key'],
            array_map(static fn (object $row): string => (string) $row->Column_name, $activityIndex),
        );
        self::assertSame([0, 0], array_map(
            static fn (object $row): int => (int) $row->Non_unique,
            $activityIndex,
        ));
    }

    /** @param array<string,mixed> $overrides */
    private function user(array $overrides = []): User
    {
        return User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));
    }

    private function event(User $organizer): int
    {
        return (int) DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $organizer->id,
            'title' => 'Attendance ledger fixture',
            'description' => 'Attendance ledger fixture.',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHours(2),
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'is_recurring_template' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rsvp(int $eventId, User $attendee, string $status = 'going'): void
    {
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'status' => $status,
            'created_at' => now(),
        ]);
    }

    private function canonicalRegistration(int $eventId, User $attendee, string $state): void
    {
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $eventId,
            'user_id' => (int) $attendee->id,
            'capacity_pool_key' => 'event',
            'registration_state' => $state,
            'registration_version' => 1,
            'state_changed_at' => now(),
            'state_changed_by' => (int) $attendee->id,
            'confirmed_at' => $state === 'confirmed' ? now() : null,
            'cancelled_at' => $state === 'cancelled' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function record(
        int $eventId,
        int $attendeeId,
        User $actor,
        ?float $hours = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
    ): \App\Support\Events\EventAttendanceResult {
        return TenantContext::runForTenant(
            $this->testTenantId,
            fn () => $this->service->record(
                $eventId,
                $attendeeId,
                $actor,
                $hours,
                $notes,
                $idempotencyKey,
            ),
        );
    }

    /** @param callable(): mixed $operation */
    private function assertRejected(string $reasonCode, callable $operation): void
    {
        try {
            $operation();
            self::fail("Attendance operation {$reasonCode} was not rejected.");
        } catch (EventAttendanceException $exception) {
            self::assertSame($reasonCode, $exception->reasonCode);
        }
    }
}
