<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Enums;

use App\Enums\GroupStatus;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GroupStatusTest extends TestCase
{
    /** @return iterable<string, array{string|null, bool, GroupStatus}> */
    public static function legacyValues(): iterable
    {
        yield 'active true' => ['active', true, GroupStatus::Active];
        yield 'active false repairs April backfill drift' => ['active', false, GroupStatus::Archived];
        yield 'blank active legacy row' => ['', true, GroupStatus::Active];
        yield 'draft alias' => ['draft', false, GroupStatus::PendingReview];
        yield 'pending approval alias' => ['pending_approval', false, GroupStatus::PendingReview];
        yield 'inactive alias' => ['inactive', false, GroupStatus::Archived];
        yield 'deleted alias' => ['deleted', false, GroupStatus::Archived];
        yield 'canonical dormant' => ['DORMANT', false, GroupStatus::Dormant];
        yield 'canonical rejected' => [' rejected ', false, GroupStatus::Rejected];
    }

    #[DataProvider('legacyValues')]
    public function test_it_normalizes_only_known_legacy_values(
        string|null $stored,
        bool $isActive,
        GroupStatus $expected,
    ): void {
        self::assertSame($expected, GroupStatus::normalize($stored, $isActive));
    }

    public function test_it_rejects_unknown_values_instead_of_guessing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GroupStatus::normalize('mystery_state', true);
    }

    public function test_only_active_mirrors_true_and_accepts_writes_or_joins(): void
    {
        foreach (GroupStatus::cases() as $status) {
            $expected = $status === GroupStatus::Active;
            self::assertSame($expected, $status->legacyIsActive(), $status->value);
            self::assertSame($expected, $status->isWritable(), $status->value);
            self::assertSame($expected, $status->isJoinable(), $status->value);
        }
    }

    public function test_transition_graph_is_explicit_and_idempotent(): void
    {
        self::assertTrue(GroupStatus::PendingReview->canTransitionTo(GroupStatus::Active));
        self::assertTrue(GroupStatus::PendingReview->canTransitionTo(GroupStatus::Rejected));
        self::assertTrue(GroupStatus::Active->canTransitionTo(GroupStatus::Dormant));
        self::assertTrue(GroupStatus::Dormant->canTransitionTo(GroupStatus::Archived));
        self::assertTrue(GroupStatus::Archived->canTransitionTo(GroupStatus::Active));
        self::assertTrue(GroupStatus::Rejected->canTransitionTo(GroupStatus::PendingReview));
        self::assertTrue(GroupStatus::Active->canTransitionTo(GroupStatus::Active));

        self::assertFalse(GroupStatus::Active->canTransitionTo(GroupStatus::Rejected));
        self::assertFalse(GroupStatus::Archived->canTransitionTo(GroupStatus::Dormant));
        self::assertFalse(GroupStatus::Rejected->canTransitionTo(GroupStatus::Active));
    }
}
