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
use Nexus\Models\GroupDiscussion;

/**
 * GroupDiscussion Model Tests
 *
 * Tests discussion creation, retrieval by group, find by ID, and deletion.
 */
class GroupDiscussionTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "grp_disc_test_{$timestamp}@test.com", "grp_disc_test_{$timestamp}", 'GrpDisc', 'Tester', 'GrpDisc Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group (groups table uses owner_id, not user_id, and has no slug column)
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [self::$testTenantId, "Discussion Test Group {$timestamp}", 'Group for discussion tests', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testGroupId) {
                Database::query("DELETE FROM group_posts WHERE discussion_id IN (SELECT id FROM group_discussions WHERE group_id = ?)", [self::$testGroupId]);
                Database::query("DELETE FROM group_discussions WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            }
            if (self::$testUserId) {
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
    // Create Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Test Discussion Topic');

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsDiscussion(): void
    {
        $id = GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Find Me Discussion');

        $discussion = GroupDiscussion::findById($id);
        $this->assertNotFalse($discussion);
        $this->assertEquals('Find Me Discussion', $discussion['title']);
        $this->assertArrayHasKey('author_name', $discussion);
        $this->assertArrayHasKey('author_avatar', $discussion);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $discussion = GroupDiscussion::findById(999999999);
        $this->assertFalse($discussion);
    }

    // ==========================================
    // GetForGroup Tests
    // ==========================================

    public function testGetForGroupReturnsArray(): void
    {
        GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Group Discussion A');

        $discussions = GroupDiscussion::getForGroup(self::$testGroupId);
        $this->assertIsArray($discussions);
        $this->assertNotEmpty($discussions);
    }

    public function testGetForGroupIncludesReplyCount(): void
    {
        $discussions = GroupDiscussion::getForGroup(self::$testGroupId);
        if (!empty($discussions)) {
            $this->assertArrayHasKey('reply_count', $discussions[0]);
            $this->assertArrayHasKey('last_reply_at', $discussions[0]);
            $this->assertArrayHasKey('author_name', $discussions[0]);
        }
    }

    public function testGetForGroupReturnsEmptyForNonExistent(): void
    {
        $discussions = GroupDiscussion::getForGroup(999999999);
        $this->assertIsArray($discussions);
        $this->assertEmpty($discussions);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesDiscussion(): void
    {
        $id = GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Delete Me Discussion');

        GroupDiscussion::delete($id);

        $discussion = GroupDiscussion::findById($id);
        $this->assertFalse($discussion);
    }
}
