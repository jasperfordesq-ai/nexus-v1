<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

use UnexpectedValueException;

/** Delivery lifecycle for a concrete event or recurrence template. */
enum EventOperationalState: string
{
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Postponed, self::Cancelled, self::Completed],
            self::Postponed => [self::Scheduled, self::Cancelled],
            self::Cancelled => [self::Scheduled],
            self::Completed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Resolve the operational axis for a legacy-only row, failing closed. */
    public static function fromLegacyStatus(?string $status): self
    {
        return match (self::normalizeLegacyStatus($status)) {
            'active', 'draft' => self::Scheduled,
            'cancelled' => self::Cancelled,
            'completed' => self::Completed,
            default => throw new UnexpectedValueException('event_lifecycle_unknown_legacy_status'),
        };
    }

    private static function normalizeLegacyStatus(?string $status): string
    {
        if ($status === null || trim($status) === '') {
            return 'active';
        }

        return strtolower(trim($status));
    }
}
