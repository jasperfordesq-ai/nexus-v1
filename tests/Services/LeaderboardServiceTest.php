<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\LeaderboardService;

class LeaderboardServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(LeaderboardService::class));
    }

    public function testLeaderboardTypesConstant(): void
    {
        $types = LeaderboardService::LEADERBOARD_TYPES;

        $this->assertIsArray($types);
        $this->assertArrayHasKey('credits_earned', $types);
        $this->assertArrayHasKey('credits_spent', $types);
        $this->assertArrayHasKey('vol_hours', $types);
        $this->assertArrayHasKey('badges', $types);
        $this->assertArrayHasKey('xp', $types);
        $this->assertArrayHasKey('connections', $types);
        $this->assertArrayHasKey('reviews', $types);
        $this->assertArrayHasKey('posts', $types);
        $this->assertArrayHasKey('streak', $types);
    }

    public function testPeriodsConstant(): void
    {
        $periods = LeaderboardService::PERIODS;

        $this->assertIsArray($periods);
        $this->assertContains('all_time', $periods);
        $this->assertContains('monthly', $periods);
        $this->assertContains('weekly', $periods);
    }

    public function testGetLeaderboardMethodExists(): void
    {
        $this->assertTrue(method_exists(LeaderboardService::class, 'getLeaderboard'));
    }

    public function testGetLeaderboardMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(LeaderboardService::class, 'getLeaderboard');
        $this->assertTrue($ref->isStatic());
    }
}
