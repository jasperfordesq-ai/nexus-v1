<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EventCapacityRegistrationState;
use App\Enums\EventWaitlistQueueState;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventWaitlistEntry;
use App\Models\User;
use App\Support\Events\EventContractMapper;
use App\Support\Events\EventRegistrationAvailability;
use App\Support\Events\EventRegistrationCompatibility;
use BackedEnum;
use DateTimeInterface;

final class EventRegistrationResource
{
    /** @return array<string, mixed> */
    public static function fromArray(array $event, array $facts, array $legacyPayload = []): array
    {
        return EventContractMapper::registration($event, $facts, $legacyPayload);
    }

    /**
     * Canonical self-relationship projection. No token hash, private answers,
     * attendance notes, contact fields, or actor-only metadata may cross here.
     *
     * @param array{limit:?int,confirmed:int,remaining:?int,is_full:bool} $capacity
     * @return array<string,mixed>
     */
    public static function fromCanonical(
        Event $event,
        User $member,
        ?EventRegistration $registration,
        ?EventWaitlistEntry $waitlist,
        ?object $attendance,
        ?string $legacyRsvp,
        ?object $legacyWaitlist,
        array $capacity,
    ): array {
        $registrationState = $registration?->registration_state
            ?? EventRegistrationCompatibility::registrationFromLegacy($legacyRsvp);
        $waitlistState = $waitlist?->queue_state
            ?? EventRegistrationCompatibility::waitlistFromLegacy(
                is_string($legacyWaitlist?->status ?? null)
                    ? $legacyWaitlist->status
                    : null,
            );
        $waitlistPosition = $waitlist === null
            ? self::nullableInt($legacyWaitlist?->position ?? null)
            : (int) $waitlist->queue_sequence;
        $offerExpiresAt = self::timestamp($waitlist?->offer_expires_at);
        $offerActive = $waitlistState === EventWaitlistQueueState::Offered
            && $waitlist?->offer_expires_at instanceof DateTimeInterface
            && $waitlist->offer_expires_at->getTimestamp() > time();
        $attendanceStatus = self::nullableString($attendance?->attendance_status ?? null);
        if ($attendanceStatus === null) {
            $attendanceStatus = ($attendance?->checked_out_at ?? null) !== null
                ? 'checked_out'
                : (($attendance?->checked_in_at ?? null) !== null ? 'checked_in' : null);
        }
        $capacityAvailable = $capacity['limit'] === null
            || ($capacity['remaining'] !== null && $capacity['remaining'] > 0);
        $finiteCapacityFull = $capacity['limit'] !== null && $capacity['is_full'];
        $registrable = EventRegistrationAvailability::isRegistrable($event);

        return [
            'contract_version' => 1,
            'event_id' => (int) $event->getKey(),
            'member_id' => (int) $member->getKey(),
            'engagement' => [
                'state' => in_array($legacyRsvp, ['interested', 'maybe'], true)
                    ? 'interested'
                    : 'none',
                'consumes_capacity' => false,
            ],
            'registration' => [
                'id' => $registration === null ? null : (int) $registration->getKey(),
                'state' => self::enumValue($registrationState),
                'version' => $registration === null
                    ? null
                    : (int) $registration->registration_version,
                'capacity_pool_key' => $registration?->capacity_pool_key ?? 'event',
                'allocation_key' => $registration?->allocation_key,
                'changed_at' => self::timestamp($registration?->state_changed_at),
                'invited_at' => self::timestamp($registration?->invited_at),
                'pending_at' => self::timestamp($registration?->pending_at),
                'confirmed_at' => self::timestamp($registration?->confirmed_at),
                'declined_at' => self::timestamp($registration?->declined_at),
                'cancelled_at' => self::timestamp($registration?->cancelled_at),
            ],
            'waitlist' => [
                'id' => $waitlist === null ? null : (int) $waitlist->getKey(),
                'state' => self::enumValue($waitlistState),
                'version' => $waitlist === null ? null : (int) $waitlist->queue_version,
                'position' => $waitlistPosition,
                'offered_at' => self::timestamp($waitlist?->offered_at),
                'offer_expires_at' => $offerExpiresAt,
                'offer_active' => $offerActive,
                'accepted_at' => self::timestamp($waitlist?->accepted_at),
                'expired_at' => self::timestamp($waitlist?->expired_at),
                'cancelled_at' => self::timestamp($waitlist?->cancelled_at),
            ],
            'attendance' => [
                'state' => $attendanceStatus,
                'checked_in_at' => self::timestamp($attendance?->checked_in_at ?? null),
                'checked_out_at' => self::timestamp($attendance?->checked_out_at ?? null),
            ],
            'capacity' => $capacity,
            'actions' => [
                'registrable' => $registrable,
                'confirm' => $registrable
                    && $registrationState !== EventCapacityRegistrationState::Confirmed
                    && ! $offerActive
                    && $capacityAvailable,
                'withdraw' => in_array($registrationState, [
                    EventCapacityRegistrationState::Invited,
                    EventCapacityRegistrationState::Pending,
                    EventCapacityRegistrationState::Confirmed,
                ], true),
                'join_waitlist' => $registrable
                    && $registrationState !== EventCapacityRegistrationState::Confirmed
                    && ! in_array($waitlistState, [
                        EventWaitlistQueueState::Waiting,
                        EventWaitlistQueueState::Offered,
                    ], true)
                    && $finiteCapacityFull,
                'leave_waitlist' => in_array($waitlistState, [
                    EventWaitlistQueueState::Waiting,
                    EventWaitlistQueueState::Offered,
                ], true),
                'accept_offer' => $registrable && $offerActive,
                'idempotency_key_required' => true,
            ],
            'privacy' => [
                'sensitive_fields_redacted' => true,
            ],
        ];
    }

    private static function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return self::nullableString($value);
    }

    private static function timestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return self::nullableString($value);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
