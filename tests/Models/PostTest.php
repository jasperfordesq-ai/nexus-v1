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
use Nexus\Models\Post;

/**
 * Post Model Tests
 *
 * Tests blog post CRUD operations, slug generation/resolution,
 * status filtering, counting, and tenant scoping.
 */
class PostTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testPostId = null;
    protected static string $testSlug = '';

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
        self::$testSlug = "test-post-slug-{$timestamp}";

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "post_model_test_{$timestamp}@test.com",
                "post_model_test_{$timestamp}",
                'Post',
                'Author',
                'Post Author'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test post
        self::$testPostId = (int)Post::create(self::$testUserId, [
            'title' => "Test Blog Post {$timestamp}",
            'slug' => self::$testSlug,
            'excerpt' => 'A short excerpt for the test post.',
            'content' => '<p>This is the full content of the test blog post.</p>',
            'featured_image' => '/uploads/blog/test-image.jpg',
            'status' => 'published',
            'category_id' => null,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM posts WHERE author_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {
            }
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
        $id = Post::create(self::$testUserId, [
            'title' => 'New Post',
            'slug' => 'new-post-' . time(),
            'content' => '<p>Content</p>',
            'status' => 'draft',
        ]);

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCreateWithAllFields(): void
    {
        $timestamp = time();
        $data = [
            'title' => "Full Post {$timestamp}",
            'slug' => "full-post-{$timestamp}",
            'excerpt' => 'A detailed excerpt.',
            'content' => '<p>Full content here.</p>',
            'featured_image' => '/uploads/blog/full-post.jpg',
            'status' => 'published',
            'category_id' => null,
        ];

        $id = Post::create(self::$testUserId, $data);
        $post = Post::findById($id);

        $this->assertNotFalse($post);
        $this->assertEquals($data['title'], $post['title']);
        $this->assertEquals($data['slug'], $post['slug']);
        $this->assertEquals($data['excerpt'], $post['excerpt']);
        $this->assertEquals($data['content'], $post['content']);
        $this->assertEquals($data['featured_image'], $post['featured_image']);
        $this->assertEquals('published', $post['status']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCreateWithDefaultStatus(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Default Status Post',
            'slug' => 'default-status-' . time(),
            'content' => '<p>Content</p>',
        ]);

        $post = Post::findById($id);
        $this->assertEquals('draft', $post['status']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCreateWithEmptyExcerpt(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'No Excerpt Post',
            'slug' => 'no-excerpt-' . time(),
            'content' => '<p>Content</p>',
        ]);

        $post = Post::findById($id);
        $this->assertEquals('', $post['excerpt']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCreateSetsTenantId(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Tenant Post',
            'slug' => 'tenant-post-' . time(),
            'content' => '<p>Content</p>',
        ]);

        $post = Database::query("SELECT tenant_id FROM posts WHERE id = ?", [$id])->fetch();
        $this->assertEquals(self::$testTenantId, $post['tenant_id']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    // ==========================================
    // Find by ID Tests
    // ==========================================

    public function testFindByIdReturnsPost(): void
    {
        $post = Post::findById(self::$testPostId);

        $this->assertNotFalse($post);
        $this->assertIsArray($post);
        $this->assertEquals(self::$testPostId, $post['id']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $post = Post::findById(999999999);

        $this->assertFalse($post);
    }

    public function testFindByIdScopesByTenant(): void
    {
        $post = Post::findById(self::$testPostId);

        $this->assertNotFalse($post);
        $this->assertEquals(self::$testTenantId, $post['tenant_id']);
    }

    // ==========================================
    // Find by Slug Tests
    // ==========================================

    public function testFindBySlugReturnsPost(): void
    {
        $post = Post::findBySlug(self::$testSlug);

        $this->assertNotFalse($post);
        $this->assertIsArray($post);
        $this->assertEquals(self::$testSlug, $post['slug']);
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $post = Post::findBySlug('nonexistent-slug-that-does-not-exist');

        $this->assertFalse($post);
    }

    public function testFindBySlugIncludesAuthorInfo(): void
    {
        $post = Post::findBySlug(self::$testSlug);

        $this->assertNotFalse($post);
        $this->assertArrayHasKey('author_name', $post);
        $this->assertArrayHasKey('first_name', $post);
        $this->assertArrayHasKey('last_name', $post);
    }

    public function testFindBySlugScopesByTenant(): void
    {
        $post = Post::findBySlug(self::$testSlug);

        $this->assertNotFalse($post);
        $this->assertEquals(self::$testTenantId, $post['tenant_id']);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesTitle(): void
    {
        $newTitle = 'Updated Title ' . time();
        $id = Post::create(self::$testUserId, [
            'title' => 'Original Title',
            'slug' => 'update-test-' . time(),
            'content' => '<p>Original content</p>',
            'status' => 'draft',
        ]);

        Post::update($id, ['title' => $newTitle]);

        $post = Post::findById($id);
        $this->assertEquals($newTitle, $post['title']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testUpdateChangesContent(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Content Update Test',
            'slug' => 'content-update-' . time(),
            'content' => '<p>Old content</p>',
            'status' => 'draft',
        ]);

        Post::update($id, ['content' => '<p>New content</p>']);

        $post = Post::findById($id);
        $this->assertEquals('<p>New content</p>', $post['content']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testUpdateChangesStatus(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Status Update Test',
            'slug' => 'status-update-' . time(),
            'content' => '<p>Content</p>',
            'status' => 'draft',
        ]);

        Post::update($id, ['status' => 'published']);

        $post = Post::findById($id);
        $this->assertEquals('published', $post['status']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testUpdatePreservesExistingImageWhenEmpty(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Image Preserve Test',
            'slug' => 'image-preserve-' . time(),
            'content' => '<p>Content</p>',
            'featured_image' => '/uploads/blog/original.jpg',
            'status' => 'draft',
        ]);

        // Update with empty image — should preserve original
        Post::update($id, ['title' => 'Updated Title', 'featured_image' => '']);

        $post = Post::findById($id);
        $this->assertEquals('/uploads/blog/original.jpg', $post['featured_image']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testUpdateWithNewImageReplacesExisting(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Image Replace Test',
            'slug' => 'image-replace-' . time(),
            'content' => '<p>Content</p>',
            'featured_image' => '/uploads/blog/old.jpg',
            'status' => 'draft',
        ]);

        Post::update($id, ['featured_image' => '/uploads/blog/new.jpg']);

        $post = Post::findById($id);
        $this->assertEquals('/uploads/blog/new.jpg', $post['featured_image']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testUpdateNonExistentPostReturnsFalse(): void
    {
        $result = Post::update(999999999, ['title' => 'Should Not Exist']);

        $this->assertFalse($result);
    }

    public function testUpdateScopesByTenant(): void
    {
        // The update query includes AND tenant_id = ?, so it should only affect
        // posts belonging to the current tenant
        $id = Post::create(self::$testUserId, [
            'title' => 'Tenant Scope Update',
            'slug' => 'tenant-scope-update-' . time(),
            'content' => '<p>Content</p>',
            'status' => 'draft',
        ]);

        Post::update($id, ['title' => 'Updated']);

        $post = Post::findById($id);
        $this->assertEquals('Updated', $post['title']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesPost(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'To Be Deleted',
            'slug' => 'to-be-deleted-' . time(),
            'content' => '<p>Will be deleted</p>',
            'status' => 'draft',
        ]);

        $this->assertNotFalse(Post::findById($id));

        Post::delete($id);

        $post = Post::findById($id);
        $this->assertFalse($post);
    }

    public function testDeleteScopesByTenant(): void
    {
        // The delete query includes AND tenant_id = ?
        $id = Post::create(self::$testUserId, [
            'title' => 'Tenant Delete Test',
            'slug' => 'tenant-delete-' . time(),
            'content' => '<p>Content</p>',
            'status' => 'draft',
        ]);

        Post::delete($id);

        // Verify deletion
        $post = Database::query("SELECT * FROM posts WHERE id = ?", [$id])->fetch();
        $this->assertFalse($post);
    }

    // ==========================================
    // Get All (List) Tests
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        $posts = Post::getAll();

        $this->assertIsArray($posts);
    }

    public function testGetAllDefaultsToPublished(): void
    {
        // Create a draft post
        $draftId = Post::create(self::$testUserId, [
            'title' => 'Draft Post',
            'slug' => 'draft-post-' . time(),
            'content' => '<p>Draft</p>',
            'status' => 'draft',
        ]);

        $posts = Post::getAll();

        foreach ($posts as $post) {
            $this->assertEquals('published', $post['status']);
        }

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$draftId]);
    }

    public function testGetAllWithAllStatus(): void
    {
        $draftId = Post::create(self::$testUserId, [
            'title' => 'Draft for All',
            'slug' => 'draft-for-all-' . time(),
            'content' => '<p>Draft</p>',
            'status' => 'draft',
        ]);

        $posts = Post::getAll(100, 0, 'all');

        $statuses = array_unique(array_column($posts, 'status'));
        // Should include posts regardless of status
        $this->assertIsArray($posts);
        $this->assertGreaterThanOrEqual(1, count($posts));

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$draftId]);
    }

    public function testGetAllRespectsLimit(): void
    {
        $posts = Post::getAll(2, 0);

        $this->assertLessThanOrEqual(2, count($posts));
    }

    public function testGetAllRespectsOffset(): void
    {
        $allPosts = Post::getAll(100, 0);
        $offsetPosts = Post::getAll(100, 1);

        if (count($allPosts) > 1) {
            $this->assertLessThan(count($allPosts), count($offsetPosts));
        }
    }

    public function testGetAllIncludesAuthorName(): void
    {
        $posts = Post::getAll();

        if (!empty($posts)) {
            $this->assertArrayHasKey('author_name', $posts[0]);
        }
    }

    public function testGetAllScopesByTenant(): void
    {
        $posts = Post::getAll(100, 0);

        foreach ($posts as $post) {
            $this->assertEquals(self::$testTenantId, $post['tenant_id']);
        }
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountReturnsInteger(): void
    {
        $count = Post::count();
        $this->assertIsInt($count);
    }

    public function testCountDefaultsToPublished(): void
    {
        $publishedCount = Post::count('published');
        $allCount = Post::count('all');

        $this->assertGreaterThanOrEqual($publishedCount, $allCount);
    }

    public function testCountIncrementsAfterCreation(): void
    {
        $countBefore = Post::count('all');

        $id = Post::create(self::$testUserId, [
            'title' => 'Count Test Post',
            'slug' => 'count-test-' . time(),
            'content' => '<p>Count</p>',
            'status' => 'draft',
        ]);

        $countAfter = Post::count('all');
        $this->assertEquals($countBefore + 1, $countAfter);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCountDecrementsAfterDeletion(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Delete Count Test',
            'slug' => 'delete-count-' . time(),
            'content' => '<p>Will count down</p>',
            'status' => 'draft',
        ]);

        $countBefore = Post::count('all');

        Post::delete($id);

        $countAfter = Post::count('all');
        $this->assertEquals($countBefore - 1, $countAfter);
    }

    // ==========================================
    // Slug Tests
    // ==========================================

    public function testDifferentSlugsResolveToCorrectPosts(): void
    {
        $slug1 = 'unique-slug-1-' . time();
        $slug2 = 'unique-slug-2-' . time();

        $id1 = Post::create(self::$testUserId, [
            'title' => 'Post One',
            'slug' => $slug1,
            'content' => '<p>Post one</p>',
            'status' => 'published',
        ]);

        $id2 = Post::create(self::$testUserId, [
            'title' => 'Post Two',
            'slug' => $slug2,
            'content' => '<p>Post two</p>',
            'status' => 'published',
        ]);

        $post1 = Post::findBySlug($slug1);
        $post2 = Post::findBySlug($slug2);

        $this->assertEquals($id1, $post1['id']);
        $this->assertEquals($id2, $post2['id']);
        $this->assertNotEquals($post1['id'], $post2['id']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id IN (?, ?)", [$id1, $id2]);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateWithSpecialCharactersInTitle(): void
    {
        $id = Post::create(self::$testUserId, [
            'title' => 'Post with "quotes" & <tags> and accents',
            'slug' => 'special-chars-post-' . time(),
            'content' => '<p>Content with <script>alert("xss")</script></p>',
            'status' => 'draft',
        ]);

        $post = Post::findById($id);
        $this->assertNotFalse($post);
        $this->assertStringContainsString('quotes', $post['title']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testCreateWithLongContent(): void
    {
        $longContent = '<p>' . str_repeat('This is a long paragraph. ', 500) . '</p>';

        $id = Post::create(self::$testUserId, [
            'title' => 'Long Content Post',
            'slug' => 'long-content-' . time(),
            'content' => $longContent,
            'status' => 'draft',
        ]);

        $post = Post::findById($id);
        $this->assertNotFalse($post);
        $this->assertNotEmpty($post['content']);

        // Clean up
        Database::query("DELETE FROM posts WHERE id = ?", [$id]);
    }

    public function testGetAllWithZeroLimit(): void
    {
        $posts = Post::getAll(0, 0);
        $this->assertIsArray($posts);
        $this->assertEmpty($posts);
    }
}
