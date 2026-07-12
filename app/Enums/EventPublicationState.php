<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

use UnexpectedValueException;

/**
 * Editorial/publication lifecycle for an event.
 *
 * Operational changes such as postponement and cancellation deliberately live
 * on EventOperationalState so moderation and delivery concerns cannot overwrite
 * one another.
 */
enum EventPublicationState: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview, self::Published, self::Archived],
            self::PendingReview => [self::Draft, self::Published, self::Archived],
            self::Published => [self::Archived],
            self::Archived => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Resolve the publication axis for a legacy-only row.
     *
     * Unknown values are never treated as active: rollout code must stop and
     * surface the corrupt source value instead of publishing an ambiguous row.
     */
    public static function fromLegacyStatus(?string $status): self
    {
        return match (self::normalizeLegacyStatus($status)) {
            'draft' => self::Draft,
            'active', 'cancelled', 'completed' => self::Published,
            default => throw new UnexpectedValueException('event_lifecycle_unknown_legacy_status'),
        };
    }

    private static function normalizeLegacyStatus(?string $status): string
    {
        if ($status === null) {
            return 'active';
        }

        return strtolower(trim($status));
    }
}
