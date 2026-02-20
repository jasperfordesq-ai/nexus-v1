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
use Nexus\Models\GroupPost;

/**
 * GroupPost Model Tests
 *
 * Tests post creation, retrieval by discussion, and deletion.
 */
class GroupPostTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;
    protected static ?int $testDiscussionId = null;

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
            [self::$testTenantId, "grp_post_test_{$timestamp}@test.com", "grp_post_test_{$timestamp}", 'GrpPost', 'Tester', 'GrpPost Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group (groups table uses owner_id, not user_id, and has no slug column)
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [self::$testTenantId, "Post Test Group {$timestamp}", 'Group for post tests', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();

        // Create test discussion
        self::$testDiscussionId = (int)GroupDiscussion::create(self::$testGroupId, self::$testUserId, 'Post Test Discussion');
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
        $id = GroupPost::create(self::$testDiscussionId, self::$testUserId, 'This is a test reply');

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    // ==========================================
    // GetForDiscussion Tests
    // ==========================================

    public function testGetForDiscussionReturnsArray(): void
    {
        GroupPost::create(self::$testDiscussionId, self::$testUserId, 'Reply for listing');

        $posts = GroupPost::getForDiscussion(self::$testDiscussionId);
        $this->assertIsArray($posts);
        $this->assertNotEmpty($posts);
    }

    public function testGetForDiscussionIncludesAuthorInfo(): void
    {
        $posts = GroupPost::getForDiscussion(self::$testDiscussionId);
        if (!empty($posts)) {
            $this->assertArrayHasKey('author_name', $posts[0]);
            $this->assertArrayHasKey('author_avatar', $posts[0]);
            $this->assertArrayHasKey('content', $posts[0]);
        }
    }

    public function testGetForDiscussionReturnsEmptyForNonExistent(): void
    {
        $posts = GroupPost::getForDiscussion(999999999);
        $this->assertIsArray($posts);
        $this->assertEmpty($posts);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesPost(): void
    {
        $id = GroupPost::create(self::$testDiscussionId, self::$testUserId, 'Post to be deleted');

        GroupPost::delete($id);

        // Verify by checking all posts for the discussion
        $posts = GroupPost::getForDiscussion(self::$testDiscussionId);
        $found = false;
        foreach ($posts as $post) {
            if ($post['id'] == $id) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Deleted post should not appear in discussion');
    }
}
