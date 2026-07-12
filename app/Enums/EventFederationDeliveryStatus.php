<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

enum EventFederationDeliveryStatus: string
{
    case Pending = 'pending';
    case Retry = 'retry';
    case Processing = 'processing';
    case Delivered = 'delivered';
    case DeadLetter = 'dead_letter';

    public function isClaimable(): bool
    {
        return $this === self::Pending || $this === self::Retry;
    }

    public function isTerminal(): bool
    {
        return $this === self::Delivered || $this === self::DeadLetter;
    }
}
