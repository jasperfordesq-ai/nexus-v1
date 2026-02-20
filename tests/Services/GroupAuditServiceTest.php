<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GroupAuditService;

/**
 * GroupAuditService Tests
 *
 * Tests comprehensive audit logging for group actions,
 * including creation, updates, member management, and content moderation.
 */
class GroupAuditServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "grpaudit_{$ts}@test.com", "grpaudit_{$ts}", 'Audit', 'User', 'Audit User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [self::$testTenantId, "Audit Group {$ts}", 'Test group for audit', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_audit_log WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Action Constants Tests
    // ==========================================

    public function testGroupActionConstantsExist(): void
    {
        $this->assertEquals('group_created', GroupAuditService::ACTION_GROUP_CREATED);
        $this->assertEquals('group_updated', GroupAuditService::ACTION_GROUP_UPDATED);
        $this->assertEquals('group_deleted', GroupAuditService::ACTION_GROUP_DELETED);
        $this->assertEquals('group_featured', GroupAuditService::ACTION_GROUP_FEATURED);
    }

    public function testMemberActionConstantsExist(): void
    {
        $this->assertEquals('member_joined', GroupAuditService::ACTION_MEMBER_JOINED);
        $this->assertEquals('member_left', GroupAuditService::ACTION_MEMBER_LEFT);
        $this->assertEquals('member_kicked', GroupAuditService::ACTION_MEMBER_KICKED);
        $this->assertEquals('member_banned', GroupAuditService::ACTION_MEMBER_BANNED);
    }

    public function testContentActionConstantsExist(): void
    {
        $this->assertEquals('discussion_created', GroupAuditService::ACTION_DISCUSSION_CREATED);
        $this->assertEquals('post_created', GroupAuditService::ACTION_POST_CREATED);
        $this->assertEquals('post_moderated', GroupAuditService::ACTION_POST_MODERATED);
    }

    // ==========================================
    // Log Tests
    // ==========================================

    public function testLogCreatesAuditEntry(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId,
            ['field' => 'name', 'old' => 'Old Name', 'new' => 'New Name']
        );

        $this->assertNotNull($logId);
        $this->assertIsInt($logId);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLogIncludesDetails(): void
    {
        $details = ['action' => 'test', 'value' => 123];
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_CREATED,
            self::$testGroupId,
            self::$testUserId,
            $details
        );

        // Verify details stored as JSON
        $stmt = Database::query("SELECT details FROM group_audit_log WHERE id = ?", [$logId]);
        $log = $stmt->fetch();
        $storedDetails = json_decode($log['details'], true);

        $this->assertEquals($details, $storedDetails);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    public function testLogCapturesIPAddress(): void
    {
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId
        );

        $stmt = Database::query("SELECT ip_address FROM group_audit_log WHERE id = ?", [$logId]);
        $log = $stmt->fetch();

        $this->assertArrayHasKey('ip_address', $log);

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    // ==========================================
    // Get Logs Tests
    // ==========================================

    public function testGetLogsForGroupReturnsArray(): void
    {
        $logs = GroupAuditService::getLogsForGroup(self::$testGroupId);
        $this->assertIsArray($logs);
    }

    public function testGetLogsForGroupFiltersbyAction(): void
    {
        // Create test log
        $logId = GroupAuditService::log(
            GroupAuditService::ACTION_GROUP_UPDATED,
            self::$testGroupId,
            self::$testUserId
        );

        $logs = GroupAuditService::getLogsForGroup(
            self::$testGroupId,
            ['action' => GroupAuditService::ACTION_GROUP_UPDATED]
        );

        foreach ($logs as $log) {
            $this->assertEquals(GroupAuditService::ACTION_GROUP_UPDATED, $log['action']);
        }

        // Cleanup
        Database::query("DELETE FROM group_audit_log WHERE id = ?", [$logId]);
    }

    // ==========================================
    // Get Logs For User Tests
    // ==========================================

    public function testGetLogsForUserReturnsArray(): void
    {
        $logs = GroupAuditService::getLogsForUser(self::$testUserId);
        $this->assertIsArray($logs);
    }

    // ==========================================
    // Statistics Tests
    // ==========================================

    public function testGetStatisticsReturnsArray(): void
    {
        $stats = GroupAuditService::getStatistics(self::$testGroupId);
        $this->assertIsArray($stats);
    }
}
