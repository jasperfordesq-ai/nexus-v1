<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GamificationService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class GamificationServiceTest extends TestCase
{
    private static $testUserId;
    private static $testTenantId = 1;

    public static function setUpBeforeClass(): void
    {
        // Set up test tenant context
        TenantContext::setById(self::$testTenantId);

        // Create a test user with unique email
        $uniqueEmail = 'test_gamification_' . time() . '@test.com';
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, xp, level, is_approved, created_at)
             VALUES (?, ?, 'Test', 'GamificationUser', 'Test GamificationUser', 0, 1, 1, NOW())",
            [self::$testTenantId, $uniqueEmail]
        );
        self::$testUserId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM user_badges WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM user_xp_log WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
    }

    protected function setUp(): void
    {
        // Reset user XP before each test
        Database::query(
            "UPDATE users SET xp = 0, level = 1 WHERE id = ?",
            [self::$testUserId]
        );
        try {
            Database::query(
                "DELETE FROM user_badges WHERE user_id = ?",
                [self::$testUserId]
            );
        } catch (\Exception $e) {}
    }

    /**
     * Test badge definitions exist and have correct structure
     */
    public function testGetBadgeDefinitions(): void
    {
        $badges = GamificationService::getBadgeDefinitions();

        $this->assertIsArray($badges, 'Badge definitions should be an array');
        $this->assertNotEmpty($badges, 'Badge definitions should not be empty');

        // Check structure of a badge
        $firstBadge = reset($badges);
        $this->assertArrayHasKey('key', $firstBadge, 'Badge should have a key');
        $this->assertArrayHasKey('name', $firstBadge, 'Badge should have a name');
        $this->assertArrayHasKey('icon', $firstBadge, 'Badge should have an icon');
        $this->assertArrayHasKey('type', $firstBadge, 'Badge should have a type');
        $this->assertArrayHasKey('threshold', $firstBadge, 'Badge should have a threshold');
    }

    /**
     * Test getting badge by key
     */
    public function testGetBadgeByKey(): void
    {
        $badge = GamificationService::getBadgeByKey('vol_1h');

        $this->assertNotNull($badge, 'Badge should be found by key');
        $this->assertEquals('vol_1h', $badge['key'], 'Badge key should match');
        $this->assertEquals('First Steps', $badge['name'], 'Badge name should match');
    }

    /**
     * Test getting badges by type
     */
    public function testGetBadgesByType(): void
    {
        $volBadges = GamificationService::getBadgesByType('vol');

        $this->assertIsArray($volBadges, 'Should return an array');
        $this->assertNotEmpty($volBadges, 'Should find volunteering badges');

        foreach ($volBadges as $badge) {
            $this->assertEquals('vol', $badge['type'], 'All badges should be of type vol');
        }
    }

    /**
     * Test XP values constants exist
     */
    public function testXPValuesExist(): void
    {
        $this->assertIsArray(GamificationService::XP_VALUES, 'XP_VALUES should be an array');
        $this->assertArrayHasKey('send_credits', GamificationService::XP_VALUES);
        $this->assertArrayHasKey('volunteer_hour', GamificationService::XP_VALUES);
        $this->assertArrayHasKey('create_listing', GamificationService::XP_VALUES);
    }

    /**
     * Test level thresholds exist
     */
    public function testLevelThresholdsExist(): void
    {
        $this->assertIsArray(GamificationService::LEVEL_THRESHOLDS, 'LEVEL_THRESHOLDS should be an array');
        $this->assertEquals(0, GamificationService::LEVEL_THRESHOLDS[1], 'Level 1 should require 0 XP');
        $this->assertGreaterThan(0, GamificationService::LEVEL_THRESHOLDS[2], 'Level 2 should require more than 0 XP');
    }

    /**
     * Test level calculation from XP
     */
    public function testCalculateLevel(): void
    {
        $this->assertEquals(1, GamificationService::calculateLevel(0), 'Level should be 1 with 0 XP');
        $this->assertEquals(1, GamificationService::calculateLevel(50), 'Level should be 1 with 50 XP');
        $this->assertEquals(2, GamificationService::calculateLevel(100), 'Level should be 2 with 100 XP');
        $this->assertEquals(3, GamificationService::calculateLevel(300), 'Level should be 3 with 300 XP');
    }

    /**
     * Test XP for next level calculation
     */
    public function testGetXPForNextLevel(): void
    {
        $xpForLevel2 = GamificationService::getXPForNextLevel(1);
        $this->assertEquals(100, $xpForLevel2, 'Level 2 should require 100 XP');

        $xpForLevel3 = GamificationService::getXPForNextLevel(2);
        $this->assertEquals(300, $xpForLevel3, 'Level 3 should require 300 XP');
    }

    /**
     * Test level progress calculation
     */
    public function testGetLevelProgress(): void
    {
        // User at level 1 with 50 XP (half way to level 2)
        $progress = GamificationService::getLevelProgress(50, 1);
        $this->assertEquals(50, $progress, 'Progress should be 50% (50/100 XP)');

        // User at level 2 with 200 XP (half way to level 3)
        $progress2 = GamificationService::getLevelProgress(200, 2);
        $this->assertEquals(50, $progress2, 'Progress should be 50% (100/200 XP in level)');
    }

    /**
     * Test badge award by key functionality
     */
    public function testAwardBadgeByKey(): void
    {
        $result = GamificationService::awardBadgeByKey(self::$testUserId, 'vol_1h');

        $this->assertTrue($result, 'Badge should be awarded successfully');

        // Verify badge was recorded
        $hasBadge = Database::query(
            "SELECT COUNT(*) as c FROM user_badges WHERE user_id = ? AND badge_key = ?",
            [self::$testUserId, 'vol_1h']
        )->fetch()['c'];

        $this->assertEquals(1, (int)$hasBadge, 'Badge should be recorded in database');
    }

    /**
     * Test duplicate badge prevention
     */
    public function testNoDuplicateBadges(): void
    {
        // Award first time
        $firstResult = GamificationService::awardBadgeByKey(self::$testUserId, 'vol_10h');
        $this->assertTrue($firstResult, 'First award should succeed');

        // Try to award again
        $secondResult = GamificationService::awardBadgeByKey(self::$testUserId, 'vol_10h');
        $this->assertFalse($secondResult, 'Duplicate badge should not be awarded');

        // Check only one badge exists
        $badgeCount = Database::query(
            "SELECT COUNT(*) as c FROM user_badges WHERE user_id = ? AND badge_key = ?",
            [self::$testUserId, 'vol_10h']
        )->fetch()['c'];

        $this->assertEquals(1, (int)$badgeCount, 'Only one instance of badge should exist');
    }

    /**
     * Test invalid badge key returns false
     */
    public function testInvalidBadgeKeyReturnsFalse(): void
    {
        $result = GamificationService::awardBadgeByKey(self::$testUserId, 'nonexistent_badge_xyz');

        $this->assertFalse($result, 'Invalid badge key should return false');
    }

    /**
     * Test getBadgeByKey returns null for invalid key
     */
    public function testGetBadgeByKeyReturnsNullForInvalid(): void
    {
        $badge = GamificationService::getBadgeByKey('nonexistent_badge_xyz');

        $this->assertNull($badge, 'Invalid badge key should return null');
    }

    /**
     * Test badge progress returns array
     */
    public function testGetBadgeProgressReturnsArray(): void
    {
        $progress = GamificationService::getBadgeProgress(self::$testUserId);

        $this->assertIsArray($progress, 'Badge progress should be an array');
    }

    /**
     * Test runAllBadgeChecks doesn't throw exception
     */
    public function testRunAllBadgeChecksDoesNotThrow(): void
    {
        // This should not throw an exception
        $this->expectNotToPerformAssertions();

        GamificationService::runAllBadgeChecks(self::$testUserId);
    }

    /**
     * Test getUserStatsForProgress returns expected structure
     */
    public function testGetUserStatsForProgress(): void
    {
        $stats = GamificationService::getUserStatsForProgress(self::$testUserId);

        $this->assertIsArray($stats, 'Stats should be an array');
        $this->assertArrayHasKey('vol', $stats, 'Stats should include vol');
        $this->assertArrayHasKey('earn', $stats, 'Stats should include earn');
        $this->assertArrayHasKey('spend', $stats, 'Stats should include spend');
        $this->assertArrayHasKey('connection', $stats, 'Stats should include connection');
        $this->assertArrayHasKey('level', $stats, 'Stats should include level');
    }

    /**
     * Test getDashboardData returns expected structure
     */
    public function testGetDashboardData(): void
    {
        $dashboard = GamificationService::getDashboardData(self::$testUserId);

        $this->assertIsArray($dashboard, 'Dashboard data should be an array');
        $this->assertArrayHasKey('user', $dashboard, 'Dashboard should include user');
        $this->assertArrayHasKey('xp', $dashboard, 'Dashboard should include xp');
        $this->assertArrayHasKey('badges', $dashboard, 'Dashboard should include badges');
    }
}
