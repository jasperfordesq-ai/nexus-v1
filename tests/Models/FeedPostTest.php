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
use Nexus\Models\FeedPost;

/**
 * FeedPost Model Tests
 *
 * Tests post creation, retrieval by ID, recent feed listing,
 * and tenant scoping.
 */
class FeedPostTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testPostId = null;

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

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "feed_model_test_{$timestamp}@test.com",
                "feed_model_test_{$timestamp}",
                'Feed',
                'Poster',
                'Feed Poster',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create a test post directly (FeedPost::create uses TenantContext internally)
        self::$testPostId = (int)FeedPost::create(
            self::$testUserId,
            'This is a test feed post for model tests.',
            null,
            null,
            null,
            'post'
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM feed_posts WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
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

    public function testCreatePostReturnsId(): void
    {
        $id = FeedPost::create(
            self::$testUserId,
            'A new test post ' . time()
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    public function testCreatePostWithEmoji(): void
    {
        $id = FeedPost::create(
            self::$testUserId,
            'Post with emoji',
            '🎉'
        );

        $post = Database::query("SELECT * FROM feed_posts WHERE id = ?", [$id])->fetch();

        $this->assertNotFalse($post);
        $this->assertEquals('🎉', $post['emoji']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    public function testCreatePostWithImage(): void
    {
        $imageUrl = '/uploads/feed-test-' . time() . '.jpg';

        $id = FeedPost::create(
            self::$testUserId,
            'Post with image',
            null,
            $imageUrl
        );

        $post = Database::query("SELECT * FROM feed_posts WHERE id = ?", [$id])->fetch();

        $this->assertNotFalse($post);
        $this->assertEquals($imageUrl, $post['image_url']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    public function testCreatePostWithParent(): void
    {
        // Create a parent post
        $parentId = FeedPost::create(
            self::$testUserId,
            'Parent post'
        );

        // Create a reply/share that references the parent
        $childId = FeedPost::create(
            self::$testUserId,
            'Child post referencing parent',
            null,
            null,
            (int)$parentId,
            'post'
        );

        $child = Database::query("SELECT * FROM feed_posts WHERE id = ?", [$childId])->fetch();

        $this->assertNotFalse($child);
        $this->assertEquals((int)$parentId, (int)$child['parent_id']);
        $this->assertEquals('post', $child['parent_type']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id IN (?, ?)", [$childId, $parentId]);
    }

    public function testCreatePostSetsTenantId(): void
    {
        $id = FeedPost::create(
            self::$testUserId,
            'Tenant scoped post'
        );

        $post = Database::query("SELECT tenant_id FROM feed_posts WHERE id = ?", [$id])->fetch();

        $this->assertEquals(self::$testTenantId, (int)$post['tenant_id']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsPost(): void
    {
        $post = FeedPost::findById(self::$testPostId);

        $this->assertNotFalse($post);
        $this->assertIsArray($post);
        $this->assertEquals(self::$testPostId, (int)$post['id']);
    }

    public function testFindByIdIncludesAuthorInfo(): void
    {
        $post = FeedPost::findById(self::$testPostId);

        $this->assertArrayHasKey('author_name', $post);
        $this->assertNotEmpty($post['author_name']);
        $this->assertArrayHasKey('author_avatar', $post);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $post = FeedPost::findById(999999999);

        $this->assertFalse($post);
    }

    public function testFindByIdEnforcesTenantIsolation(): void
    {
        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        $post = FeedPost::findById(self::$testPostId);

        // Should not find the post in the wrong tenant (post belongs to tenant 2)
        $this->assertFalse($post, 'Post should not be found from a different tenant context');

        // Restore tenant
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // GetRecent Tests
    // ==========================================

    public function testGetRecentReturnsArray(): void
    {
        $posts = FeedPost::getRecent(10);

        $this->assertIsArray($posts);
    }

    public function testGetRecentRespectsLimit(): void
    {
        $posts = FeedPost::getRecent(3);

        $this->assertIsArray($posts);
        $this->assertLessThanOrEqual(3, count($posts));
    }

    public function testGetRecentScopesByTenant(): void
    {
        $posts = FeedPost::getRecent(50);

        foreach ($posts as $post) {
            $this->assertEquals(self::$testTenantId, (int)$post['tenant_id']);
        }
    }

    public function testGetRecentIncludesUserInfo(): void
    {
        $posts = FeedPost::getRecent(10);

        foreach ($posts as $post) {
            $this->assertArrayHasKey('user_name', $post);
            $this->assertArrayHasKey('avatar_url', $post);
            $this->assertArrayHasKey('type', $post);
            $this->assertEquals('post', $post['type']);
        }
    }

    public function testGetRecentOrderedByNewest(): void
    {
        $posts = FeedPost::getRecent(50);

        $this->assertIsArray($posts);
        for ($i = 1; $i < count($posts); $i++) {
            $this->assertGreaterThanOrEqual(
                $posts[$i]['created_at'],
                $posts[$i - 1]['created_at'],
                'Recent posts should be ordered newest first'
            );
        }
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreatePostWithNullOptionalFields(): void
    {
        $id = FeedPost::create(
            self::$testUserId,
            'Minimal post with only content'
        );

        $post = Database::query("SELECT * FROM feed_posts WHERE id = ?", [$id])->fetch();

        $this->assertNotFalse($post);
        $this->assertNull($post['emoji']);
        $this->assertNull($post['image_url']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    public function testCreatePostWithSpecialCharacters(): void
    {
        $content = "Post with <b>HTML</b>, 'quotes', \"doubles\" & symbols © ®";

        $id = FeedPost::create(
            self::$testUserId,
            $content
        );

        $post = Database::query("SELECT content FROM feed_posts WHERE id = ?", [$id])->fetch();

        $this->assertNotFalse($post);
        $this->assertEquals($content, $post['content']);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }

    public function testCreatePostWithLongContent(): void
    {
        $longContent = str_repeat('This is a long post. ', 200);

        $id = FeedPost::create(
            self::$testUserId,
            $longContent
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$id]);
    }
}
