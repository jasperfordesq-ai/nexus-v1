<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\StreakService;
use App\Services\DailyRewardService;
use App\Services\GamificationService;
use App\Services\ChallengeService;

/**
 * GamificationCronTest
 *
 * Tests for cron-related gamification operations:
 * - Streak resets (daily expired streaks, weekly freeze resets)
 * - Daily reward processing
 * - Challenge lifecycle (expired challenges, progress tracking)
 *
 * @covers \App\Services\StreakService
 * @covers \App\Services\DailyRewardService
 * @covers \App\Services\GamificationService
 * @covers \App\Services\ChallengeService
 */
class GamificationCronTest extends \Tests\Laravel\TestCase
{
    // =========================================================================
    // STREAK SERVICE — CRON METHODS
    // =========================================================================

    /**
     * Test that recordActivity (the cron-relevant activity recorder) is static.
     *
     * The current StreakService exposes recordActivity(tenantId, userId) rather
     * than the older checkExpiredStreaks/resetWeeklyFreezes cron helpers; streak
     * expiry is computed lazily on read (see getStreak / updateStreak).
     */
    public function testRecordActivityMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'recordActivity');
        $this->assertTrue($ref->isStatic(), 'recordActivity should be a static method');
        $this->assertTrue($ref->isPublic(), 'recordActivity should be public');
    }

    /**
     * Test that getStreakLeaderboard (used by cron-driven leaderboards) is static.
     */
    public function testGetStreakLeaderboardMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'getStreakLeaderboard');
        $this->assertTrue($ref->isStatic(), 'getStreakLeaderboard should be a static method');
        $this->assertTrue($ref->isPublic(), 'getStreakLeaderboard should be public');
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
     * Test recordActivity signature accepts (tenantId, userId).
     *
     * The streak type is fixed to 'activity' internally; updateStreak() guards
     * against unknown types via the STREAK_TYPES whitelist.
     */
    public function testRecordActivityAcceptsTenantAndUser(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'recordActivity');
        $params = $ref->getParameters();

        $this->assertCount(2, $params, 'recordActivity should accept tenantId and userId');
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('userId', $params[1]->getName());
        $this->assertEquals('bool', (string) $ref->getReturnType(), 'recordActivity should return bool');
    }

    /**
     * Test that the private updateStreak guards against unknown streak types.
     *
     * Only the four whitelisted STREAK_TYPES are valid; anything else returns
     * false without touching the database.
     */
    public function testUpdateStreakRejectsInvalidStreakType(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'updateStreak');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, 1, 'invalid_type');
        $this->assertFalse($result, 'Invalid streak type should return false');

        $resultEmpty = $ref->invoke(null, 1, '');
        $this->assertFalse($resultEmpty, 'Empty streak type should return false');
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
     * Test that 'giving' is a recognised streak type for cron processing.
     *
     * There is no dedicated recordGiving() alias on the current service; giving
     * streaks are recorded through the generic updateStreak('giving') path.
     */
    public function testGivingIsARecognisedStreakType(): void
    {
        $this->assertContains('giving', StreakService::STREAK_TYPES);
    }

    /**
     * Test that 'volunteer' is a recognised streak type for cron processing.
     *
     * There is no dedicated recordVolunteer() alias on the current service;
     * volunteer streaks are recorded through updateStreak('volunteer').
     */
    public function testVolunteerIsARecognisedStreakType(): void
    {
        $this->assertContains('volunteer', StreakService::STREAK_TYPES);
    }

    // =========================================================================
    // DAILY REWARD SERVICE — CRON METHODS
    // =========================================================================

    /**
     * Test daily XP reward base constant is reasonable.
     *
     * The current service stores configuration in the private BASE_XP constant
     * and a STREAK_BONUSES milestone map (read via getRewardConfig()).
     */
    public function testDailyRewardConstantsAreReasonable(): void
    {
        $config = DailyRewardService::getRewardConfig(2);

        $this->assertGreaterThan(0, $config['base_xp'], 'Base XP should be positive');
        $this->assertIsArray($config['streak_bonuses'], 'Streak bonuses should be a map');
        $this->assertNotEmpty($config['streak_bonuses'], 'Streak bonuses should not be empty');
        $this->assertGreaterThan(0, $config['max_streak_bonus'], 'Max streak bonus should be positive');
        $this->assertGreaterThanOrEqual(
            min($config['streak_bonuses']),
            $config['max_streak_bonus'],
            'Max bonus should be >= the smallest milestone bonus'
        );
    }

    /**
     * Test milestone streak bonuses increase progressively with streak length.
     *
     * STREAK_BONUSES is keyed by streak day; longer streaks award larger bonuses.
     */
    public function testMilestoneBonusesIncreaseProgressively(): void
    {
        $bonuses = DailyRewardService::getRewardConfig(2)['streak_bonuses'];

        // Keys (streak days) ascending should yield non-decreasing bonus values.
        ksort($bonuses);
        $previous = 0;
        foreach ($bonuses as $day => $bonus) {
            $this->assertGreaterThan(0, $bonus, "Milestone bonus for day {$day} should be positive");
            $this->assertGreaterThanOrEqual(
                $previous,
                $bonus,
                "Milestone bonus for day {$day} should be >= the previous milestone"
            );
            $previous = $bonus;
        }

        // Sanity: the longest milestone awards strictly more than the shortest.
        $this->assertGreaterThan(min($bonuses), max($bonuses), 'Largest milestone should exceed smallest');
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
     * Test checkMembershipBadges exists for cron-triggered membership duration checks.
     *
     * It is a private static helper invoked from runAllBadgeChecks() (the public
     * cron entry point), so it is correctly not part of the public API.
     */
    public function testCheckMembershipBadgesMethodExists(): void
    {
        $ref = new \ReflectionMethod(GamificationService::class, 'checkMembershipBadges');
        $this->assertTrue($ref->isStatic(), 'checkMembershipBadges should be static');
        $this->assertTrue($ref->isPrivate(), 'checkMembershipBadges is a private helper of runAllBadgeChecks');
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
     * Test getChallengesWithProgress exists for cron/dashboard progress reporting.
     *
     * The current service has no fixed getActionTypes() catalogue — action_type
     * is a free-form column matched directly in updateProgress(). Progress
     * reporting is exposed via getChallengesWithProgress(userId).
     */
    public function testGetChallengesWithProgressMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'getChallengesWithProgress');
        $this->assertTrue($ref->isStatic(), 'getChallengesWithProgress should be static');
        $this->assertTrue($ref->isPublic(), 'getChallengesWithProgress should be public');

        $params = $ref->getParameters();
        $this->assertCount(1, $params, 'getChallengesWithProgress should accept userId');
        $this->assertEquals('userId', $params[0]->getName());
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
        $this->assertCount(2, $params, 'create should accept tenantId and a data array');
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('data', $params[1]->getName());
    }

    /**
     * Test getById exists for monitoring/inspecting individual challenges.
     *
     * The current service has no getStats() aggregate; per-challenge state is
     * read via getById(id, tenantId).
     */
    public function testGetByIdMethodExists(): void
    {
        $ref = new \ReflectionMethod(ChallengeService::class, 'getById');
        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());

        $params = $ref->getParameters();
        $this->assertCount(2, $params, 'getById should accept id and tenantId');
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());
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

        // Level 10: 5500 XP (boundary in the V1 LEVEL_THRESHOLDS table)
        $this->assertEquals(10, GamificationService::calculateLevel(5500));

        // Beyond the highest defined threshold caps at the max level.
        $maxLevel = max(array_keys(GamificationService::LEVEL_THRESHOLDS));
        $maxThreshold = GamificationService::LEVEL_THRESHOLDS[$maxLevel];
        $this->assertEquals($maxLevel, GamificationService::calculateLevel($maxThreshold));
        $this->assertEquals($maxLevel, GamificationService::calculateLevel($maxThreshold + 1_000_000));
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
