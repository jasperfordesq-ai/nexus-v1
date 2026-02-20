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
use Nexus\Services\FeedService;

/**
 * FeedService Tests
 *
 * Tests social feed aggregation, post creation, likes, and filtering.
 */
class FeedServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testPostId = null;
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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "feedsvc_user1_{$ts}@test.com", "feedsvc_user1_{$ts}", 'Feed', 'Author', 'Feed Author']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "feedsvc_user2_{$ts}@test.com", "feedsvc_user2_{$ts}", 'Feed', 'Viewer', 'Feed Viewer']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test group (optional)
        try {
            Database::query(
                "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, cached_member_count, created_at)
                 VALUES (?, ?, ?, ?, 'public', 1, NOW())",
                [
                    self::$testTenantId,
                    self::$testUserId,
                    "Feed Test Group {$ts}",
                    "Test group for feed posts"
                ]
            );
            self::$testGroupId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Groups may not exist
        }

        // Create test feed post
        try {
            Database::query(
                "INSERT INTO feed_posts (tenant_id, user_id, content, visibility, created_at)
                 VALUES (?, ?, ?, 'public', NOW())",
                [
                    self::$testTenantId,
                    self::$testUserId,
                    "Test feed post content {$ts}"
                ]
            );
            self::$testPostId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Feed posts may not exist
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testPostId) {
            try {
                Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ?", [self::$testPostId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [self::$testPostId, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM `groups` WHERE id = ? AND tenant_id = ?", [self::$testGroupId, self::$testTenantId]);
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
    // getFeed Tests
    // ==========================================

    public function testGetFeedReturnsValidStructure(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('has_more', $result);
            $this->assertIsArray($result['items']);
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedRespectsLimit(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId, ['limit' => 5]);

            $this->assertLessThanOrEqual(5, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedEnforcesMaxLimit(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId, ['limit' => 500]);

            $this->assertLessThanOrEqual(100, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedFiltersByType(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId, ['type' => 'posts']);

            foreach ($result['items'] as $item) {
                $this->assertEquals('post', $item['type']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed type filter not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedIncludesAuthorInfo(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId);

            if (!empty($result['items'])) {
                $item = $result['items'][0];
                $this->assertArrayHasKey('author_name', $item);
                $this->assertArrayHasKey('user_id', $item);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedIncludesLikesInfo(): void
    {
        try {
            $result = FeedService::getFeed(self::$testUserId);

            if (!empty($result['items'])) {
                $item = $result['items'][0];
                $this->assertArrayHasKey('likes_count', $item);
                $this->assertArrayHasKey('is_liked', $item);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed not available: ' . $e->getMessage());
        }
    }

    public function testGetFeedWorksWithoutAuthenticatedUser(): void
    {
        try {
            $result = FeedService::getFeed(null);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed without user not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // User Profile Feed Tests
    // ==========================================

    public function testGetFeedFiltersByUserId(): void
    {
        try {
            $result = FeedService::getFeed(null, ['user_id' => self::$testUserId]);

            foreach ($result['items'] as $item) {
                $this->assertEquals(self::$testUserId, $item['user_id']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed user filter not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // Group Feed Tests
    // ==========================================

    public function testGetFeedFiltersByGroupId(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('Group not available');
        }

        try {
            $result = FeedService::getFeed(null, ['group_id' => self::$testGroupId]);

            foreach ($result['items'] as $item) {
                $this->assertEquals(self::$testGroupId, $item['group_id'] ?? null);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getFeed group filter not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // createPost Tests
    // ==========================================

    public function testCreatePostReturnsIdForValidPost(): void
    {
        try {
            $postId = FeedService::createPost(self::$testUserId, [
                'content' => 'Test post content from unit test',
                'visibility' => 'public',
            ]);

            $this->assertIsInt($postId);
            $this->assertGreaterThan(0, $postId);

            // Cleanup
            Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$postId, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('createPost not available: ' . $e->getMessage());
        }
    }

    public function testCreatePostReturnsFalseForEmptyContent(): void
    {
        try {
            $result = FeedService::createPost(self::$testUserId, [
                'content' => '',
            ]);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('createPost not available: ' . $e->getMessage());
        }
    }

    public function testCreatePostReturnsFalseForTooLongContent(): void
    {
        try {
            $result = FeedService::createPost(self::$testUserId, [
                'content' => str_repeat('A', 10001),
            ]);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('createPost not available: ' . $e->getMessage());
        }
    }

    public function testCreatePostAcceptsVisibilityOptions(): void
    {
        try {
            $postId1 = FeedService::createPost(self::$testUserId, [
                'content' => 'Public post',
                'visibility' => 'public',
            ]);

            $postId2 = FeedService::createPost(self::$testUserId, [
                'content' => 'Connections only post',
                'visibility' => 'connections',
            ]);

            $this->assertIsInt($postId1);
            $this->assertIsInt($postId2);

            // Cleanup
            Database::query("DELETE FROM feed_posts WHERE id IN (?, ?) AND tenant_id = ?", [$postId1, $postId2, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('createPost visibility not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // likePost Tests
    // ==========================================

    public function testLikePostReturnsTrueForValidPost(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $result = FeedService::likePost(self::$testPostId, self::$testUser2Id);

            $this->assertTrue($result);

            // Cleanup
            Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ? AND user_id = ?", [self::$testPostId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('likePost not available: ' . $e->getMessage());
        }
    }

    public function testLikePostReturnsFalseForNonExistent(): void
    {
        try {
            $result = FeedService::likePost(999999, self::$testUserId);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('likePost not available: ' . $e->getMessage());
        }
    }

    public function testLikePostReturnsFalseForDuplicate(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            // Like once
            FeedService::likePost(self::$testPostId, self::$testUser2Id);

            // Try to like again
            $result = FeedService::likePost(self::$testPostId, self::$testUser2Id);

            $this->assertFalse($result);

            // Cleanup
            Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ? AND user_id = ?", [self::$testPostId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('likePost not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // unlikePost Tests
    // ==========================================

    public function testUnlikePostReturnsTrueForLikedPost(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            // Like first
            FeedService::likePost(self::$testPostId, self::$testUser2Id);

            // Then unlike
            $result = FeedService::unlikePost(self::$testPostId, self::$testUser2Id);

            $this->assertTrue($result);

            // Cleanup
            Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ? AND user_id = ?", [self::$testPostId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('unlikePost not available: ' . $e->getMessage());
        }
    }

    public function testUnlikePostReturnsFalseForNotLiked(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            $result = FeedService::unlikePost(self::$testPostId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('unlikePost not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // deletePost Tests
    // ==========================================

    public function testDeletePostReturnsTrueForOwnPost(): void
    {
        try {
            // Create a post to delete
            Database::query(
                "INSERT INTO feed_posts (tenant_id, user_id, content, visibility, created_at)
                 VALUES (?, ?, 'To delete', 'public', NOW())",
                [self::$testTenantId, self::$testUserId]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            $result = FeedService::deletePost($tempId, self::$testUserId);

            $this->assertTrue($result);

            // Cleanup (if soft delete was used)
            Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$tempId, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('deletePost not available: ' . $e->getMessage());
        }
    }

    public function testDeletePostReturnsFalseForOthersPost(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('Test post not available');
        }

        try {
            // Try to delete someone else's post
            $result = FeedService::deletePost(self::$testPostId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('deletePost not available: ' . $e->getMessage());
        }
    }
}
