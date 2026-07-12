<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Staff-operated transitions for the canonical attendance fact. */
enum EventAttendanceAction: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
    case NoShow = 'no_show';
    case Undo = 'undo';

    public function requiresExistingFact(): bool
    {
        return in_array($this, [self::CheckOut, self::Undo], true);
    }

    public function requiresReason(): bool
    {
        return $this === self::Undo;
    }
}
