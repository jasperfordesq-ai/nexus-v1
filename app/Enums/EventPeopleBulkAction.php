<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Bounded Event People actions accepted by the bulk operations service. */
enum EventPeopleBulkAction: string
{
    case Invite = 'invite';
    case Approve = 'approve';
    case Reject = 'reject';
    case Cancel = 'cancel';
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case NoShow = 'no_show';
    case UndoAttendance = 'undo_attendance';

    public function attendanceAction(): ?EventAttendanceAction
    {
        return match ($this) {
            self::CheckIn => EventAttendanceAction::CheckIn,
            self::CheckOut => EventAttendanceAction::CheckOut,
            self::NoShow => EventAttendanceAction::NoShow,
            self::UndoAttendance => EventAttendanceAction::Undo,
            default => null,
        };
    }
}
