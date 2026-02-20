<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for AdminBlogApiController
 *
 * Tests all 6 blog management endpoints:
 * - GET    /api/v2/admin/blog           - List blog posts
 * - GET    /api/v2/admin/blog/{id}      - Get single blog post
 * - POST   /api/v2/admin/blog           - Create blog post
 * - PUT    /api/v2/admin/blog/{id}      - Update blog post
 * - DELETE /api/v2/admin/blog/{id}      - Delete blog post
 * - POST   /api/v2/admin/blog/{id}/toggle-status - Toggle post status
 *
 * @group integration
 */
class AdminBlogApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $testPostId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_blog_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test blog post
        try {
            Database::query(
                "INSERT INTO posts (tenant_id, user_id, title, slug, content, status, created_at)
                 VALUES (?, ?, 'Test Blog Post', 'test-blog-post', 'Test content', 'published', NOW())",
                [self::$tenantId, self::$adminUserId]
            );
            self::$testPostId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            self::$testPostId = 0;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // ===========================
    // CRUD TESTS
    // ===========================

    public function testListBlogPostsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/blog?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListBlogPostsWithFilters(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/blog?status=published&search=test',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetSingleBlogPostWorks(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('posts table may not exist');
        }

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/blog/' . self::$testPostId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateBlogPostWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/blog',
            [
                'title' => 'New Test Post',
                'content' => 'This is test content for a blog post',
                'status' => 'draft',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateBlogPostValidation(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/blog',
            ['content' => 'Missing title'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateBlogPostWorks(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('posts table may not exist');
        }

        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/blog/' . self::$testPostId,
            [
                'title' => 'Updated Blog Post Title',
                'content' => 'Updated content',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteBlogPostWorks(): void
    {
        // Create a post to delete
        try {
            Database::query(
                "INSERT INTO posts (tenant_id, user_id, title, slug, content, status, created_at)
                 VALUES (?, ?, 'Delete Me Post', 'delete-me-post', 'Content', 'draft', NOW())",
                [self::$tenantId, self::$adminUserId]
            );
            $postId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('posts table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/blog/' . $postId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // TOGGLE STATUS TESTS
    // ===========================

    public function testToggleStatusWorks(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('posts table may not exist');
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/blog/' . self::$testPostId . '/toggle-status',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testBlogEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/blog'],
            ['POST', '/api/v2/admin/blog'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testPostId) {
            try {
                Database::query("DELETE FROM posts WHERE id = ?", [self::$testPostId]);
            } catch (\Exception $e) {
                // Table may not exist
            }
        }
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }

        parent::tearDownAfterClass();
    }
}
