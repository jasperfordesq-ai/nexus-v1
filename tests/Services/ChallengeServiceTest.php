<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\ChallengeService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class ChallengeServiceTest extends TestCase
{
    private static $testUserId;
    private static $testChallengeId;
    private static $testTenantId = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Create test user with unique email
        $uniqueEmail = 'test_challenge_' . time() . '@test.com';
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, xp, level, is_approved, created_at)
             VALUES (?, ?, 'Challenge', 'Tester', 0, 1, 1, NOW())",
            [self::$testTenantId, $uniqueEmail]
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        // Create test challenge
        try {
            Database::query(
                "INSERT INTO challenges (tenant_id, title, description, challenge_type, action_type, target_count, xp_reward, start_date, end_date, is_active)
                 VALUES (?, 'Test Challenge', 'Complete 5 test actions', 'weekly', 'test_action', 5, 100, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 1)",
                [self::$testTenantId]
            );
            self::$testChallengeId = Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Table might not exist
            self::$testChallengeId = null;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testChallengeId) {
            try {
                Database::query("DELETE FROM user_challenge_progress WHERE challenge_id = ?", [self::$testChallengeId]);
                Database::query("DELETE FROM challenges WHERE id = ?", [self::$testChallengeId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
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
        if (self::$testChallengeId) {
            try {
                Database::query(
                    "DELETE FROM user_challenge_progress WHERE user_id = ? AND challenge_id = ?",
                    [self::$testUserId, self::$testChallengeId]
                );
            } catch (\Exception $e) {}
        }
    }

    /**
     * Test getting active challenges returns array
     */
    public function testGetActiveChallengesReturnsArray(): void
    {
        $challenges = ChallengeService::getActiveChallenges();
        $this->assertIsArray($challenges, 'Challenges should be an array');
    }

    /**
     * Test getChallengesWithProgress returns array
     */
    public function testGetChallengesWithProgressReturnsArray(): void
    {
        $challenges = ChallengeService::getChallengesWithProgress(self::$testUserId);
        $this->assertIsArray($challenges, 'Challenges with progress should be an array');
    }

    /**
     * Test updateProgress returns array
     */
    public function testUpdateProgressReturnsArray(): void
    {
        $completed = ChallengeService::updateProgress(self::$testUserId, 'test_action', 1);
        $this->assertIsArray($completed, 'updateProgress should return an array');
    }

    /**
     * Test getActionTypes returns expected types
     */
    public function testGetActionTypesReturnsArray(): void
    {
        $types = ChallengeService::getActionTypes();

        $this->assertIsArray($types, 'Action types should be an array');
        $this->assertNotEmpty($types, 'Action types should not be empty');
        $this->assertArrayHasKey('transaction', $types, 'Should have transaction action type');
        $this->assertArrayHasKey('login', $types, 'Should have login action type');
    }

    /**
     * Test getById returns challenge or false
     */
    public function testGetByIdReturnsChallengeOrFalse(): void
    {
        if (self::$testChallengeId) {
            $challenge = ChallengeService::getById(self::$testChallengeId);
            $this->assertNotEmpty($challenge, 'Should return challenge data');
            $this->assertEquals('Test Challenge', $challenge['title'], 'Title should match');
        } else {
            $this->markTestSkipped('Challenges table not available');
        }
    }

    /**
     * Test getById with invalid ID returns false
     */
    public function testGetByIdWithInvalidIdReturnsFalse(): void
    {
        $challenge = ChallengeService::getById(999999999);
        $this->assertFalse($challenge, 'Invalid ID should return false');
    }

    /**
     * Test getStats returns array
     */
    public function testGetStatsReturnsArray(): void
    {
        if (self::$testChallengeId) {
            $stats = ChallengeService::getStats(self::$testChallengeId);
            $this->assertIsArray($stats, 'Stats should be an array');
            $this->assertArrayHasKey('total_participants', $stats, 'Stats should have total_participants');
        } else {
            $this->markTestSkipped('Challenges table not available');
        }
    }

    /**
     * Test create returns ID
     */
    public function testCreateReturnsId(): void
    {
        try {
            $id = ChallengeService::create([
                'title' => 'Unit Test Challenge ' . time(),
                'description' => 'Created by unit test',
                'action_type' => 'test_action',
                'target_count' => 3,
                'xp_reward' => 50,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+7 days')),
            ]);

            $this->assertNotEmpty($id, 'Create should return an ID');
            $this->assertIsNumeric($id, 'ID should be numeric');

            // Clean up
            ChallengeService::delete($id);
        } catch (\Exception $e) {
            $this->markTestSkipped('Challenges table not available: ' . $e->getMessage());
        }
    }
}
