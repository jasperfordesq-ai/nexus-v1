<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventBroadcastDeliveryStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Retry = 'retry';
    case Delivered = 'delivered';
    case Suppressed = 'suppressed';
    case DeadLetter = 'dead_letter';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Suppressed,
            self::DeadLetter,
            self::Cancelled,
        ], true);
    }
}
