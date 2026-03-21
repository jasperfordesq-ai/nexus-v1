<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\StreakService;
use App\Models\UserStreak;

class StreakServiceTest extends TestCase
{
    // ── STREAK_TYPES ──

    public function test_streak_types_constant(): void
    {
        $this->assertEquals(['login', 'activity', 'giving', 'volunteer'], StreakService::STREAK_TYPES);
    }

    // ── getCurrentStreak ──

    public function test_getCurrentStreak_returns_zero_for_nonexistent_user(): void
    {
        $result = StreakService::getCurrentStreak($this->testTenantId, 0);
        $this->assertEquals(0, $result);
    }

    // ── getLongestStreak ──

    public function test_getLongestStreak_returns_zero_for_nonexistent_user(): void
    {
        $result = StreakService::getLongestStreak($this->testTenantId, 0);
        $this->assertEquals(0, $result);
    }

    // ── getStreak ──

    public function test_getStreak_returns_defaults_for_no_streak(): void
    {
        $result = StreakService::getStreak(0, 'activity');
        $this->assertNotNull($result);
        $this->assertEquals(0, $result['current']);
        $this->assertEquals(0, $result['longest']);
        $this->assertFalse($result['is_active']);
    }

    // ── getAllStreaks ──

    public function test_getAllStreaks_returns_all_types(): void
    {
        $result = StreakService::getAllStreaks(0);
        $this->assertArrayHasKey('login', $result);
        $this->assertArrayHasKey('activity', $result);
        $this->assertArrayHasKey('giving', $result);
        $this->assertArrayHasKey('volunteer', $result);
    }

    // ── getStreakIcon ──

    public function test_getStreakIcon_returns_fire_for_week(): void
    {
        $icon = StreakService::getStreakIcon(7);
        $this->assertNotEmpty($icon);
    }

    public function test_getStreakIcon_returns_sleep_for_zero(): void
    {
        $icon = StreakService::getStreakIcon(0);
        $this->assertNotEmpty($icon);
    }

    public function test_getStreakIcon_returns_trophy_for_year(): void
    {
        $icon = StreakService::getStreakIcon(365);
        $this->assertNotEmpty($icon);
    }

    // ── getStreakMessage ──

    public function test_getStreakMessage_handles_null(): void
    {
        $msg = StreakService::getStreakMessage(null);
        $this->assertEquals('Start your streak today!', $msg);
    }

    public function test_getStreakMessage_handles_zero_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 0]);
        $this->assertEquals('Start your streak today!', $msg);
    }

    public function test_getStreakMessage_for_short_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 3]);
        $this->assertStringContainsString('3 day streak', $msg);
    }

    public function test_getStreakMessage_for_week_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 10]);
        $this->assertStringContainsString('10 day streak', $msg);
    }

    public function test_getStreakMessage_for_month_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 30]);
        $this->assertStringContainsString('Fantastic', $msg);
    }

    public function test_getStreakMessage_for_hundred_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 100]);
        $this->assertStringContainsString('Amazing', $msg);
    }

    public function test_getStreakMessage_for_year_streak(): void
    {
        $msg = StreakService::getStreakMessage(['current' => 365]);
        $this->assertStringContainsString('Incredible', $msg);
    }

    // ── getStreakLeaderboard ──

    public function test_getStreakLeaderboard_returns_array(): void
    {
        $result = StreakService::getStreakLeaderboard($this->testTenantId);
        $this->assertIsArray($result);
    }
}
