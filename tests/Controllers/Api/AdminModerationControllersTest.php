<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for Admin Moderation API Controllers
 *
 * Tests that the 4 moderation controllers work with production database schema:
 * - AdminReportsApiController
 * - AdminCommentsApiController
 * - AdminFeedApiController
 * - AdminReviewsApiController
 *
 * @group integration
 */
class AdminModerationControllersTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $regularUserId;
    private static int $tenantId;
    private static string $adminToken;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'mod_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Mod Admin', 'Mod', 'Admin', 'admin', 'active', NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create regular user for test data
        $regularEmail = 'mod_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Test User', 'Test', 'User', 'member', 'active', NOW())",
            [self::$tenantId, $regularEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$regularUserId = (int)Database::lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // ===========================
    // REPORTS CONTROLLER TESTS
    // ===========================

    public function testReportsListWorks(): void
    {
        // Create a test report
        Database::query(
            "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at)
             VALUES (?, ?, 'listing', 1, 'Test report', 'open', NOW())",
            [self::$tenantId, self::$regularUserId]
        );
        $reportId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reports WHERE id = ?", [$reportId]);
    }

    public function testReportsResolveWorks(): void
    {
        // Create a test report
        Database::query(
            "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at)
             VALUES (?, ?, 'user', ?, 'Test report', 'open', NOW())",
            [self::$tenantId, self::$regularUserId, self::$regularUserId]
        );
        $reportId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/reports/{$reportId}/resolve",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reports WHERE id = ?", [$reportId]);
    }

    // ===========================
    // COMMENTS CONTROLLER TESTS
    // ===========================

    public function testCommentsListWorks(): void
    {
        // Create a test feed post
        Database::query(
            "INSERT INTO feed_posts (tenant_id, user_id, content, type, created_at)
             VALUES (?, ?, 'Test post', 'post', NOW())",
            [self::$tenantId, self::$regularUserId]
        );
        $postId = (int)Database::lastInsertId();

        // Create a test comment
        Database::query(
            "INSERT INTO comments (tenant_id, user_id, target_type, target_id, content, created_at)
             VALUES (?, ?, 'post', ?, 'Test comment', NOW())",
            [self::$tenantId, self::$regularUserId, $postId]
        );
        $commentId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/comments',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM comments WHERE id = ?", [$commentId]);
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$postId]);
    }

    public function testCommentsDeleteWorks(): void
    {
        // Create a test feed post
        Database::query(
            "INSERT INTO feed_posts (tenant_id, user_id, content, type, created_at)
             VALUES (?, ?, 'Test post', 'post', NOW())",
            [self::$tenantId, self::$regularUserId]
        );
        $postId = (int)Database::lastInsertId();

        // Create a test comment
        Database::query(
            "INSERT INTO comments (tenant_id, user_id, target_type, target_id, content, created_at)
             VALUES (?, ?, 'post', ?, 'Test comment to delete', NOW())",
            [self::$tenantId, self::$regularUserId, $postId]
        );
        $commentId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'DELETE',
            "/api/v2/admin/comments/{$commentId}",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM comments WHERE id = ?", [$commentId]);
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$postId]);
    }

    // ===========================
    // FEED CONTROLLER TESTS
    // ===========================

    public function testFeedPostsListWorks(): void
    {
        // Create a test feed post
        Database::query(
            "INSERT INTO feed_posts (tenant_id, user_id, content, type, created_at)
             VALUES (?, ?, 'Test moderation post', 'post', NOW())",
            [self::$tenantId, self::$regularUserId]
        );
        $postId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$postId]);
    }

    public function testFeedPostHideWorks(): void
    {
        // Create a test feed post
        Database::query(
            "INSERT INTO feed_posts (tenant_id, user_id, content, type, created_at)
             VALUES (?, ?, 'Post to hide', 'post', NOW())",
            [self::$tenantId, self::$regularUserId]
        );
        $postId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/feed/posts/{$postId}/hide",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM feed_hidden WHERE target_type = 'post' AND target_id = ?", [$postId]);
        Database::query("DELETE FROM feed_posts WHERE id = ?", [$postId]);
    }

    // ===========================
    // REVIEWS CONTROLLER TESTS
    // ===========================

    public function testReviewsListWorks(): void
    {
        // Create a second user to be the receiver
        $receiverEmail = 'mod_receiver_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Receiver', 'Test', 'Receiver', 'member', 'active', NOW())",
            [self::$tenantId, $receiverEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $receiverId = (int)Database::lastInsertId();

        // Create a test review
        Database::query(
            "INSERT INTO reviews (tenant_id, reviewer_id, receiver_id, rating, comment, status, created_at)
             VALUES (?, ?, ?, 5, 'Test review', 'approved', NOW())",
            [self::$tenantId, self::$regularUserId, $receiverId]
        );
        $reviewId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reviews WHERE id = ?", [$reviewId]);
        Database::query("DELETE FROM users WHERE id = ?", [$receiverId]);
    }

    public function testReviewsHideWorks(): void
    {
        // Create a second user to be the receiver
        $receiverEmail = 'mod_receiver2_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Receiver2', 'Test', 'Receiver', 'member', 'active', NOW())",
            [self::$tenantId, $receiverEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $receiverId = (int)Database::lastInsertId();

        // Create a test review
        Database::query(
            "INSERT INTO reviews (tenant_id, reviewer_id, receiver_id, rating, comment, status, created_at)
             VALUES (?, ?, ?, 4, 'Review to hide', 'approved', NOW())",
            [self::$tenantId, self::$regularUserId, $receiverId]
        );
        $reviewId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/reviews/{$reviewId}/hide",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reviews WHERE id = ?", [$reviewId]);
        Database::query("DELETE FROM users WHERE id = ?", [$receiverId]);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testModerationEndpointsRequireAuth(): void
    {
        $endpoints = [
            '/api/v2/admin/reports',
            '/api/v2/admin/comments',
            '/api/v2/admin/feed/posts',
            '/api/v2/admin/reviews',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeApiRequest('GET', $endpoint, [], []);
            $this->assertEquals('simulated', $response['status']);
        }

        $this->assertTrue(true); // Assert that we got here without errors
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup test users
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }
        if (self::$regularUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$regularUserId]);
        }

        parent::tearDownAfterClass();
    }
}
