<?php

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ReferralService;

/**
 * ReferralService Tests
 *
 * Tests referral tracking, codes, and rewards.
 */
class ReferralServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testReferrerId = null;
    protected static ?int $testReferredId = null;
    protected static ?string $testReferralCode = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create referrer user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, xp, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, 0, 1, NOW())",
            [self::$testTenantId, "referrer_{$timestamp}@test.com", "referrer_{$timestamp}", 'Test', 'Referrer']
        );
        self::$testReferrerId = (int)Database::getInstance()->lastInsertId();

        // Create referred user (no referral code yet)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, xp, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, 0, 1, NOW())",
            [self::$testTenantId, "referred_{$timestamp}@test.com", "referred_{$timestamp}", 'Test', 'Referred']
        );
        self::$testReferredId = (int)Database::getInstance()->lastInsertId();

        // Generate referral code for referrer
        self::$testReferralCode = ReferralService::generateReferralCode(self::$testReferrerId);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testReferrerId) {
            try {
                Database::query("DELETE FROM referral_tracking WHERE referrer_id = ?", [self::$testReferrerId]);
                Database::query("DELETE FROM user_badges WHERE user_id = ?", [self::$testReferrerId]);
                Database::query("DELETE FROM xp_log WHERE user_id = ?", [self::$testReferrerId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testReferrerId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testReferrerId]);
            } catch (\Exception $e) {}
        }
        if (self::$testReferredId) {
            try {
                Database::query("DELETE FROM referral_tracking WHERE referred_id = ?", [self::$testReferredId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testReferredId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testReferredId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset referral tracking and user XP
        Database::query(
            "DELETE FROM referral_tracking WHERE referrer_id = ? OR referred_id = ?",
            [self::$testReferrerId, self::$testReferredId]
        );
        Database::query(
            "UPDATE users SET xp = 0, referred_by = NULL WHERE id IN (?, ?)",
            [self::$testReferrerId, self::$testReferredId]
        );
    }

    // ==========================================
    // Referral Code Tests
    // ==========================================

    public function testGenerateReferralCodeReturnsString(): void
    {
        $code = ReferralService::generateReferralCode(self::$testReferrerId);

        $this->assertIsString($code);
        $this->assertNotEmpty($code);
    }

    public function testGenerateReferralCodeIsIdempotent(): void
    {
        $code1 = ReferralService::generateReferralCode(self::$testReferrerId);
        $code2 = ReferralService::generateReferralCode(self::$testReferrerId);

        $this->assertEquals($code1, $code2);
    }

    public function testGetReferralCodeReturnsCode(): void
    {
        $code = ReferralService::getReferralCode(self::$testReferrerId);

        $this->assertIsString($code);
        $this->assertEquals(self::$testReferralCode, $code);
    }

    public function testGetReferralLinkIncludesCode(): void
    {
        $link = ReferralService::getReferralLink(self::$testReferrerId);

        $this->assertIsString($link);
        $this->assertStringContainsString('ref=', $link);
        $this->assertStringContainsString(self::$testReferralCode, $link);
    }

    // ==========================================
    // Track Referral Tests
    // ==========================================

    public function testTrackReferralReturnsTrueOnSuccess(): void
    {
        $result = ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        $this->assertTrue($result);
    }

    public function testTrackReferralCreatesRecord(): void
    {
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        $record = Database::query(
            "SELECT * FROM referral_tracking WHERE referrer_id = ? AND referred_id = ?",
            [self::$testReferrerId, self::$testReferredId]
        )->fetch();

        $this->assertNotEmpty($record);
        $this->assertEquals('pending', $record['status']);
    }

    public function testTrackReferralUpdatesReferredByField(): void
    {
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        $user = Database::query(
            "SELECT referred_by FROM users WHERE id = ?",
            [self::$testReferredId]
        )->fetch();

        $this->assertEquals(self::$testReferrerId, $user['referred_by']);
    }

    public function testTrackReferralReturnsFalseWithEmptyCode(): void
    {
        $result = ReferralService::trackReferral('', self::$testReferredId);

        $this->assertFalse($result);
    }

    public function testTrackReferralReturnsFalseWithInvalidCode(): void
    {
        $result = ReferralService::trackReferral('INVALID_CODE_123', self::$testReferredId);

        $this->assertFalse($result);
    }

    public function testTrackReferralRejectsSelfReferral(): void
    {
        // Try to use own referral code
        $result = ReferralService::trackReferral(self::$testReferralCode, self::$testReferrerId);

        $this->assertFalse($result);
    }

    // ==========================================
    // Mark Active/Engaged Tests
    // ==========================================

    public function testMarkReferralActiveUpdatesStatus(): void
    {
        // First track the referral
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        // Then mark as active
        $result = ReferralService::markReferralActive(self::$testReferredId);

        $this->assertTrue($result);

        $record = Database::query(
            "SELECT status FROM referral_tracking WHERE referred_id = ?",
            [self::$testReferredId]
        )->fetch();

        $this->assertEquals('active', $record['status']);
    }

    public function testMarkReferralActiveReturnsFalseWithNoReferral(): void
    {
        // Don't track referral first
        $result = ReferralService::markReferralActive(self::$testReferredId);

        $this->assertFalse($result);
    }

    public function testMarkReferralEngagedUpdatesStatus(): void
    {
        // Track and activate first
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);
        ReferralService::markReferralActive(self::$testReferredId);

        // Then mark as engaged
        $result = ReferralService::markReferralEngaged(self::$testReferredId);

        $this->assertTrue($result);

        $record = Database::query(
            "SELECT status FROM referral_tracking WHERE referred_id = ?",
            [self::$testReferredId]
        )->fetch();

        $this->assertEquals('engaged', $record['status']);
    }

    // ==========================================
    // Referral Stats Tests
    // ==========================================

    public function testGetReferralStatsReturnsExpectedKeys(): void
    {
        $stats = ReferralService::getReferralStats(self::$testReferrerId);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_referrals', $stats);
        $this->assertArrayHasKey('active_count', $stats);
        $this->assertArrayHasKey('engaged_count', $stats);
        $this->assertArrayHasKey('pending_count', $stats);
        $this->assertArrayHasKey('total_xp_earned', $stats);
        $this->assertArrayHasKey('referral_code', $stats);
        $this->assertArrayHasKey('referral_link', $stats);
    }

    public function testGetReferralStatsCountsCorrectly(): void
    {
        // Track referral
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        $stats = ReferralService::getReferralStats(self::$testReferrerId);

        $this->assertEquals(1, $stats['total_referrals']);
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(0, $stats['active_count']);
    }

    // ==========================================
    // Badge Progress Tests
    // ==========================================

    public function testGetNextBadgeProgressReturnsExpectedStructure(): void
    {
        $progress = ReferralService::getNextBadgeProgress(self::$testReferrerId);

        if ($progress !== null) {
            $this->assertArrayHasKey('badge', $progress);
            $this->assertArrayHasKey('key', $progress);
            $this->assertArrayHasKey('current', $progress);
            $this->assertArrayHasKey('target', $progress);
            $this->assertArrayHasKey('remaining', $progress);
            $this->assertArrayHasKey('percent', $progress);
        }
    }

    // ==========================================
    // Leaderboard Tests
    // ==========================================

    public function testGetReferralLeaderboardReturnsArray(): void
    {
        // Track a referral to have data
        ReferralService::trackReferral(self::$testReferralCode, self::$testReferredId);

        $leaderboard = ReferralService::getReferralLeaderboard(10);

        $this->assertIsArray($leaderboard);
    }

    // ==========================================
    // Badge Constants Tests
    // ==========================================

    public function testReferralBadgesHaveExpectedStructure(): void
    {
        $badges = ReferralService::REFERRAL_BADGES;

        $this->assertIsArray($badges);
        $this->assertNotEmpty($badges);

        foreach ($badges as $key => $badge) {
            $this->assertArrayHasKey('name', $badge);
            $this->assertArrayHasKey('description', $badge);
            $this->assertArrayHasKey('icon', $badge);
            $this->assertArrayHasKey('threshold', $badge);
            $this->assertArrayHasKey('xp_reward', $badge);
        }
    }

    public function testReferralXpConstantsExist(): void
    {
        $xp = ReferralService::REFERRAL_XP;

        $this->assertIsArray($xp);
        $this->assertArrayHasKey('signup', $xp);
        $this->assertArrayHasKey('active', $xp);
        $this->assertArrayHasKey('engaged', $xp);
    }
}
