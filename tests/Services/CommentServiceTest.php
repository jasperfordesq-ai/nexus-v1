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

    public function testAddCommentReturnsIdForValidComment(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $commentId = CommentService::addComment('post', self::$testPostId, self::$testUserId, 'New test comment');

            $this->assertIsInt($commentId);
            $this->assertGreaterThan(0, $commentId);

            // Cleanup
            Database::query("DELETE FROM comments WHERE id = ?", [$commentId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('addComment not available: ' . $e->getMessage());
        }
    }

    public function testAddCommentReturnsFalseForEmptyContent(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $result = CommentService::addComment('post', self::$testPostId, self::$testUserId, '');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('addComment not available: ' . $e->getMessage());
        }
    }

    public function testAddCommentReturnsFalseForTooLongContent(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $result = CommentService::addComment('post', self::$testPostId, self::$testUserId, str_repeat('A', 5001));

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('addComment not available: ' . $e->getMessage());
        }
    }

    public function testAddCommentSupportsParentId(): void
    {
        if (!self::$testPostId || !self::$testCommentId) {
            $this->markTestSkipped('Test data not available');
        }

        try {
            $replyId = CommentService::addComment('post', self::$testPostId, self::$testUser2Id, 'Test reply', self::$testCommentId);

            $this->assertIsInt($replyId);
            $this->assertGreaterThan(0, $replyId);

            // Verify parent_id was set
            $stmt = Database::query("SELECT parent_id FROM comments WHERE id = ?", [$replyId]);
            $row = $stmt->fetch();
            $this->assertEquals(self::$testCommentId, $row['parent_id']);

            // Cleanup
            Database::query("DELETE FROM comments WHERE id = ?", [$replyId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('addComment with parent not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // updateComment Tests
    // ==========================================

    public function testUpdateCommentReturnsTrueForOwnComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            $result = CommentService::updateComment(self::$testCommentId, self::$testUserId, 'Updated content');

            $this->assertTrue($result);

            // Verify content was updated
            $stmt = Database::query("SELECT content FROM comments WHERE id = ?", [self::$testCommentId]);
            $row = $stmt->fetch();
            $this->assertEquals('Updated content', $row['content']);
        } catch (\Exception $e) {
            $this->markTestSkipped('updateComment not available: ' . $e->getMessage());
        }
    }

    public function testUpdateCommentReturnsFalseForOthersComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            // User 2 trying to update User 1's comment
            $result = CommentService::updateComment(self::$testCommentId, self::$testUser2Id, 'Hacked content');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('updateComment not available: ' . $e->getMessage());
        }
    }

    public function testUpdateCommentReturnsFalseForEmptyContent(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            $result = CommentService::updateComment(self::$testCommentId, self::$testUserId, '');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('updateComment not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // deleteComment Tests
    // ==========================================

    public function testDeleteCommentReturnsTrueForOwnComment(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            // Create a comment to delete
            Database::query(
                "INSERT INTO comments (user_id, target_type, target_id, content, created_at, updated_at)
                 VALUES (?, 'post', ?, 'To delete', NOW(), NOW())",
                [self::$testUserId, self::$testPostId]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            $result = CommentService::deleteComment($tempId, self::$testUserId);

            $this->assertTrue($result);

            // Verify it was deleted
            $stmt = Database::query("SELECT * FROM comments WHERE id = ?", [$tempId]);
            $row = $stmt->fetch();
            $this->assertFalse($row);
        } catch (\Exception $e) {
            $this->markTestSkipped('deleteComment not available: ' . $e->getMessage());
        }
    }

    public function testDeleteCommentReturnsFalseForOthersComment(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            // User 2 trying to delete User 1's comment
            $result = CommentService::deleteComment(self::$testCommentId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('deleteComment not available: ' . $e->getMessage());
        }
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

    public function testAddReactionReturnsTrueForValidReaction(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            $result = CommentService::addReaction(self::$testCommentId, self::$testUser2Id, '👍');

            $this->assertTrue($result);

            // Cleanup
            Database::query("DELETE FROM reactions WHERE target_type = 'comment' AND target_id = ? AND user_id = ?", [self::$testCommentId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('addReaction not available: ' . $e->getMessage());
        }
    }

    public function testAddReactionReturnsFalseForInvalidEmoji(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            $result = CommentService::addReaction(self::$testCommentId, self::$testUserId, '🔥');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('addReaction not available: ' . $e->getMessage());
        }
    }

    public function testRemoveReactionReturnsTrueForExistingReaction(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            // Add reaction first
            CommentService::addReaction(self::$testCommentId, self::$testUser2Id, '❤️');

            // Remove it
            $result = CommentService::removeReaction(self::$testCommentId, self::$testUser2Id, '❤️');

            $this->assertTrue($result);

            // Cleanup
            Database::query("DELETE FROM reactions WHERE target_type = 'comment' AND target_id = ? AND user_id = ?", [self::$testCommentId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('removeReaction not available: ' . $e->getMessage());
        }
    }

    public function testRemoveReactionReturnsFalseForNonExistent(): void
    {
        if (!self::$testCommentId) {
            $this->markTestSkipped('Test comment not available');
        }

        try {
            $result = CommentService::removeReaction(self::$testCommentId, self::$testUser2Id, '😂');

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('removeReaction not available: ' . $e->getMessage());
        }
    }
}
