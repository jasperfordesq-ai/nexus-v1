<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/** Canonical lifecycle states for every tenant-scoped group. */
enum GroupStatus: string
{
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Dormant = 'dormant';
    case Archived = 'archived';
    case Rejected = 'rejected';

    /**
     * Normalize the finite set of values written by earlier releases.
     * Unknown values are rejected so migrations and runtime code cannot guess.
     */
    public static function normalize(string|null $status, bool $legacyIsActive): self
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'pending_review', 'pending_approval', 'pending', 'draft' => self::PendingReview,
            'dormant' => self::Dormant,
            'archived', 'inactive', 'deleted' => self::Archived,
            'rejected' => self::Rejected,
            '', 'active' => $legacyIsActive ? self::Active : self::Archived,
            default => throw new InvalidArgumentException("Unknown group lifecycle status: {$normalized}"),
        };
    }

    /** The compatibility boolean is true only for a fully active group. */
    public function legacyIsActive(): bool
    {
        return $this === self::Active;
    }

    public function isWritable(): bool
    {
        return $this === self::Active;
    }

    public function isJoinable(): bool
    {
        return $this === self::Active;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::PendingReview => in_array($target, [self::Active, self::Rejected, self::Archived], true),
            self::Active => in_array($target, [self::Dormant, self::Archived], true),
            self::Dormant => in_array($target, [self::Active, self::Archived], true),
            self::Archived => $target === self::Active,
            self::Rejected => $target === self::PendingReview,
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
