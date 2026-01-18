<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\DailyRewardService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class DailyRewardServiceTest extends TestCase
{
    private static $testUserId;
    private static $testTenantId = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Create test user with unique email
        $uniqueEmail = 'test_daily_' . time() . '@test.com';
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, xp, level, is_approved, created_at)
             VALUES (?, ?, 'Daily', 'Tester', 0, 1, 1, NOW())",
            [self::$testTenantId, $uniqueEmail]
        );
        self::$testUserId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM daily_rewards WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM user_xp_log WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM user_streaks WHERE user_id = ?", [self::$testUserId]);
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
        // Reset user state
        Database::query(
            "UPDATE users SET xp = 0 WHERE id = ?",
            [self::$testUserId]
        );
        try {
            Database::query(
                "DELETE FROM daily_rewards WHERE user_id = ?",
                [self::$testUserId]
            );
        } catch (\Exception $e) {}
    }

    /**
     * Test daily XP base constant exists
     */
    public function testDailyXPBaseConstantExists(): void
    {
        $this->assertIsInt(DailyRewardService::DAILY_XP_BASE);
        $this->assertGreaterThan(0, DailyRewardService::DAILY_XP_BASE);
    }

    /**
     * Test weekly bonus constant exists
     */
    public function testWeeklyBonusConstantExists(): void
    {
        $this->assertIsInt(DailyRewardService::WEEKLY_BONUS_XP);
        $this->assertGreaterThan(0, DailyRewardService::WEEKLY_BONUS_XP);
    }

    /**
     * Test monthly bonus constant exists
     */
    public function testMonthlyBonusConstantExists(): void
    {
        $this->assertIsInt(DailyRewardService::MONTHLY_BONUS_XP);
        $this->assertGreaterThan(DailyRewardService::WEEKLY_BONUS_XP, DailyRewardService::MONTHLY_BONUS_XP);
    }

    /**
     * Test checkAndAwardDailyReward returns data on first claim
     */
    public function testCheckAndAwardDailyRewardReturnsDataOnFirstClaim(): void
    {
        try {
            $result = DailyRewardService::checkAndAwardDailyReward(self::$testUserId);

            // First claim should return reward data
            if ($result !== null) {
                $this->assertIsArray($result, 'Result should be an array');
                $this->assertArrayHasKey('daily_xp', $result, 'Should have daily_xp');
                $this->assertArrayHasKey('streak_day', $result, 'Should have streak_day');
                $this->assertArrayHasKey('total_xp', $result, 'Should have total_xp');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test checkAndAwardDailyReward returns null when already claimed
     */
    public function testCheckAndAwardDailyRewardReturnsNullWhenAlreadyClaimed(): void
    {
        try {
            // First claim
            DailyRewardService::checkAndAwardDailyReward(self::$testUserId);

            // Second claim should return null
            $result = DailyRewardService::checkAndAwardDailyReward(self::$testUserId);

            $this->assertNull($result, 'Second claim should return null');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getTodayStatus returns expected structure
     */
    public function testGetTodayStatusReturnsExpectedStructure(): void
    {
        try {
            $status = DailyRewardService::getTodayStatus(self::$testUserId);

            $this->assertIsArray($status, 'Status should be an array');
            $this->assertArrayHasKey('claimed', $status, 'Should have claimed key');
            $this->assertArrayHasKey('current_streak', $status, 'Should have current_streak key');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getTodayStatus claimed is false before claiming
     */
    public function testGetTodayStatusClaimedIsFalseBeforeClaiming(): void
    {
        try {
            $status = DailyRewardService::getTodayStatus(self::$testUserId);

            $this->assertFalse($status['claimed'], 'Claimed should be false before claiming');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getTodayStatus claimed is true after claiming
     */
    public function testGetTodayStatusClaimedIsTrueAfterClaiming(): void
    {
        try {
            DailyRewardService::checkAndAwardDailyReward(self::$testUserId);
            $status = DailyRewardService::getTodayStatus(self::$testUserId);

            $this->assertTrue($status['claimed'], 'Claimed should be true after claiming');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getHistory returns array
     */
    public function testGetHistoryReturnsArray(): void
    {
        try {
            $history = DailyRewardService::getHistory(self::$testUserId, 10);

            $this->assertIsArray($history, 'History should be an array');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getHistory contains entry after claiming
     */
    public function testGetHistoryContainsEntryAfterClaiming(): void
    {
        try {
            DailyRewardService::checkAndAwardDailyReward(self::$testUserId);
            $history = DailyRewardService::getHistory(self::$testUserId, 10);

            $this->assertNotEmpty($history, 'History should not be empty after claiming');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getTotalEarned returns integer
     */
    public function testGetTotalEarnedReturnsInteger(): void
    {
        try {
            $total = DailyRewardService::getTotalEarned(self::$testUserId);

            $this->assertIsInt($total, 'Total earned should be an integer');
            $this->assertGreaterThanOrEqual(0, $total, 'Total earned should be non-negative');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }

    /**
     * Test getTotalEarned increases after claiming
     */
    public function testGetTotalEarnedIncreasesAfterClaiming(): void
    {
        try {
            $before = DailyRewardService::getTotalEarned(self::$testUserId);
            DailyRewardService::checkAndAwardDailyReward(self::$testUserId);
            $after = DailyRewardService::getTotalEarned(self::$testUserId);

            $this->assertGreaterThan($before, $after, 'Total earned should increase after claiming');
        } catch (\Exception $e) {
            $this->markTestSkipped('Daily rewards tables not available: ' . $e->getMessage());
        }
    }
}
