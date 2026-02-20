<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationActivityService;

/**
 * FederationActivityService Tests
 *
 * Tests the unified federation activity feed combining messages,
 * transactions, and new partner availability.
 */
class FederationActivityServiceTest extends DatabaseTestCase
{
    protected static ?int $testUserId = null;
    protected static ?int $tenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantId = 2; // hour-timebank
        TenantContext::setById(self::$tenantId);

        // Create test user
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenantId, "activity_test_{$timestamp}@test.com", "activity_test_{$timestamp}", 'Activity', 'Test', 'Activity Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getActivityFeed Tests
    // ==========================================

    public function testGetActivityFeedReturnsArray(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId);

        $this->assertIsArray($result);
    }

    public function testGetActivityFeedWithDefaultParams(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId);

        $this->assertIsArray($result);
        // Should return at most 50 items by default
        $this->assertLessThanOrEqual(50, count($result));
    }

    public function testGetActivityFeedWithCustomLimit(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId, 10);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(10, count($result));
    }

    public function testGetActivityFeedWithOffset(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId, 50, 10);

        $this->assertIsArray($result);
    }

    public function testGetActivityFeedItemStructure(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId);

        $this->assertIsArray($result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('icon', $item);
            $this->assertArrayHasKey('color', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('subtitle', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('link', $item);
            $this->assertArrayHasKey('timestamp', $item);
            $this->assertArrayHasKey('is_unread', $item);
            $this->assertArrayHasKey('meta', $item);
            $this->assertContains($item['type'], ['message', 'transaction', 'new_partner']);
        }
    }

    public function testGetActivityFeedWithInvalidUserId(): void
    {
        $result = FederationActivityService::getActivityFeed(999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ==========================================
    // getUnreadCount Tests
    // ==========================================

    public function testGetUnreadCountReturnsInt(): void
    {
        $result = FederationActivityService::getUnreadCount(self::$testUserId);

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testGetUnreadCountForNewUserIsZero(): void
    {
        $result = FederationActivityService::getUnreadCount(999999);

        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    // ==========================================
    // getActivityStats Tests
    // ==========================================

    public function testGetActivityStatsReturnsExpectedStructure(): void
    {
        $result = FederationActivityService::getActivityStats(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('unread_messages', $result);
        $this->assertArrayHasKey('total_messages', $result);
        $this->assertArrayHasKey('transactions_sent', $result);
        $this->assertArrayHasKey('transactions_received', $result);
        $this->assertArrayHasKey('hours_sent', $result);
        $this->assertArrayHasKey('hours_received', $result);
        $this->assertArrayHasKey('partner_count', $result);
    }

    public function testGetActivityStatsValuesAreNumeric(): void
    {
        $result = FederationActivityService::getActivityStats(self::$testUserId);

        $this->assertIsInt($result['unread_messages']);
        $this->assertIsInt($result['total_messages']);
        $this->assertIsInt($result['transactions_sent']);
        $this->assertIsInt($result['transactions_received']);
        $this->assertIsFloat($result['hours_sent']);
        $this->assertIsFloat($result['hours_received']);
        $this->assertIsInt($result['partner_count']);
    }

    public function testGetActivityStatsForNewUserReturnsZeros(): void
    {
        $result = FederationActivityService::getActivityStats(999999);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['unread_messages']);
        $this->assertEquals(0, $result['total_messages']);
        $this->assertEquals(0, $result['transactions_sent']);
        $this->assertEquals(0, $result['transactions_received']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testGetActivityFeedWithZeroLimit(): void
    {
        $result = FederationActivityService::getActivityFeed(self::$testUserId, 0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetActivityStatsConsistentWithUnreadCount(): void
    {
        $stats = FederationActivityService::getActivityStats(self::$testUserId);
        $unread = FederationActivityService::getUnreadCount(self::$testUserId);

        // The unread count from getActivityStats should match getUnreadCount
        $this->assertEquals($unread, $stats['unread_messages']);
    }
}
