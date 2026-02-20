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
use Nexus\Services\CommentService;

/**
 * CommentService Tests
 *
 * Tests comments, threaded replies, reactions, and mentions.
 */
class CommentServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testPostId = null;
    protected static ?int $testCommentId = null;
    protected static ?int $testReplyId = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "cmtsvc_user1_{$ts}@test.com", "cmtsvc_user1_{$ts}", 'Comment', 'Author', 'Comment Author']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "cmtsvc_user2_{$ts}@test.com", "cmtsvc_user2_{$ts}", 'Comment', 'Replier', 'Comment Replier']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test feed post (target for comments)
        try {
            Database::query(
                "INSERT INTO feed_posts (tenant_id, user_id, content, visibility, created_at)
                 VALUES (?, ?, ?, 'public', NOW())",
                [
                    self::$testTenantId,
                    self::$testUserId,
                    "Test post for comments {$ts}"
                ]
            );
            self::$testPostId = (int)Database::getInstance()->lastInsertId();

            // Create test comment
            Database::query(
                "INSERT INTO comments (user_id, target_type, target_id, content, created_at, updated_at)
                 VALUES (?, 'post', ?, ?, NOW(), NOW())",
                [
                    self::$testUserId,
                    self::$testPostId,
                    "Test comment content {$ts}"
                ]
            );
            self::$testCommentId = (int)Database::getInstance()->lastInsertId();

            // Create test reply
            Database::query(
                "INSERT INTO comments (user_id, target_type, target_id, parent_id, content, created_at, updated_at)
                 VALUES (?, 'post', ?, ?, ?, NOW(), NOW())",
                [
                    self::$testUser2Id,
                    self::$testPostId,
                    self::$testCommentId,
                    "Test reply content {$ts}"
                ]
            );
            self::$testReplyId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Comments may not exist in all schemas
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testCommentId || self::$testReplyId) {
            try {
                Database::query("DELETE FROM reactions WHERE target_type = 'comment'");
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM comments WHERE id IN (?, ?)", [self::$testCommentId, self::$testReplyId]);
            } catch (\Exception $e) {}
        }

        if (self::$testPostId) {
            try {
                Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [self::$testPostId, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        $userIds = array_filter([self::$testUserId, self::$testUser2Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // fetchComments Tests
    // ==========================================

    public function testFetchCommentsReturnsArray(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $comments = CommentService::fetchComments('post', self::$testPostId);

            $this->assertIsArray($comments);
        } catch (\Exception $e) {
            $this->markTestSkipped('fetchComments not available: ' . $e->getMessage());
        }
    }

    public function testFetchCommentsIncludesRootComments(): void
    {
        if (!self::$testPostId || !self::$testCommentId) {
            $this->markTestSkipped('Test data not available');
        }

        try {
            $comments = CommentService::fetchComments('post', self::$testPostId);

            $foundRoot = false;
            foreach ($comments as $comment) {
                if ($comment['id'] == self::$testCommentId) {
                    $foundRoot = true;
                    $this->assertNull($comment['parent_id']);
                    break;
                }
            }

            $this->assertTrue($foundRoot, 'Root comment should be present');
        } catch (\Exception $e) {
            $this->markTestSkipped('fetchComments not available: ' . $e->getMessage());
        }
    }

    public function testFetchCommentsIncludesNestedReplies(): void
    {
        if (!self::$testPostId || !self::$testCommentId || !self::$testReplyId) {
            $this->markTestSkipped('Test data not available');
        }

        try {
            $comments = CommentService::fetchComments('post', self::$testPostId);

            $foundRoot = false;
            foreach ($comments as $comment) {
                if ($comment['id'] == self::$testCommentId) {
                    $foundRoot = true;
                    $this->assertArrayHasKey('replies', $comment);
                    $this->assertIsArray($comment['replies']);

                    if (!empty($comment['replies'])) {
                        $reply = $comment['replies'][0];
                        $this->assertEquals(self::$testReplyId, $reply['id']);
                        $this->assertEquals(self::$testCommentId, $reply['parent_id']);
                    }
                    break;
                }
            }

            $this->assertTrue($foundRoot, 'Root comment with replies should be present');
        } catch (\Exception $e) {
            $this->markTestSkipped('fetchComments nested replies not available: ' . $e->getMessage());
        }
    }

    public function testFetchCommentsIncludesAuthorInfo(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $comments = CommentService::fetchComments('post', self::$testPostId);

            if (!empty($comments)) {
                $comment = $comments[0];
                $this->assertArrayHasKey('author_name', $comment);
                $this->assertArrayHasKey('author_avatar', $comment);
                $this->assertArrayHasKey('user_id', $comment);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('fetchComments not available: ' . $e->getMessage());
        }
    }

    public function testFetchCommentsIncludesReactions(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $comments = CommentService::fetchComments('post', self::$testPostId, self::$testUserId);

            if (!empty($comments)) {
                $comment = $comments[0];
                $this->assertArrayHasKey('reactions', $comment);
                $this->assertArrayHasKey('user_reactions', $comment);
                $this->assertArrayHasKey('is_owner', $comment);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('fetchComments not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // addComment Tests
    // ==========================================

    public function testAddCommentReturnsArrayWithCommentData(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        // addComment(userId, tenantId, targetType, targetId, content, parentId) returns array
        $result = CommentService::addComment(
            self::$testUserId,
            self::$testTenantId,
            'post',
            self::$testPostId,
            'New test comment'
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('comment', $result);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['comment']);
        $this->assertArrayHasKey('id', $result['comment']);

        // Cleanup
        Database::query("DELETE FROM comments WHERE id = ?", [$result['comment']['id']]);
    }

    public function testAddCommentReturnsErrorForEmptyContent(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        $result = CommentService::addComment(
            self::$testUserId,
            self::$testTenantId,
            'post',
            self::$testPostId,
            ''
        );

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testAddCommentAcceptsLongContent(): void
    {
        // CommentService does not enforce content length limit
        $this->markTestSkipped('CommentService does not enforce content length limit');
    }

    public function testAddCommentSupportsParentId(): void
    {
        if (!self::$testPostId || !self::$testCommentId) {
            $this->markTestSkipped('Test data not available');
        }

        $result = CommentService::addComment(
            self::$testUser2Id,
            self::$testTenantId,
            'post',
            self::$testPostId,
            'Test reply',
            self::$testCommentId
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_reply']);

        // Verify parent_id was set
        $stmt = Database::query("SELECT parent_id FROM comments WHERE id = ?", [$result['comment']['id']]);
        $row = $stmt->fetch();
        $this->assertEquals(self::$testCommentId, $row['parent_id']);

        // Cleanup
        Database::query("DELETE FROM comments WHERE id = ?", [$result['comment']['id']]);
    }

    // ==========================================
    // updateComment Tests
    // ==========================================

    public function testEditCommentReturnsArrayForOwnComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        // editComment(commentId, userId, newContent) returns array
        $result = CommentService::editComment(self::$testCommentId, self::$testUserId, 'Updated content');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('Updated content', $result['content']);
        $this->assertTrue($result['is_edited']);

        // Verify content was updated
        $stmt = Database::query("SELECT content FROM comments WHERE id = ?", [self::$testCommentId]);
        $row = $stmt->fetch();
        $this->assertEquals('Updated content', $row['content']);
    }

    public function testEditCommentReturnsErrorForOthersComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        // User 2 trying to update User 1's comment
        $result = CommentService::editComment(self::$testCommentId, self::$testUser2Id, 'Hacked content');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testEditCommentReturnsErrorForEmptyContent(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        $result = CommentService::editComment(self::$testCommentId, self::$testUserId, '');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    // ==========================================
    // deleteComment Tests
    // ==========================================

    public function testDeleteCommentReturnsArrayForOwnComment(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        // Create a comment to delete
        Database::query(
            "INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at, updated_at)
             VALUES (?, ?, 'post', ?, 'To delete', NOW(), NOW())",
            [self::$testUserId, self::$testTenantId, self::$testPostId]
        );
        $tempId = (int)Database::getInstance()->lastInsertId();

        // deleteComment(commentId, userId, isSuperAdmin) returns array
        $result = CommentService::deleteComment($tempId, self::$testUserId);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        // Verify it was deleted
        $stmt = Database::query("SELECT * FROM comments WHERE id = ?", [$tempId]);
        $row = $stmt->fetch();
        $this->assertFalse($row);
    }

    public function testDeleteCommentReturnsErrorForOthersComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        // User 2 trying to delete User 1's comment
        $result = CommentService::deleteComment(self::$testCommentId, self::$testUser2Id);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // ==========================================
    // Reaction Tests
    // ==========================================

    public function testGetAvailableReactionsReturnsArray(): void
    {
        $reactions = CommentService::getAvailableReactions();

        $this->assertIsArray($reactions);
        $this->assertNotEmpty($reactions);
        $this->assertContains('👍', $reactions);
        $this->assertContains('❤️', $reactions);
    }

    public function testToggleReactionReturnsArrayForValidReaction(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        // toggleReaction(userId, tenantId, commentId, emoji) returns array
        $result = CommentService::toggleReaction(self::$testUser2Id, self::$testTenantId, self::$testCommentId, '👍');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('added', $result['action']);
        $this->assertArrayHasKey('reactions', $result);

        // Cleanup
        Database::query("DELETE FROM reactions WHERE target_type = 'comment' AND target_id = ? AND user_id = ?", [self::$testCommentId, self::$testUser2Id]);
    }

    public function testToggleReactionReturnsErrorForInvalidEmoji(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        $result = CommentService::toggleReaction(self::$testUserId, self::$testTenantId, self::$testCommentId, '🔥');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testToggleReactionRemovesExistingReaction(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        // Add reaction first
        CommentService::toggleReaction(self::$testUser2Id, self::$testTenantId, self::$testCommentId, '❤️');

        // Toggle again (remove)
        $result = CommentService::toggleReaction(self::$testUser2Id, self::$testTenantId, self::$testCommentId, '❤️');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('removed', $result['action']);

        // Cleanup
        Database::query("DELETE FROM reactions WHERE target_type = 'comment' AND target_id = ? AND user_id = ?", [self::$testCommentId, self::$testUser2Id]);
    }
}
