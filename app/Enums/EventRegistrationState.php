<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventRegistrationState: string
{
    case None = 'none';
    case Invited = 'invited';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Offered = 'offered';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
