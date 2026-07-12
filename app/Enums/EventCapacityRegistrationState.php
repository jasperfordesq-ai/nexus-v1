<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

/** Capacity-bearing registration lifecycle, separate from engagement/interest. */
enum EventCapacityRegistrationState: string
{
    case Invited = 'invited';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function consumesCapacity(): bool
    {
        return $this === self::Confirmed;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Pending, self::Confirmed, self::Declined, self::Cancelled],
            self::Pending => [self::Confirmed, self::Declined, self::Cancelled],
            self::Confirmed => [self::Cancelled],
            self::Declined, self::Cancelled => [self::Invited, self::Pending, self::Confirmed],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }
}
