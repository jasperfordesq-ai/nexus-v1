<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\AiUserLimit;

/**
 * AiUserLimit Model Tests
 *
 * Tests user limit record creation, request permission checks,
 * usage incrementing, limit updates, daily/monthly resets,
 * and admin stats.
 */
class AiUserLimitTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "ai_limit_test_{$timestamp}@test.com", "ai_limit_test_{$timestamp}", 'AiLimit', 'Tester', 'AiLimit Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM ai_user_limits WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // GetOrCreate Tests
    // ==========================================

    public function testGetOrCreateReturnsArray(): void
    {
        $limits = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('daily_limit', $limits);
        $this->assertArrayHasKey('monthly_limit', $limits);
        $this->assertArrayHasKey('daily_used', $limits);
        $this->assertArrayHasKey('monthly_used', $limits);
    }

    public function testGetOrCreateReturnsSameRecord(): void
    {
        $first = AiUserLimit::getOrCreate(self::$testUserId);
        $second = AiUserLimit::getOrCreate(self::$testUserId);

        $this->assertEquals($first['id'], $second['id']);
    }

    // ==========================================
    // CanMakeRequest Tests
    // ==========================================

    public function testCanMakeRequestReturnsStructure(): void
    {
        $result = AiUserLimit::canMakeRequest(self::$testUserId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('daily_used', $result);
        $this->assertArrayHasKey('daily_limit', $result);
        $this->assertArrayHasKey('daily_remaining', $result);
        $this->assertArrayHasKey('monthly_used', $result);
        $this->assertArrayHasKey('monthly_limit', $result);
        $this->assertArrayHasKey('monthly_remaining', $result);
    }

    public function testCanMakeRequestAllowedWhenBelowLimits(): void
    {
        // Reset to ensure clean state
        AiUserLimit::resetDaily(self::$testUserId);
        AiUserLimit::resetMonthly(self::$testUserId);

        $result = AiUserLimit::canMakeRequest(self::$testUserId);
        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }

    // ==========================================
    // IncrementUsage Tests
    // ==========================================

    public function testIncrementUsageIncreasesBothCounters(): void
    {
        AiUserLimit::resetDaily(self::$testUserId);
        AiUserLimit::resetMonthly(self::$testUserId);

        $before = AiUserLimit::getOrCreate(self::$testUserId);

        AiUserLimit::incrementUsage(self::$testUserId, 3);

        $after = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertEquals((int)$before['daily_used'] + 3, (int)$after['daily_used']);
        $this->assertEquals((int)$before['monthly_used'] + 3, (int)$after['monthly_used']);
    }

    public function testIncrementUsageDefaultsToOne(): void
    {
        AiUserLimit::resetDaily(self::$testUserId);
        AiUserLimit::resetMonthly(self::$testUserId);

        AiUserLimit::incrementUsage(self::$testUserId);

        $limits = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertEquals(1, (int)$limits['daily_used']);
    }

    // ==========================================
    // UpdateLimits Tests
    // ==========================================

    public function testUpdateLimitsChangesLimits(): void
    {
        AiUserLimit::updateLimits(self::$testUserId, 100, 5000);

        $limits = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertEquals(100, (int)$limits['daily_limit']);
        $this->assertEquals(5000, (int)$limits['monthly_limit']);
    }

    // ==========================================
    // Reset Tests
    // ==========================================

    public function testResetDailyResetsCounter(): void
    {
        AiUserLimit::incrementUsage(self::$testUserId, 10);
        AiUserLimit::resetDaily(self::$testUserId);

        $limits = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertEquals(0, (int)$limits['daily_used']);
    }

    public function testResetMonthlyResetsCounter(): void
    {
        AiUserLimit::incrementUsage(self::$testUserId, 10);
        AiUserLimit::resetMonthly(self::$testUserId);

        $limits = AiUserLimit::getOrCreate(self::$testUserId);
        $this->assertEquals(0, (int)$limits['monthly_used']);
    }

    // ==========================================
    // GetUsageStats Tests
    // ==========================================

    public function testGetUsageStatsReturnsStructure(): void
    {
        $stats = AiUserLimit::getUsageStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('total_daily_usage', $stats);
        $this->assertArrayHasKey('total_monthly_usage', $stats);
        $this->assertArrayHasKey('avg_daily_usage', $stats);
        $this->assertArrayHasKey('max_daily_usage', $stats);
    }

    // ==========================================
    // GetTopUsers Tests
    // ==========================================

    public function testGetTopUsersReturnsArray(): void
    {
        $topUsers = AiUserLimit::getTopUsers();
        $this->assertIsArray($topUsers);
    }

    public function testGetTopUsersRespectsLimit(): void
    {
        $topUsers = AiUserLimit::getTopUsers(5);
        $this->assertIsArray($topUsers);
        $this->assertLessThanOrEqual(5, count($topUsers));
    }

    public function testGetTopUsersIncludesUserInfo(): void
    {
        $topUsers = AiUserLimit::getTopUsers();
        if (!empty($topUsers)) {
            $this->assertArrayHasKey('name', $topUsers[0]);
            $this->assertArrayHasKey('email', $topUsers[0]);
        }
    }
}
