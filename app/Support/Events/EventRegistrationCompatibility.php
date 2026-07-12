<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Support\Events;

use App\Enums\EventCapacityRegistrationState;
use App\Enums\EventWaitlistQueueState;

/** Safe projection between canonical capacity facts and legacy enum columns. */
final class EventRegistrationCompatibility
{
    public const DEFAULT_CAPACITY_POOL = 'event';

    public static function registrationFromLegacy(?string $status): ?EventCapacityRegistrationState
    {
        return match (strtolower(trim((string) $status))) {
            'going', 'attended' => EventCapacityRegistrationState::Confirmed,
            'invited' => EventCapacityRegistrationState::Invited,
            'not_going', 'declined' => EventCapacityRegistrationState::Declined,
            'cancelled' => EventCapacityRegistrationState::Cancelled,
            // interested/maybe are engagement facts, never capacity facts.
            default => null,
        };
    }

    public static function registrationToLegacy(EventCapacityRegistrationState $state): string
    {
        return match ($state) {
            EventCapacityRegistrationState::Invited,
            EventCapacityRegistrationState::Pending => 'invited',
            EventCapacityRegistrationState::Confirmed => 'going',
            EventCapacityRegistrationState::Declined => 'declined',
            EventCapacityRegistrationState::Cancelled => 'cancelled',
        };
    }

    public static function waitlistFromLegacy(?string $status): ?EventWaitlistQueueState
    {
        return match (strtolower(trim((string) $status))) {
            'waiting' => EventWaitlistQueueState::Waiting,
            'promoted' => EventWaitlistQueueState::Accepted,
            'expired' => EventWaitlistQueueState::Expired,
            'cancelled' => EventWaitlistQueueState::Cancelled,
            default => null,
        };
    }

    public static function waitlistToLegacy(EventWaitlistQueueState $state): string
    {
        return match ($state) {
            // Legacy has no offered state; retaining waiting is the only safe
            // projection and keeps existing roster code from seeing a new enum.
            EventWaitlistQueueState::Waiting,
            EventWaitlistQueueState::Offered => 'waiting',
            EventWaitlistQueueState::Accepted => 'promoted',
            EventWaitlistQueueState::Expired => 'expired',
            EventWaitlistQueueState::Cancelled => 'cancelled',
        };
    }
}
