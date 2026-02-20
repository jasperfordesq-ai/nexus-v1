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
use Nexus\Services\GroupModerationService;

/**
 * GroupModerationService Tests
 *
 * Tests content moderation, flagging, and safety tools
 * for the groups module.
 */
class GroupModerationServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;
    protected static ?int $testFlagId = null;

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
            [self::$testTenantId, "grpmod_{$ts}@test.com", "grpmod_{$ts}", 'Mod', 'User', 'Mod User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [self::$testTenantId, "Moderation Group {$ts}", 'Test group for moderation', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testFlagId) {
            try {
                Database::query("DELETE FROM group_content_flags WHERE id = ?", [self::$testFlagId]);
            } catch (\Exception $e) {}
        }
        if (self::$testGroupId) {
            try {
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

    public function testActionConstantsExist(): void
    {
        $this->assertEquals('flag', GroupModerationService::ACTION_FLAG);
        $this->assertEquals('hide', GroupModerationService::ACTION_HIDE);
        $this->assertEquals('delete', GroupModerationService::ACTION_DELETE);
        $this->assertEquals('approve', GroupModerationService::ACTION_APPROVE);
    }

    public function testContentTypeConstantsExist(): void
    {
        $this->assertEquals('group', GroupModerationService::CONTENT_GROUP);
        $this->assertEquals('discussion', GroupModerationService::CONTENT_DISCUSSION);
        $this->assertEquals('post', GroupModerationService::CONTENT_POST);
    }

    public function testReasonConstantsExist(): void
    {
        $this->assertEquals('spam', GroupModerationService::REASON_SPAM);
        $this->assertEquals('harassment', GroupModerationService::REASON_HARASSMENT);
        $this->assertEquals('inappropriate', GroupModerationService::REASON_INAPPROPRIATE);
        $this->assertEquals('hate_speech', GroupModerationService::REASON_HATE_SPEECH);
    }

    // ==========================================
    // Flag Content Tests
    // ==========================================

    public function testFlagContentCreatesFlagRecord(): void
    {
        $flagId = GroupModerationService::flagContent(
            GroupModerationService::CONTENT_GROUP,
            self::$testGroupId,
            self::$testUserId,
            GroupModerationService::REASON_SPAM,
            'This looks like spam'
        );

        $this->assertNotNull($flagId);
        $this->assertIsNumeric($flagId);
        self::$testFlagId = (int)$flagId;
    }

    public function testFlagContentStoresAllDetails(): void
    {
        $flagId = GroupModerationService::flagContent(
            GroupModerationService::CONTENT_POST,
            123,
            self::$testUserId,
            GroupModerationService::REASON_HARASSMENT,
            'Test description'
        );

        $stmt = Database::query("SELECT * FROM group_content_flags WHERE id = ?", [$flagId]);
        $flag = $stmt->fetch();

        $this->assertEquals(GroupModerationService::CONTENT_POST, $flag['content_type']);
        $this->assertEquals(123, $flag['content_id']);
        $this->assertEquals(self::$testUserId, $flag['reported_by']);
        $this->assertEquals(GroupModerationService::REASON_HARASSMENT, $flag['reason']);
        $this->assertEquals('Test description', $flag['description']);

        // Cleanup
        Database::query("DELETE FROM group_content_flags WHERE id = ?", [$flagId]);
    }

    // ==========================================
    // Moderate Content Tests
    // ==========================================

    public function testModerateContentUpdatesFlag(): void
    {
        $flagId = GroupModerationService::flagContent(
            GroupModerationService::CONTENT_GROUP,
            self::$testGroupId,
            self::$testUserId
        );

        $result = GroupModerationService::moderateContent(
            $flagId,
            GroupModerationService::ACTION_APPROVE,
            self::$testUserId,
            'Content is fine'
        );

        $this->assertTrue($result);

        // Cleanup
        Database::query("DELETE FROM group_content_flags WHERE id = ?", [$flagId]);
    }

    public function testModerateContentReturnsFalseForInvalidFlag(): void
    {
        $result = GroupModerationService::moderateContent(
            999999,
            GroupModerationService::ACTION_APPROVE,
            self::$testUserId
        );

        $this->assertFalse($result);
    }

    // ==========================================
    // Get Flags Tests
    // ==========================================

    public function testGetPendingFlagsReturnsArray(): void
    {
        $flags = GroupModerationService::getPendingFlags();
        $this->assertIsArray($flags);
    }

    public function testGetPendingFlagsFiltersByContentType(): void
    {
        $flags = GroupModerationService::getPendingFlags([
            'content_type' => GroupModerationService::CONTENT_GROUP
        ]);

        $this->assertIsArray($flags);
    }

    // ==========================================
    // Statistics Tests
    // ==========================================

    public function testGetStatisticsReturnsArray(): void
    {
        $stats = GroupModerationService::getStatistics();
        $this->assertIsArray($stats);
    }
}
