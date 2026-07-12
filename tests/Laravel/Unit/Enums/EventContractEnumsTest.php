<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Enums;

use App\Enums\EventAttendanceState;
use App\Enums\EventEngagementState;
use App\Enums\EventLocationMode;
use App\Enums\EventOnlineAccessState;
use App\Enums\EventRegistrationState;
use App\Enums\EventScheduleState;
use PHPUnit\Framework\TestCase;

final class EventContractEnumsTest extends TestCase
{
    public function test_canonical_event_contract_values_are_frozen(): void
    {
        self::assertSame(['none', 'interested'], self::values(EventEngagementState::cases()));
        self::assertSame([
            'none',
            'invited',
            'pending',
            'confirmed',
            'waitlisted',
            'offered',
            'declined',
            'cancelled',
        ], self::values(EventRegistrationState::cases()));
        self::assertSame([
            'not_checked_in',
            'checked_in',
            'checked_out',
            'attended',
            'no_show',
        ], self::values(EventAttendanceState::cases()));
        self::assertSame(['in_person', 'online', 'hybrid'], self::values(EventLocationMode::cases()));
        self::assertSame([
            'not_applicable',
            'not_configured',
            'restricted',
            'scheduled',
            'available',
            'expired',
        ], self::values(EventOnlineAccessState::cases()));
        self::assertSame([
            'draft',
            'pending_review',
            'upcoming',
            'ongoing',
            'ended',
            'postponed',
            'cancelled',
            'completed',
            'archived',
        ], self::values(EventScheduleState::cases()));
    }

    /** @param array<int, \BackedEnum> $cases */
    private static function values(array $cases): array
    {
        return array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);
    }
}
