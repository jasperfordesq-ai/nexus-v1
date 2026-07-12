<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Timed capacity-offer lifecycle for one queue entry. */
enum EventWaitlistQueueState: string
{
    case Waiting = 'waiting';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Waiting => [self::Offered, self::Cancelled],
            self::Offered => [self::Accepted, self::Expired, self::Cancelled],
            self::Accepted, self::Expired, self::Cancelled => [self::Waiting],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }

    public function reservesCapacity(): bool
    {
        return $this === self::Offered;
    }
}
