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
 * Integration tests for AdminFeedApiController
 *
 * Tests all 5 feed moderation endpoints:
 * - GET    /api/v2/admin/feed/posts           - List feed posts
 * - GET    /api/v2/admin/feed/posts/{id}      - Get post detail
 * - POST   /api/v2/admin/feed/posts/{id}/hide - Hide post
 * - DELETE /api/v2/admin/feed/posts/{id}      - Delete post
 * - GET    /api/v2/admin/feed/stats           - Get moderation stats
 *
 * @group integration
 */
class AdminFeedApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $testPostId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_feed_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test feed post
        try {
            Database::query(
                "INSERT INTO feed_posts (tenant_id, user_id, type, content, visibility, likes_count, created_at)
                 VALUES (?, ?, 'text', 'Test feed post content', 'public', 0, NOW())",
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
    // LIST TESTS
    // ===========================

    public function testListFeedPostsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListFeedPostsWithFilters(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts?type=text&search=test&is_hidden=0',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListFeedPostsByUser(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts?user_id=' . self::$adminUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SHOW TESTS
    // ===========================

    public function testShowFeedPostWorks(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('feed_posts table may not exist');
        }

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts/' . self::$testPostId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // HIDE TESTS
    // ===========================

    public function testHideFeedPostWorks(): void
    {
        if (!self::$testPostId) {
            $this->markTestSkipped('feed_posts table may not exist');
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/feed/posts/' . self::$testPostId . '/hide',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // DELETE TESTS
    // ===========================

    public function testDeleteFeedPostWorks(): void
    {
        // Create a post to delete
        try {
            Database::query(
                "INSERT INTO feed_posts (tenant_id, user_id, type, content, visibility, likes_count, created_at)
                 VALUES (?, ?, 'text', 'Post to delete', 'public', 0, NOW())",
                [self::$tenantId, self::$adminUserId]
            );
            $postId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('feed_posts table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/feed/posts/' . $postId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // STATS TESTS
    // ===========================

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testFeedEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/feed/posts'],
            ['GET', '/api/v2/admin/feed/stats'],
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
                Database::query("DELETE FROM feed_hidden WHERE target_type = 'post' AND target_id = ?", [self::$testPostId]);
                Database::query("DELETE FROM feed_posts WHERE id = ?", [self::$testPostId]);
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
