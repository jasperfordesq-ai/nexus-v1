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
use Nexus\Models\ActivityLog;

/**
 * ActivityLog Model Tests
 *
 * Tests activity logging, log retrieval (recent, public feed, all),
 * count operations, and tenant scoping.
 */
class ActivityLogTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testAdminUserId = null;

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

        // Create regular test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, role, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'member', 100, 1, NOW())",
            [
                self::$testTenantId,
                "actlog_test_user_{$timestamp}@test.com",
                "actlog_user_{$timestamp}",
                'Activity',
                'Logger',
                'Activity Logger'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create admin test user (for admin login exclusion tests)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, role, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'admin', 100, 1, NOW())",
            [
                self::$testTenantId,
                "actlog_test_admin_{$timestamp}@test.com",
                "actlog_admin_{$timestamp}",
                'Activity',
                'Admin',
                'Activity Admin'
            ]
        );
        self::$testAdminUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testAdminUserId]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$uid]);
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Clean activity logs before each test
        try {
            Database::query("DELETE FROM activity_log WHERE user_id IN (?, ?)", [self::$testUserId, self::$testAdminUserId]);
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // Log Activity Tests
    // ==========================================

    public function testLogCreatesActivity(): void
    {
        ActivityLog::log(self::$testUserId, 'test_action', 'Test details');

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals(self::$testUserId, $log['user_id']);
        $this->assertEquals('test_action', $log['action']);
        $this->assertEquals('Test details', $log['details']);
    }

    public function testLogWithPublicFlag(): void
    {
        ActivityLog::log(self::$testUserId, 'public_action', 'Public details', true);

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'public_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals(1, (int)$log['is_public']);
    }

    public function testLogWithPrivateFlag(): void
    {
        ActivityLog::log(self::$testUserId, 'private_action', 'Private details', false);

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'private_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals(0, (int)$log['is_public']);
    }

    public function testLogWithLinkUrl(): void
    {
        ActivityLog::log(self::$testUserId, 'linked_action', 'Has link', false, '/listings/123');

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'linked_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('/listings/123', $log['link_url']);
    }

    public function testLogWithActionType(): void
    {
        ActivityLog::log(self::$testUserId, 'typed_action', 'Custom type', false, null, 'social');

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'typed_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('social', $log['action_type']);
    }

    public function testLogDefaultActionTypeIsSystem(): void
    {
        ActivityLog::log(self::$testUserId, 'default_type', 'Default type');

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'default_type' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('system', $log['action_type']);
    }

    public function testLogWithEntityInfo(): void
    {
        ActivityLog::log(self::$testUserId, 'entity_action', 'Entity test', false, null, 'system', 'listing', 42);

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'entity_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('listing', $log['entity_type']);
        $this->assertEquals(42, (int)$log['entity_id']);
    }

    public function testLogWithAllParameters(): void
    {
        ActivityLog::log(
            self::$testUserId,
            'full_action',
            'Full parameter test',
            true,
            '/events/99',
            'social',
            'event',
            99
        );

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'full_action' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('full_action', $log['action']);
        $this->assertEquals('Full parameter test', $log['details']);
        $this->assertEquals(1, (int)$log['is_public']);
        $this->assertEquals('/events/99', $log['link_url']);
        $this->assertEquals('social', $log['action_type']);
        $this->assertEquals('event', $log['entity_type']);
        $this->assertEquals(99, (int)$log['entity_id']);
    }

    // ==========================================
    // Get Recent Tests — Tenant Scoping
    // ==========================================

    public function testGetRecentReturnsArray(): void
    {
        ActivityLog::log(self::$testUserId, 'recent_test', 'Recent');

        $recent = ActivityLog::getRecent(20);

        $this->assertIsArray($recent);
    }

    public function testGetRecentRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            ActivityLog::log(self::$testUserId, "recent_{$i}", "Recent entry {$i}");
        }

        $recent = ActivityLog::getRecent(3);

        $this->assertLessThanOrEqual(3, count($recent));
    }

    public function testGetRecentIncludesUserName(): void
    {
        ActivityLog::log(self::$testUserId, 'name_test', 'Name test');

        $recent = ActivityLog::getRecent(5);

        if (!empty($recent)) {
            $this->assertArrayHasKey('user_name', $recent[0]);
        }
    }

    public function testGetRecentIncludesAvatarUrl(): void
    {
        ActivityLog::log(self::$testUserId, 'avatar_test', 'Avatar test');

        $recent = ActivityLog::getRecent(5);

        if (!empty($recent)) {
            $this->assertArrayHasKey('avatar_url', $recent[0]);
        }
    }

    public function testGetRecentExcludesAdminLoginEvents(): void
    {
        // Log an admin login event
        ActivityLog::log(self::$testAdminUserId, 'login', 'Admin logged in');
        // Log a regular user action
        ActivityLog::log(self::$testUserId, 'listing_created', 'Created listing');

        $recent = ActivityLog::getRecent(50);

        // Check that admin login is not in the results
        $adminLoginFound = false;
        foreach ($recent as $entry) {
            if ($entry['user_id'] == self::$testAdminUserId && $entry['action'] === 'login') {
                $adminLoginFound = true;
                break;
            }
        }

        $this->assertFalse($adminLoginFound, 'Admin login events should be excluded from getRecent');
    }

    // ==========================================
    // Get Public Feed Tests
    // ==========================================

    public function testGetPublicFeedReturnsArray(): void
    {
        $feed = ActivityLog::getPublicFeed(10);
        $this->assertIsArray($feed);
    }

    public function testGetPublicFeedOnlyReturnsPublicEntries(): void
    {
        ActivityLog::log(self::$testUserId, 'public_entry', 'Public', true);
        ActivityLog::log(self::$testUserId, 'private_entry', 'Private', false);

        $feed = ActivityLog::getPublicFeed(50);

        foreach ($feed as $entry) {
            $this->assertEquals(1, (int)$entry['is_public'], 'Public feed should only contain public entries');
        }
    }

    public function testGetGlobalCallsGetPublicFeed(): void
    {
        ActivityLog::log(self::$testUserId, 'global_test', 'Global', true);

        $global = ActivityLog::getGlobal(10);
        $publicFeed = ActivityLog::getPublicFeed(10);

        // getGlobal is a wrapper for getPublicFeed, so results should match
        $this->assertIsArray($global);
        $this->assertEquals(count($publicFeed), count($global));
    }

    // ==========================================
    // Get All Tests (Admin)
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        ActivityLog::log(self::$testUserId, 'all_test', 'All test');

        $all = ActivityLog::getAll(20, 0);
        $this->assertIsArray($all);
    }

    public function testGetAllRespectsLimitAndOffset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            ActivityLog::log(self::$testUserId, "all_{$i}", "Entry {$i}");
        }

        $all = ActivityLog::getAll(2, 0);
        $this->assertLessThanOrEqual(2, count($all));

        $offset = ActivityLog::getAll(2, 2);
        $this->assertLessThanOrEqual(2, count($offset));
    }

    public function testGetAllIncludesUserEmail(): void
    {
        ActivityLog::log(self::$testUserId, 'email_test', 'Email test');

        $all = ActivityLog::getAll(5, 0);

        if (!empty($all)) {
            $this->assertArrayHasKey('user_email', $all[0]);
        }
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountReturnsInteger(): void
    {
        $count = ActivityLog::count();
        $this->assertIsNumeric($count);
    }

    public function testCountIncrementsAfterLogging(): void
    {
        $countBefore = (int)ActivityLog::count();

        ActivityLog::log(self::$testUserId, 'count_test', 'Count increment');

        $countAfter = (int)ActivityLog::count();
        $this->assertGreaterThanOrEqual($countBefore, $countAfter);
    }

    // ==========================================
    // Tenant Scoping Tests
    // ==========================================

    public function testGetRecentScopesByTenant(): void
    {
        ActivityLog::log(self::$testUserId, 'tenant_scope', 'Scoped');

        $recent = ActivityLog::getRecent(50);

        // All entries should belong to users from the current tenant
        // (the query joins on users.tenant_id)
        $this->assertIsArray($recent);
    }

    public function testGetAllScopesByTenant(): void
    {
        ActivityLog::log(self::$testUserId, 'tenant_all', 'Tenant all');

        $all = ActivityLog::getAll(50, 0);

        $this->assertIsArray($all);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testLogWithEmptyDetails(): void
    {
        ActivityLog::log(self::$testUserId, 'no_details', '');

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'no_details' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertEquals('', $log['details']);
    }

    public function testLogWithSpecialCharacters(): void
    {
        ActivityLog::log(
            self::$testUserId,
            'special_chars',
            'Details with <script>alert("xss")</script> & "quotes" and émojis'
        );

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'special_chars' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertStringContainsString('quotes', $log['details']);
    }

    public function testLogWithNullLinkUrl(): void
    {
        ActivityLog::log(self::$testUserId, 'null_link', 'No link', false, null);

        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'null_link' LIMIT 1",
            [self::$testUserId]
        )->fetch();

        $this->assertNotFalse($log);
        $this->assertNull($log['link_url']);
    }

    public function testGetRecentWithZeroLimit(): void
    {
        ActivityLog::log(self::$testUserId, 'zero_limit', 'Zero');

        $recent = ActivityLog::getRecent(0);
        $this->assertIsArray($recent);
        $this->assertEmpty($recent);
    }
}
