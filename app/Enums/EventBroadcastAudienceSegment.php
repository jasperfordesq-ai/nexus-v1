<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Canonical Event audience axes; legacy RSVP facts are intentionally absent. */
enum EventBroadcastAudienceSegment: string
{
    case RegistrationConfirmed = 'registration_confirmed';
    case WaitlistActive = 'waitlist_active';
    case AttendanceAttended = 'attendance_attended';
    case AttendanceNoShow = 'attendance_no_show';

    public function isAttendanceSegment(): bool
    {
        return in_array($this, [self::AttendanceAttended, self::AttendanceNoShow], true);
    }
}
