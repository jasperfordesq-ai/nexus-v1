<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventAttendanceAction;
use App\Enums\EventAttendanceState;
use App\Exceptions\EventRegistrationFoundationException;
use App\Models\Event;
use App\Models\EventRegistrationGuestAttendance;
use App\Models\User;
use App\Policies\EventPolicy;
use App\Support\Events\EventRegistrationFoundationSupport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Guest check-in ledger aligned to canonical attendance states, without credits. */
final class EventRegistrationGuestAttendanceService
{
    public function __construct(
        private readonly EventRegistrationFoundationSupport $support = new EventRegistrationFoundationSupport(),
        private readonly EventPolicy $policy = new EventPolicy(),
    ) {
    }

    /** @return array{attendance:EventRegistrationGuestAttendance,changed:bool,replayed:bool,history_id:int} */
    public function transition(
        int $eventId,
        int $guestId,
        User|int $actor,
        EventAttendanceAction|string $action,
        int $expectedVersion,
        string $idempotencyKey,
        ?string $reason = null,
    ): array {
        $this->assertSchema();
        $tenantId = $this->support->tenantId();
        $action = is_string($action) ? EventAttendanceAction::tryFrom($action) : $action;
        if ($action === null) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_action_invalid');
        }
        if ($expectedVersion < 0) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_version_invalid');
        }
        $reason = $reason === null ? null : trim($reason);
        if ($reason === '') {
            $reason = null;
        }
        if ($action->requiresReason() && $reason === null) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_reason_required');
        }
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_reason_invalid');
        }
        $keyHash = $this->support->idempotencyHash($idempotencyKey);

        return DB::transaction(function () use (
            $tenantId,
            $eventId,
            $guestId,
            $actor,
            $action,
            $expectedVersion,
            $keyHash,
            $reason,
        ): array {
            $event = $this->support->concreteEvent($tenantId, $eventId, true);
            $persistedActor = $this->support->actor($tenantId, $actor, true);
            if (! $this->policy->manageAttendance($persistedActor, $event)) {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_authorization_denied');
            }
            $this->assertAttendanceWindow($event);
            $guest = DB::table('event_registration_guests')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', $guestId)
                ->lockForUpdate()
                ->first();
            if ($guest === null) {
                throw new EventRegistrationFoundationException('event_registration_guest_not_found');
            }
            if ((string) $guest->status !== 'captured') {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_guest_inactive');
            }
            $registration = DB::table('event_registrations')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('id', (int) $guest->registration_id)
                ->lockForUpdate()
                ->first(['id', 'registration_state']);
            if ($registration === null || (string) $registration->registration_state !== 'confirmed') {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_registration_required');
            }
            if ($guest->ticket_entitlement_id !== null
                && ! DB::table('event_ticket_entitlements')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('id', (int) $guest->ticket_entitlement_id)
                    ->where('registration_id', (int) $guest->registration_id)
                    ->where('status', 'confirmed')
                    ->exists()) {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_ticket_invalid');
            }

            $requestHash = $this->support->requestHash([
                'action' => $action->value,
                'event_id' => $eventId,
                'guest_id' => $guestId,
                'actor_id' => (int) $persistedActor->id,
                'expected_version' => $expectedVersion,
                'reason' => $reason,
            ]);
            $replay = DB::table('event_registration_guest_attendance_history')
                ->where('tenant_id', $tenantId)
                ->where('idempotency_hash', $keyHash)
                ->first();
            if ($replay !== null) {
                if ((string) $replay->action !== $action->value
                    || ! hash_equals((string) $replay->request_hash, $requestHash)) {
                    throw new EventRegistrationFoundationException('event_registration_guest_attendance_idempotency_conflict');
                }
                $stored = $this->attendanceModel($tenantId, $eventId, $guestId);
                if ((int) $stored->attendance_version !== (int) $replay->attendance_version) {
                    throw new EventRegistrationFoundationException('event_registration_guest_attendance_idempotency_conflict');
                }

                return [
                    'attendance' => $stored,
                    'changed' => false,
                    'replayed' => true,
                    'history_id' => (int) $replay->id,
                ];
            }

            $attendance = DB::table('event_registration_guest_attendance')
                ->where('tenant_id', $tenantId)
                ->where('event_id', $eventId)
                ->where('guest_id', $guestId)
                ->lockForUpdate()
                ->first();
            $currentVersion = $attendance === null ? 0 : (int) $attendance->attendance_version;
            if ($currentVersion !== $expectedVersion) {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_version_conflict');
            }
            if ($action->requiresExistingFact() && $attendance === null) {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_transition_invalid');
            }
            $from = $attendance === null
                ? EventAttendanceState::NotCheckedIn
                : EventAttendanceState::from((string) $attendance->attendance_status);
            $undoHistory = null;
            $to = match ($action) {
                EventAttendanceAction::CheckIn => $from === EventAttendanceState::NotCheckedIn
                    ? EventAttendanceState::CheckedIn
                    : throw new EventRegistrationFoundationException('event_registration_guest_attendance_transition_invalid'),
                EventAttendanceAction::CheckOut => $from === EventAttendanceState::CheckedIn
                    ? EventAttendanceState::CheckedOut
                    : throw new EventRegistrationFoundationException('event_registration_guest_attendance_transition_invalid'),
                EventAttendanceAction::NoShow => $from === EventAttendanceState::NotCheckedIn
                    ? EventAttendanceState::NoShow
                    : throw new EventRegistrationFoundationException('event_registration_guest_attendance_transition_invalid'),
                EventAttendanceAction::Undo => $this->undoTarget(
                    $tenantId,
                    $eventId,
                    $guestId,
                    $undoHistory,
                ),
            };
            $now = CarbonImmutable::now('UTC');
            $nextVersion = $currentVersion + 1;
            $before = [
                'status' => $from->value,
                'checked_in_at' => $attendance?->checked_in_at,
                'checked_out_at' => $attendance?->checked_out_at,
                'attended_at' => $attendance?->attended_at,
                'no_show_at' => $attendance?->no_show_at,
            ];
            $timestamps = $this->timestamps($action, $to, $attendance, $undoHistory, $now);
            $values = [
                'attendance_status' => $to->value,
                'attendance_version' => $nextVersion,
                'status_changed_at' => $now,
                'status_changed_by' => (int) $persistedActor->id,
                ...$timestamps,
                'updated_at' => $now,
            ];
            if ($attendance === null) {
                $attendanceId = (int) DB::table('event_registration_guest_attendance')->insertGetId([
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'registration_id' => (int) $guest->registration_id,
                    'guest_id' => $guestId,
                    ...$values,
                    'created_at' => $now,
                ]);
            } else {
                $attendanceId = (int) $attendance->id;
                if (DB::table('event_registration_guest_attendance')
                    ->where('tenant_id', $tenantId)
                    ->where('event_id', $eventId)
                    ->where('id', $attendanceId)
                    ->where('attendance_version', $expectedVersion)
                    ->update($values) !== 1) {
                    throw new EventRegistrationFoundationException('event_registration_guest_attendance_version_conflict');
                }
            }
            $historyId = (int) DB::table('event_registration_guest_attendance_history')->insertGetId([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'attendance_id' => $attendanceId,
                'registration_id' => (int) $guest->registration_id,
                'guest_id' => $guestId,
                'actor_user_id' => (int) $persistedActor->id,
                'attendance_version' => $nextVersion,
                'action' => $action->value,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'idempotency_hash' => $keyHash,
                'request_hash' => $requestHash,
                'reason' => $reason,
                'metadata' => json_encode([
                    'schema_version' => 1,
                    'credit_mode' => 'off',
                    'before' => $before,
                    'undone_history_id' => $undoHistory?->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return [
                'attendance' => $this->attendanceModel($tenantId, $eventId, $guestId),
                'changed' => true,
                'replayed' => false,
                'history_id' => $historyId,
            ];
        }, 3);
    }

    private function undoTarget(int $tenantId, int $eventId, int $guestId, ?object &$history): EventAttendanceState
    {
        $history = DB::table('event_registration_guest_attendance_history')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('guest_id', $guestId)
            ->orderByDesc('attendance_version')
            ->first();
        if ($history === null || (string) $history->action === EventAttendanceAction::Undo->value) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_undo_invalid');
        }

        return EventAttendanceState::from((string) $history->from_status);
    }

    /** @return array<string,mixed> */
    private function timestamps(
        EventAttendanceAction $action,
        EventAttendanceState $to,
        ?object $attendance,
        ?object $undoHistory,
        CarbonImmutable $now,
    ): array {
        if ($action === EventAttendanceAction::CheckIn) {
            return [
                'checked_in_at' => $now,
                'checked_out_at' => null,
                'attended_at' => null,
                'no_show_at' => null,
            ];
        }
        if ($action === EventAttendanceAction::CheckOut) {
            return [
                'checked_in_at' => $attendance?->checked_in_at,
                'checked_out_at' => $now,
                'attended_at' => null,
                'no_show_at' => null,
            ];
        }
        if ($action === EventAttendanceAction::NoShow) {
            return [
                'checked_in_at' => null,
                'checked_out_at' => null,
                'attended_at' => null,
                'no_show_at' => $now,
            ];
        }
        $metadata = is_string($undoHistory?->metadata ?? null)
            ? json_decode((string) $undoHistory->metadata, true)
            : null;
        $before = is_array($metadata['before'] ?? null) ? $metadata['before'] : [];

        return [
            'checked_in_at' => $before['checked_in_at'] ?? null,
            'checked_out_at' => $before['checked_out_at'] ?? null,
            'attended_at' => $before['attended_at'] ?? null,
            'no_show_at' => $before['no_show_at'] ?? null,
        ];
    }

    private function assertAttendanceWindow(Event $event): void
    {
        $now = CarbonImmutable::now('UTC');
        $opens = $this->support->eventStart($event)
            ->subMinutes(max(0, (int) config('events.attendance.opens_before_minutes', 30)));
        $closes = $this->support->eventEnd($event)
            ->addHours(max(0, (int) config('events.attendance.closes_after_hours', 24)));
        if ($now->lessThan($opens)) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_too_early');
        }
        if ($now->greaterThan($closes)) {
            throw new EventRegistrationFoundationException('event_registration_guest_attendance_window_closed');
        }
    }

    private function attendanceModel(int $tenantId, int $eventId, int $guestId): EventRegistrationGuestAttendance
    {
        return EventRegistrationGuestAttendance::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('guest_id', $guestId)
            ->firstOrFail();
    }

    private function assertSchema(): void
    {
        foreach ([
            'event_registration_guest_attendance',
            'event_registration_guest_attendance_history',
            'event_registration_guests',
            'event_ticket_entitlements',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new EventRegistrationFoundationException('event_registration_guest_attendance_schema_unavailable');
            }
        }
    }
}
