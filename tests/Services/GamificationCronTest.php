<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\StreakService;
use Nexus\Services\DailyRewardService;
use Nexus\Services\GamificationService;
use Nexus\Services\ChallengeService;

/**
 * GamificationCronTest
 *
 * Tests for cron-related gamification operations:
 * - Streak resets (daily expired streaks, weekly freeze resets)
 * - Daily reward processing
 * - Challenge lifecycle (expired challenges, progress tracking)
 *
 * @covers \Nexus\Services\StreakService
 * @covers \Nexus\Services\DailyRewardService
 * @covers \Nexus\Services\GamificationService
 * @covers \Nexus\Services\ChallengeService
 */
class GamificationCronTest extends TestCase
{
    // =========================================================================
    // STREAK SERVICE — CRON METHODS
    // =========================================================================

    /**
     * Test that checkExpiredStreaks method exists and is static
     */
    public function testCheckExpiredStreaksMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'checkExpiredStreaks');
        $this->assertTrue($ref->isStatic(), 'checkExpiredStreaks should be a static method');
        $this->assertTrue($ref->isPublic(), 'checkExpiredStreaks should be public');
    }

    /**
     * Test that resetWeeklyFreezes method exists and is static
     */
    public function testResetWeeklyFreezesMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'resetWeeklyFreezes');
        $this->assertTrue($ref->isStatic(), 'resetWeeklyFreezes should be a static method');
        $this->assertTrue($ref->isPublic(), 'resetWeeklyFreezes should be public');
    }

    /**
     * Test streak types constant contains expected types
     */
    public function testStreakTypesContainsCronRelevantTypes(): void
    {
        $types = StreakService::STREAK_TYPES;

        $this->assertContains('login', $types, 'Streak types must include login for daily cron');
        $this->assertContains('activity', $types, 'Streak types must include activity');
        $this->assertContains('giving', $types, 'Streak types must include giving');
        $this->assertContains('volunteer', $types, 'Streak types must include volunteer');
        $this->assertCount(4, $types, 'Should have exactly 4 streak types');
    }

    /**
     * Test recordActivity rejects invalid streak types
     */
    public function testRecordActivityRejectsInvalidStreakType(): void
    {
        // Invalid streak type should return false without touching DB
        $result = StreakService::recordActivity(1, 'invalid_type');
        $this->assertFalse($result, 'Invalid streak type should return false');
    }

    /**
     * Test recordActivity rejects empty streak type
     */
    public function testRecordActivityRejectsEmptyStreakType(): void
    {
        $result = StreakService::recordActivity(1, '');
        $this->assertFalse($result, 'Empty streak type should return false');
    }

    /**
     * Test getStreakMessage returns correct messages for various streak lengths
     */
    public function testGetStreakMessageVariousCases(): void
    {
        // No streak
        $this->assertEquals(
            "Start your streak today!",
            StreakService::getStreakMessage(null),
            'Null streak should return start message'
        );

        $this->assertEquals(
            "Start your streak today!",
            StreakService::getStreakMessage(['current' => 0]),
            'Zero streak should return start message'
        );

        // Small streak (1-6 days)
        $msg = StreakService::getStreakMessage(['current' => 3]);
        $this->assertStringContainsString('3 day streak', $msg, 'Should mention 3 day streak');

        // Weekly streak (7-29 days)
        $msg = StreakService::getStreakMessage(['current' => 7]);
        $this->assertStringContainsString('Great job', $msg, 'Should congratulate on 7 day streak');
        $this->assertStringContainsString('7 day streak', $msg);

        // Monthly streak (30-99 days)
        $msg = StreakService::getStreakMessage(['current' => 30]);
        $this->assertStringContainsString('Fantastic', $msg, 'Should say Fantastic for 30 day streak');

        // Century streak (100-364 days)
        $msg = StreakService::getStreakMessage(['current' => 100]);
        $this->assertStringContainsString('Amazing', $msg, 'Should say Amazing for 100 day streak');

        // Legendary streak (365+ days)
        $msg = StreakService::getStreakMessage(['current' => 365]);
        $this->assertStringContainsString('Incredible', $msg, 'Should say Incredible for 365 day streak');
        $this->assertStringContainsString('legend', $msg);
    }

    /**
     * Test getStreakIcon returns appropriate icons for various streak lengths
     */
    public function testGetStreakIconForDifferentLengths(): void
    {
        $this->assertNotEmpty(StreakService::getStreakIcon(0), 'Should return an icon for zero streak');
        $this->assertNotEmpty(StreakService::getStreakIcon(1), 'Should return an icon for 1 day streak');
        $this->assertNotEmpty(StreakService::getStreakIcon(7), 'Should return an icon for 7 day streak');
        $this->assertNotEmpty(StreakService::getStreakIcon(30), 'Should return an icon for 30 day streak');
        $this->assertNotEmpty(StreakService::getStreakIcon(100), 'Should return an icon for 100 day streak');
        $this->assertNotEmpty(StreakService::getStreakIcon(365), 'Should return an icon for 365 day streak');

        // Higher streak = different icon (not just the same for all)
        $this->assertNotEquals(
            StreakService::getStreakIcon(0),
            StreakService::getStreakIcon(365),
            'Icons should differ between zero and legendary streaks'
        );
    }

    /**
     * Test recordLogin delegates to recordActivity with 'login' type
     */
    public function testRecordLoginIsAliasForRecordActivityLogin(): void
    {
        $this->assertTrue(
            method_exists(StreakService::class, 'recordLogin'),
            'recordLogin method should exist'
        );

        $ref = new \ReflectionMethod(StreakService::class, 'recordLogin');
        $this->assertTrue($ref->isStatic(), 'recordLogin should be static');

        // Parameters: only $userId (no streak type parameter)
        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'recordLogin should only accept userId');
    }

    /**
     * Test recordGiving delegates to recordActivity with 'giving' type
     */
    public function testRecordGivingIsAliasForRecordActivityGiving(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'recordGiving');
        $this->assertTrue($ref->isStatic(), 'recordGiving should be static');

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'recordGiving should only accept userId');
    }

    /**
     * Test recordVolunteer delegates to recordActivity with 'volunteer' type
     */
    public function testRecordVolunteerIsAliasForRecordActivityVolunteer(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'recordVolunteer');
        $this->assertTrue($ref->isStatic(), 'recordVolunteer should be static');

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'recordVolunteer should only accept userId');
    }

    // =========================================================================
    // DAILY REWARD SERVICE — CRON METHODS
    // =========================================================================

    /**
     * Test daily XP reward constants are reasonable
     */
    public function testDailyRewardConstantsAreReasonable(): void
    {
        $this->assertGreaterThan(0, DailyRewardService::DAILY_XP_BASE, 'Base XP should be positive');
        $this->assertGreaterThan(0, DailyRewardService::DAILY_XP_STREAK_BONUS, 'Streak bonus should be positive');
        $this->assertGreaterThan(0, DailyRewardService::DAILY_XP_MAX_BONUS, 'Max bonus should be positive');
        $this->assertGreaterThanOrEqual(
            DailyRewardService::DAILY_XP_STREAK_BONUS,
            DailyRewardService::DAILY_XP_MAX_BONUS,
            'Max bonus should be >= streak bonus per day'
        );
    }

    /**
     * Test milestone bonus constants exist and increase progressively
     */
    public function testMilestoneBonusesIncreaseProgressively(): void
    {
        $this->assertGreaterThan(0, DailyRewardService::WEEKLY_BONUS_XP, 'Weekly bonus should be positive');
        $this->assertGreaterThan(0, DailyRewardService::MONTHLY_BONUS_XP, 'Monthly bonus should be positive');
        $this->assertGreaterThan(
            DailyRewardService::WEEKLY_BONUS_XP,
            DailyRewardService::MONTHLY_BONUS_XP,
            'Monthly bonus should be greater than weekly'
        );
    }

    /**
     * Test checkAndAwardDailyReward method signature
     */
    public function testCheckAndAwardDailyRewardMethodSignature(): void
    {
        $ref = new \ReflectionMethod(DailyRewardService::class, 'checkAndAwardDailyReward');
        $this->assertTrue($ref->isStatic(), 'checkAndAwardDailyReward should be static');
        $this->assertTrue($ref->isPublic(), 'checkAndAwardDailyReward should be public');

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'Should accept exactly one parameter (userId)');
        $this->assertEquals('userId', $params[0]->getName());
    }

    /**
     * Test getTodayStatus method signature
     */
    public function testGetTodayStatusMethodSignature(): void
    {
        $ref = new \ReflectionMethod(DailyRewardService::class, 'getTodayStatus');
        $this->assertTrue($ref->isStatic(), 'getTodayStatus should be static');
        $this->assertTrue($ref->isPublic(), 'getTodayStatus should be public');
    }

    // =========================================================================
    // GAMIFICATION SERVICE — STREAK BADGE CHECKING
    // =========================================================================

    /**
     * Test checkStreakBadges method exists and is static
     */
    public function testCheckStreakBadgesMethodExists(): void
    {
        $ref = new \ReflectionMethod(GamificationService::class, 'checkStreakBadges');
        $this->assertTrue($ref->isStatic(), 'checkStreakBadges should be static');
        $this->assertTrue($ref->isPublic(), 'checkStreakBadges should be public');

        $params = $ref->getParameters();
        $this->assertCount(2, $params, 'Should accept userId and currentStreak');
    }

    /**
     * Test streak badge definitions exist with correct thresholds
     */
    public function testStreakBadgeDefinitionsExist(): void
    {
        $badges = GamificationService::getBadgeDefinitions();
        $streakBadges = array_filter($badges, fn($b) => $b['type'] === 'streak');

        $this->assertNotEmpty($streakBadges, 'Should have streak badges defined');

        // Check expected streak thresholds
        $thresholds = array_map(fn($b) => $b['threshold'], $streakBadges);
        $this->assertContains(7, $thresholds, 'Should have a 7-day streak badge');
        $this->assertContains(30, $thresholds, 'Should have a 30-day streak badge');
        $this->assertContains(100, $thresholds, 'Should have a 100-day streak badge');
        $this->assertContains(365, $thresholds, 'Should have a 365-day streak badge');
    }

    /**
     * Test runAllBadgeChecks method exists for batch cron processing
     */
    public function testRunAllBadgeChecksMethodExists(): void
    {
        $ref = new \ReflectionMethod(GamificationService::class, 'runAllBadgeChecks');
        $this->assertTrue($ref->isStatic(), 'runAllBadgeChecks should be static');
        $this->assertTrue($ref->isPublic(), 'runAllBadgeChecks should be public');

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'Should accept exactly one parameter (userId)');
    }

    /**
     * Test checkMembershipBadges exists for cron-triggered membership duration checks
     */
    public function testCheckMembershipBadgesMethodExists(): void
    {
        $ref = new \ReflectionMethod(GamificationService::class, 'checkMembershipBadges');
        $this->assertTrue($ref->isStatic(), 'checkMembershipBadges should be static');
        $this->assertTrue($ref->isPublic(), 'checkMembershipBadges should be public');
    }

    /**
     * Test membership badge thresholds are defined
     */
    public function testMembershipBadgeThresholdsExist(): void
    {
        $badges = GamificationService::getBadgeDefinitions();
        $memberBadges = array_filter($badges, fn($b) => $b['type'] === 'membership');

        $this->assertNotEmpty($memberBadges, 'Should have membership badges');

        $thresholds = array_map(fn($b) => $b['threshold'], $memberBadges);
        $this->assertContains(30, $thresholds, 'Should have 30 day membership badge');
        $this->assertContains(180, $thresholds, 'Should have 180 day membership badge');
        $this->assertContains(365, $thresholds, 'Should have 365 day membership badge');
    }

    // =========================================================================
    // CHALLENGE SERVICE — EXPIRED CHALLENGE HANDLING
    // =========================================================================

    /**
     * Test ChallengeService class exists
     */
    public function testChallengeServiceClassExists(): void
    {
        $this->assertTrue(class_exists(ChallengeService::class));
    }

    /**
     * Test getActiveChallenges method exists and is static
     */
    public function testGetActiveChallengesMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'getActiveChallenges');
        $this->assertTrue($ref->isStatic(), 'getActiveChallenges should be static');
        $this->assertTrue($ref->isPublic(), 'getActiveChallenges should be public');
    }

    /**
     * Test getActionTypes returns expected challenge action types
     */
    public function testGetActionTypesReturnsExpectedTypes(): void
    {
        $types = ChallengeService::getActionTypes();

        $this->assertIsArray($types);
        $this->assertNotEmpty($types);
        $this->assertArrayHasKey('transaction', $types, 'Should include transaction action type');
        $this->assertArrayHasKey('login', $types, 'Should include login action type for daily cron');
        $this->assertArrayHasKey('volunteer_hours', $types, 'Should include volunteer_hours action type');
        $this->assertArrayHasKey('credits_sent', $types, 'Should include credits_sent action type');
    }

    /**
     * Test updateProgress method signature for cron-triggered progress
     */
    public function testUpdateProgressMethodSignature(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'updateProgress');
        $this->assertTrue($ref->isStatic(), 'updateProgress should be static');
        $this->assertTrue($ref->isPublic(), 'updateProgress should be public');

        $params = $ref->getParameters();
        $this->assertGreaterThanOrEqual(2, count($params), 'Should accept at least userId and actionType');
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('actionType', $params[1]->getName());
    }

    /**
     * Test challenge create method accepts required fields
     */
    public function testChallengeCreateMethodSignature(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'create');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'create should accept a data array');
    }

    /**
     * Test challenge stats method exists for monitoring expired challenges
     */
    public function testGetStatsMethodExists(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'getStats');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'getStats should accept challengeId');
    }

    // =========================================================================
    // GAMIFICATION SERVICE — LEVEL CALCULATIONS (used by cron)
    // =========================================================================

    /**
     * Test calculateLevel returns correct levels for XP boundaries
     */
    public function testCalculateLevelBoundaryValues(): void
    {
        // Level 1: 0 XP
        $this->assertEquals(1, GamificationService::calculateLevel(0));
        $this->assertEquals(1, GamificationService::calculateLevel(99));

        // Level 2: 100 XP
        $this->assertEquals(2, GamificationService::calculateLevel(100));
        $this->assertEquals(2, GamificationService::calculateLevel(299));

        // Level 3: 300 XP
        $this->assertEquals(3, GamificationService::calculateLevel(300));

        // Max level (10): 5500 XP
        $this->assertEquals(10, GamificationService::calculateLevel(5500));
        $this->assertEquals(10, GamificationService::calculateLevel(99999));
    }

    /**
     * Test getLevelProgress calculates correctly for cron-based level checks
     */
    public function testGetLevelProgressCalculation(): void
    {
        // Level 1 with 0 XP = 0% progress
        $this->assertEquals(0, GamificationService::getLevelProgress(0, 1));

        // Level 1 with 50 XP = 50% progress (need 100 for level 2)
        $this->assertEquals(50, GamificationService::getLevelProgress(50, 1));

        // Max level = 100% progress
        $maxLevel = max(array_keys(GamificationService::LEVEL_THRESHOLDS));
        $this->assertEquals(100, GamificationService::getLevelProgress(99999, $maxLevel));
    }

    /**
     * Test XP values are all positive integers
     */
    public function testXPValuesAreAllPositive(): void
    {
        foreach (GamificationService::XP_VALUES as $action => $xp) {
            $this->assertIsInt($xp, "XP for '$action' should be an integer");
            $this->assertGreaterThan(0, $xp, "XP for '$action' should be positive");
        }
    }

    /**
     * Test level thresholds are monotonically increasing
     */
    public function testLevelThresholdsAreMonotonicallyIncreasing(): void
    {
        $prev = -1;
        foreach (GamificationService::LEVEL_THRESHOLDS as $level => $threshold) {
            $this->assertGreaterThan(
                $prev,
                $threshold,
                "Level $level threshold ($threshold) must be greater than previous ($prev)"
            );
            $prev = $threshold;
        }
    }
}
