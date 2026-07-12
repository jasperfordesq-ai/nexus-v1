<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventAttendanceState: string
{
    case NotCheckedIn = 'not_checked_in';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Attended = 'attended';
    case NoShow = 'no_show';
}
