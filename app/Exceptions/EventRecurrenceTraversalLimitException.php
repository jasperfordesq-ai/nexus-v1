<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Safe operational failure when an RRULE cannot be sought within its work budget. */
final class EventRecurrenceTraversalLimitException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('event_recurrence_seek_limit_exceeded');
    }
}
