<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Support;

use App\Support\FeedItemTables;
use Tests\Laravel\TestCase;

class FeedItemTablesTest extends TestCase
{
    public function testTablesMapIsNonEmptyAndStringTyped(): void
    {
        $this->assertNotEmpty(FeedItemTables::TABLES);
        foreach (FeedItemTables::TABLES as $type => $table) {
            $this->assertIsString($type);
            $this->assertIsString($table);
            $this->assertNotSame('', $table);
        }
    }

    public function testEveryCommentableTypeHasABackingTable(): void
    {
        foreach (FeedItemTables::COMMENTABLE_TYPES as $type) {
            $this->assertArrayHasKey(
                $type,
                FeedItemTables::TABLES,
                "Commentable type '{$type}' must have a backing table",
            );
        }
    }

    public function testIsCommentableMatchesTheAllowList(): void
    {
        foreach (FeedItemTables::COMMENTABLE_TYPES as $type) {
            $this->assertTrue(FeedItemTables::isCommentable($type));
        }

        // 'comment' is a backing table but is NOT itself commentable.
        $this->assertFalse(FeedItemTables::isCommentable('comment'));
        $this->assertFalse(FeedItemTables::isCommentable('badge_earned'));
        $this->assertFalse(FeedItemTables::isCommentable('totally-unknown'));
    }

    public function testExistsFailsClosedForNonPositiveIds(): void
    {
        // targetId <= 0 short-circuits before any tenant/DB access.
        $this->assertFalse(FeedItemTables::exists('post', 0));
        $this->assertFalse(FeedItemTables::exists('post', -5));
    }

    public function testExistsFailsClosedForUnknownType(): void
    {
        // Unknown target_type short-circuits before any tenant/DB access.
        $this->assertFalse(FeedItemTables::exists('not-a-real-type', 5));
    }

    public function testCanViewFailsClosedForBadInput(): void
    {
        $this->assertFalse(FeedItemTables::canView('post', 0));
        $this->assertFalse(FeedItemTables::canView('not-a-real-type', 5));
    }

    public function testCanViewProfileFailsClosedForNonPositiveId(): void
    {
        $this->assertFalse(FeedItemTables::canViewProfile(0));
        $this->assertFalse(FeedItemTables::canViewProfile(-1));
    }
}
