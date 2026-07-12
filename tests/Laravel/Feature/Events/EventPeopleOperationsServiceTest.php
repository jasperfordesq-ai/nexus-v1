<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Events;

use App\Core\TenantContext;
use App\Enums\EventAttendanceAction;
use App\Enums\EventPeopleBulkAction;
use App\Enums\EventStaffRole;
use App\Exceptions\EventAttendanceException;
use App\Exceptions\EventRegistrationException;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAttendanceService;
use App\Services\EventPeopleBulkService;
use App\Services\EventPeopleHistoryService;
use App\Services\EventPeopleService;
use App\Support\Events\EventPeopleBulkOperation;
use App\Support\Events\EventPeopleQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\Laravel\TestCase;

final class EventPeopleOperationsServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        Config::set('events.attendance_credit_mode', 'off');
        Config::set('events.registration.legacy_dual_write', true);
    }

    public function test_check_out_and_reasoned_undo_append_versions_and_replay_safely(): void
    {
        $organizer = $this->member('Operations Organizer');
        $attendee = $this->member('Operations Attendee');
        $event = $this->event($organizer, now()->subHour());
        $this->confirmed($event, $attendee);
        $service = app(EventAttendanceService::class);

        $checkIn = $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::CheckIn,
            $organizer,
            0,
            null,
            'operations-check-in',
        );
        $checkOut = $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::CheckOut,
            $organizer,
            1,
            null,
            'operations-check-out',
        );
        $undo = $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::Undo,
            $organizer,
            2,
            'Member remained at the event.',
            'operations-undo-check-out',
        );

        self::assertTrue($checkIn->changed);
        self::assertSame('checked_out', $checkOut->toState->value);
        self::assertSame('checked_in', $undo->toState->value);
        self::assertSame(3, (int) $undo->attendance->attendance_version);
        self::assertNull($undo->attendance->checked_out_at);
        self::assertSame(3, DB::table('event_attendance_activity')
            ->where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->count());
        $people = app(EventPeopleService::class)->paginate(
            $event,
            new EventPeopleQuery(),
        );
        self::assertFalse($people['items'][0]['management_actions']['undo_attendance']);
        $this->assertAttendanceRejected(
            'event_attendance_idempotency_conflict',
            fn () => $service->transition(
                (int) $event->id,
                (int) $attendee->id,
                EventAttendanceAction::Undo,
                $organizer,
                2,
                'A different correction reason.',
                'operations-undo-check-out',
            ),
        );
        self::assertDatabaseHas('event_attendance_activity', [
            'id' => $undo->activityId,
            'attendance_version' => 3,
            'action' => 'undo',
            'from_status' => 'checked_out',
            'to_status' => 'checked_in',
            'reason' => 'Member remained at the event.',
        ]);

        $replay = $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::Undo,
            $organizer,
            2,
            'Member remained at the event.',
            'operations-undo-check-out',
        );
        self::assertTrue($replay->replayed);
        self::assertFalse($replay->changed);
        self::assertSame(3, DB::table('event_attendance_activity')
            ->where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->count());
    }

    public function test_no_show_requires_event_start_and_undo_requires_reason(): void
    {
        $organizer = $this->member('No-show Organizer');
        $attendee = $this->member('No-show Attendee');
        $event = $this->event($organizer, now()->addHour());
        $this->confirmed($event, $attendee);
        $service = app(EventAttendanceService::class);

        $this->assertAttendanceRejected(
            'event_attendance_no_show_too_early',
            fn () => $service->transition(
                (int) $event->id,
                (int) $attendee->id,
                EventAttendanceAction::NoShow,
                $organizer,
                0,
                null,
                'early-no-show',
            ),
        );
        DB::table('events')->where('id', $event->id)->update([
            'start_time' => now()->subHour(),
            'end_time' => now()->subMinutes(10),
        ]);
        $event->refresh();
        $noShow = $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::NoShow,
            $organizer,
            0,
            null,
            'valid-no-show',
        );
        self::assertSame('no_show', $noShow->toState->value);
        self::assertSame('going', DB::table('event_rsvps')
            ->where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->value('status'));

        $this->assertAttendanceRejected(
            'event_attendance_reason_required',
            fn () => $service->transition(
                (int) $event->id,
                (int) $attendee->id,
                EventAttendanceAction::Undo,
                $organizer,
                1,
                null,
                'undo-without-reason',
            ),
        );
        self::assertSame(1, DB::table('event_attendance_activity')
            ->where('event_id', $event->id)
            ->count());
    }

    public function test_stale_expected_version_rolls_back_without_activity_or_projection_change(): void
    {
        $organizer = $this->member('Version Organizer');
        $attendee = $this->member('Version Attendee');
        $event = $this->event($organizer, now()->subHour());
        $this->confirmed($event, $attendee);
        $service = app(EventAttendanceService::class);
        $service->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::CheckIn,
            $organizer,
            0,
            null,
            'version-check-in',
        );

        $this->assertAttendanceRejected(
            'event_attendance_version_conflict',
            fn () => $service->transition(
                (int) $event->id,
                (int) $attendee->id,
                EventAttendanceAction::CheckOut,
                $organizer,
                0,
                null,
                'stale-check-out',
            ),
        );
        self::assertSame(1, DB::table('event_attendance_activity')
            ->where('event_id', $event->id)
            ->count());
        self::assertNull(DB::table('event_attendance')
            ->where('event_id', $event->id)
            ->value('checked_out_at'));
    }

    public function test_bulk_invitation_is_canonical_per_subject_and_idempotent(): void
    {
        $organizer = $this->member('Bulk Organizer');
        $invitee = $this->member('Bulk Invitee');
        $event = $this->event($organizer, now()->addDay());
        $service = app(EventPeopleBulkService::class);

        $operations = [
            new EventPeopleBulkOperation(
                (int) $invitee->id,
                EventPeopleBulkAction::Invite,
                0,
                'bulk-invite-one',
            ),
            new EventPeopleBulkOperation(
                2_000_000_000,
                EventPeopleBulkAction::Invite,
                0,
                'bulk-missing-one',
            ),
        ];
        $result = $service->execute($event, $organizer, $operations);

        self::assertSame(2, $result['requested']);
        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertTrue($result['results'][0]['success']);
        self::assertSame('invited', $result['results'][0]['mutation']['state']);
        self::assertFalse($result['results'][1]['success']);
        self::assertSame(
            'event_registration_subject_not_found',
            $result['results'][1]['error'],
        );
        self::assertDatabaseHas('event_registrations', [
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'registration_state' => 'invited',
            'registration_version' => 1,
        ]);
        $replay = $service->execute($event, $organizer, $operations);
        self::assertSame(1, $replay['succeeded']);
        self::assertTrue($replay['results'][0]['mutation']['idempotent_replay']);
        self::assertSame(1, DB::table('event_registration_history')
            ->where('event_id', $event->id)
            ->where('user_id', $invitee->id)
            ->count());
        $source = file_get_contents(app_path('Services/EventPeopleBulkService.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString("DB::table('event_rsvps')", $source);
    }

    public function test_bulk_started_event_operations_are_mixed_and_partially_successful(): void
    {
        $organizer = $this->member('Bulk Live Organizer');
        $cancelled = $this->member('Bulk Cancellation');
        $attendee = $this->member('Bulk Check-in');
        $event = $this->event($organizer, now()->subHour());
        $this->confirmed($event, $cancelled);
        $this->confirmed($event, $attendee);
        DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->where('user_id', $cancelled->id)
            ->update([
                'registration_state' => 'pending',
                'pending_at' => now(),
                'confirmed_at' => null,
            ]);
        DB::table('event_rsvps')
            ->where('event_id', $event->id)
            ->where('user_id', $cancelled->id)
            ->update(['status' => 'invited']);
        $service = app(EventPeopleBulkService::class);
        $operations = [
            new EventPeopleBulkOperation(
                (int) $cancelled->id,
                EventPeopleBulkAction::Cancel,
                1,
                'bulk-cancel-one',
                'Duplicate registration removed by the organizer.',
            ),
            new EventPeopleBulkOperation(
                (int) $attendee->id,
                EventPeopleBulkAction::CheckIn,
                0,
                'bulk-check-in-one',
            ),
            new EventPeopleBulkOperation(
                2_000_000_000,
                EventPeopleBulkAction::CheckIn,
                0,
                'bulk-missing-check-in',
            ),
        ];

        $result = $service->execute($event, $organizer, $operations);
        self::assertSame(3, $result['requested']);
        self::assertSame(
            2,
            $result['succeeded'],
            json_encode($result['results'], JSON_THROW_ON_ERROR),
        );
        self::assertSame(1, $result['failed']);
        self::assertSame('cancelled', $result['results'][0]['mutation']['state']);
        self::assertSame('checked_in', $result['results'][1]['mutation']['to_state']);
        self::assertSame(
            'event_registration_subject_not_found',
            $result['results'][2]['error'],
        );

        $replay = $service->execute($event, $organizer, $operations);
        self::assertSame(2, $replay['succeeded']);
        self::assertTrue($replay['results'][0]['mutation']['idempotent_replay']);
        self::assertTrue($replay['results'][1]['mutation']['idempotent_replay']);
        self::assertSame(1, DB::table('event_registration_history')
            ->where('event_id', $event->id)
            ->where('user_id', $cancelled->id)
            ->count());
        self::assertSame(1, DB::table('event_attendance_activity')
            ->where('event_id', $event->id)
            ->where('user_id', $attendee->id)
            ->count());
    }

    public function test_cross_ledger_history_is_redacted_and_deterministic(): void
    {
        $organizer = $this->member('History Organizer');
        $attendee = $this->member('History Attendee');
        $event = $this->event($organizer, now()->subHour());
        $this->confirmed($event, $attendee);
        app(EventAttendanceService::class)->transition(
            (int) $event->id,
            (int) $attendee->id,
            EventAttendanceAction::NoShow,
            $organizer,
            0,
            'Door list reconciled.',
            'history-no-show-secret-key',
        );

        $history = app(EventPeopleHistoryService::class)->paginate(
            $event,
            $attendee,
            $organizer,
        );
        self::assertSame(1, $history['total']);
        self::assertSame('attendance', $history['items'][0]['axis']);
        self::assertSame('Door list reconciled.', $history['items'][0]['reason']);
        self::assertSame((int) $organizer->id, $history['items'][0]['actor']['id']);
        $encoded = json_encode($history, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('history-no-show-secret-key', $encoded);
        self::assertStringNotContainsString('idempotency_key', $encoded);
        self::assertStringNotContainsString('metadata', $encoded);
        self::assertStringNotContainsString('credit_mode', $encoded);

        try {
            app(EventPeopleHistoryService::class)->paginate(
                $event,
                $attendee,
                $organizer,
                201,
                50,
            );
            self::fail('A history offset beyond 10,000 rows was accepted.');
        } catch (EventRegistrationException $exception) {
            self::assertSame(
                'event_registration_people_query_invalid',
                $exception->reasonCode,
            );
        }
    }

    public function test_check_in_staff_receive_only_the_attendance_projection(): void
    {
        $organizer = $this->member('Projection Organizer');
        $staff = $this->member('Projection Check-in Staff');
        $attendee = $this->member('Projection Attendee');
        $interested = $this->member('Projection Interested Secret');
        $waitlisted = $this->member('Projection Waitlisted Secret');
        $event = $this->event($organizer, now()->subMinutes(10));
        $this->confirmed($event, $attendee);
        $this->assignStaff($event, $staff, EventStaffRole::CheckInStaff, $organizer);
        $now = now();
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $interested->id,
            'status' => 'interested',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_waitlist_entries')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => $event->id,
            'user_id' => $waitlisted->id,
            'capacity_pool_key' => 'event',
            'queue_state' => 'waiting',
            'queue_version' => 1,
            'queue_sequence' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => $organizer->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $service = app(EventPeopleService::class);

        $result = $service->paginateForActor($event, $staff, new EventPeopleQuery());

        self::assertSame(1, $result['total']);
        self::assertSame('attendance', $result['items'][0]['privacy']['projection']);
        self::assertTrue($result['items'][0]['management_actions']['check_in']);
        self::assertArrayNotHasKey('waitlist', $result['items'][0]);
        self::assertArrayNotHasKey('engagement', $result['items'][0]);
        self::assertArrayNotHasKey('approve', $result['items'][0]['management_actions']);
        self::assertArrayNotHasKey('waitlisted', $result['metrics']);
        self::assertSame(
            ['state' => 'confirmed'],
            $result['items'][0]['registration'],
        );
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Projection Interested Secret', $encoded);
        self::assertStringNotContainsString('Projection Waitlisted Secret', $encoded);
        $returnedIds = array_map(
            static fn (array $item): int => (int) $item['member']['id'],
            $result['items'],
        );
        self::assertNotContains((int) $interested->id, $returnedIds);
        self::assertNotContains((int) $waitlisted->id, $returnedIds);

        try {
            $service->paginateForActor(
                $event,
                $staff,
                new EventPeopleQuery(waitlistState: 'active'),
            );
            self::fail('Check-in staff could filter the hidden waitlist axis.');
        } catch (EventRegistrationException $exception) {
            self::assertSame(
                'event_registration_people_query_invalid',
                $exception->reasonCode,
            );
        }
    }

    public function test_advertised_attendance_actions_follow_the_server_timing_windows(): void
    {
        $organizer = $this->member('Timing Organizer');
        $attendee = $this->member('Timing Attendee');
        $event = $this->event($organizer, now()->addHours(2));
        $this->confirmed($event, $attendee);
        $service = app(EventPeopleService::class);

        $before = $service->paginate($event, new EventPeopleQuery());
        self::assertFalse($before['items'][0]['management_actions']['check_in']);
        self::assertFalse($before['items'][0]['management_actions']['no_show']);

        DB::table('events')->where('id', $event->id)->update([
            'start_time' => now()->subMinute(),
            'end_time' => now()->addHour(),
        ]);
        $event->refresh();
        $started = $service->paginate($event, new EventPeopleQuery());
        self::assertTrue($started['items'][0]['management_actions']['check_in']);
        self::assertTrue($started['items'][0]['management_actions']['no_show']);
    }

    public function test_attendance_metrics_report_current_states_separately(): void
    {
        $organizer = $this->member('Metrics Organizer');
        $checkedIn = $this->member('Metrics Checked In');
        $checkedOut = $this->member('Metrics Checked Out');
        $noShow = $this->member('Metrics No-show');
        $event = $this->event($organizer, now()->subHour());
        $now = now();
        foreach ([$checkedIn, $checkedOut, $noShow] as $member) {
            $this->confirmed($event, $member);
        }
        DB::table('event_attendance')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'event_id' => $event->id,
                'user_id' => $checkedIn->id,
                'attendance_status' => 'checked_in',
                'attendance_version' => null,
                'status_changed_at' => $now,
                'status_changed_by' => $organizer->id,
                'checked_in_at' => $now,
                'checked_in_by' => $organizer->id,
                'checked_out_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->testTenantId,
                'event_id' => $event->id,
                'user_id' => $checkedOut->id,
                'attendance_status' => 'checked_out',
                'attendance_version' => 2,
                'status_changed_at' => $now,
                'status_changed_by' => $organizer->id,
                'checked_in_at' => $now->copy()->subHour(),
                'checked_in_by' => $organizer->id,
                'checked_out_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $this->testTenantId,
                'event_id' => $event->id,
                'user_id' => $noShow->id,
                'attendance_status' => 'no_show',
                'attendance_version' => 1,
                'status_changed_at' => $now,
                'status_changed_by' => $organizer->id,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'checked_out_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $people = app(EventPeopleService::class)->paginate($event, new EventPeopleQuery());
        $metrics = $people['metrics'];
        self::assertSame(1, $metrics['checked_in']);
        self::assertSame(1, $metrics['checked_out']);
        self::assertSame(1, $metrics['no_show']);
        self::assertSame(0, $metrics['attended']);
        $legacyVersion = collect($people['items'])->firstWhere(
            'member.id',
            (int) $checkedIn->id,
        );
        self::assertIsArray($legacyVersion);
        self::assertSame(1, $legacyVersion['attendance']['version']);
        self::assertFalse($legacyVersion['management_actions']['undo_attendance']);
    }

    public function test_deep_offsets_beyond_the_operational_envelope_are_rejected(): void
    {
        self::assertSame(100, (new EventPeopleQuery(page: 100, perPage: 100))->page);
        try {
            new EventPeopleQuery(page: 101, perPage: 100);
            self::fail('A deep People offset beyond 10,000 rows was accepted.');
        } catch (EventRegistrationException $exception) {
            self::assertSame(
                'event_registration_people_query_invalid',
                $exception->reasonCode,
            );
        }
    }

    /** @param callable():mixed $operation */
    private function assertAttendanceRejected(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail("Attendance operation {$reason} was not rejected.");
        } catch (EventAttendanceException $exception) {
            self::assertSame($reason, $exception->reasonCode);
        }
    }

    private function member(string $name): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'name' => $name,
            'first_name' => $name,
            'status' => 'active',
            'is_approved' => true,
        ]);
    }

    private function event(User $organizer, Carbon $start): Event
    {
        $id = DB::table('events')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $organizer->id,
            'title' => 'Event People operations fixture',
            'description' => 'Versioned attendance fixture.',
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
            'timezone' => 'UTC',
            'timezone_source' => 'explicit',
            'all_day' => 0,
            'occurrence_key' => 'people-operations:' . bin2hex(random_bytes(8)),
            'is_recurring_template' => 0,
            'status' => 'active',
            'publication_status' => 'published',
            'operational_status' => 'scheduled',
            'lifecycle_version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Event::withoutGlobalScopes()->findOrFail($id);
    }

    private function confirmed(Event $event, User $attendee): void
    {
        $now = now();
        DB::table('event_registrations')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => (int) $event->id,
            'user_id' => (int) $attendee->id,
            'capacity_pool_key' => 'event',
            'registration_state' => 'confirmed',
            'registration_version' => 1,
            'state_changed_at' => $now,
            'state_changed_by' => (int) $attendee->id,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('event_rsvps')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => (int) $event->id,
            'user_id' => (int) $attendee->id,
            'status' => 'going',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assignStaff(
        Event $event,
        User $staff,
        EventStaffRole $role,
        User $grantor,
    ): void {
        DB::table('event_staff_assignments')->insert([
            'tenant_id' => $this->testTenantId,
            'event_id' => (int) $event->id,
            'user_id' => (int) $staff->id,
            'role' => $role->value,
            'status' => 'active',
            'assignment_version' => 1,
            'granted_at' => now(),
            'granted_by' => (int) $grantor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
