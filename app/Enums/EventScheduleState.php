<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventScheduleState: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Ended = 'ended';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Archived = 'archived';
}
