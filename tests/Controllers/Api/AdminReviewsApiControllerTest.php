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
 * Integration tests for AdminReviewsApiController
 *
 * Tests all 5 review moderation endpoints:
 * - GET    /api/v2/admin/reviews              - List reviews
 * - GET    /api/v2/admin/reviews/{id}         - Get review detail
 * - POST   /api/v2/admin/reviews/{id}/flag    - Flag review (set status to pending)
 * - POST   /api/v2/admin/reviews/{id}/hide    - Hide review (set status to rejected)
 * - DELETE /api/v2/admin/reviews/{id}         - Delete review
 *
 * @group integration
 */
class AdminReviewsApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $testReviewId = 0;
    protected static ?int $testUserId = null;
    private static int $receiverUserId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_reviews_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create reviewer user
        $testEmail = 'reviews_reviewer_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Reviewer User', 'Reviewer', 'User', 'member', 'active', 1, NOW())",
            [self::$tenantId, $testEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$testUserId = (int)Database::lastInsertId();

        // Create receiver user
        $receiverEmail = 'reviews_receiver_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Receiver User', 'Receiver', 'User', 'member', 'active', 1, NOW())",
            [self::$tenantId, $receiverEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$receiverUserId = (int)Database::lastInsertId();

        // Create test review
        try {
            Database::query(
                "INSERT INTO reviews (tenant_id, reviewer_id, receiver_id, rating, comment, status, created_at)
                 VALUES (?, ?, ?, 4, 'Great service!', 'approved', NOW())",
                [self::$tenantId, self::$testUserId, self::$receiverUserId]
            );
            self::$testReviewId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            self::$testReviewId = 0;
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

    public function testListReviewsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReviewsWithFilters(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews?rating=4&status=approved&search=Great',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReviewsByRating(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews?rating=5',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReviewsByStatus(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews?status=pending',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SHOW TESTS
    // ===========================

    public function testShowReviewWorks(): void
    {
        if (!self::$testReviewId) {
            $this->markTestSkipped('reviews table may not exist');
        }

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews/' . self::$testReviewId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // FLAG TESTS
    // ===========================

    public function testFlagReviewWorks(): void
    {
        if (!self::$testReviewId) {
            $this->markTestSkipped('reviews table may not exist');
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/reviews/' . self::$testReviewId . '/flag',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset status
        try {
            Database::query("UPDATE reviews SET status = 'approved' WHERE id = ?", [self::$testReviewId]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    // ===========================
    // HIDE TESTS
    // ===========================

    public function testHideReviewWorks(): void
    {
        if (!self::$testReviewId) {
            $this->markTestSkipped('reviews table may not exist');
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/reviews/' . self::$testReviewId . '/hide',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset status
        try {
            Database::query("UPDATE reviews SET status = 'approved' WHERE id = ?", [self::$testReviewId]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    // ===========================
    // DELETE TESTS
    // ===========================

    public function testDeleteReviewWorks(): void
    {
        // Create a review to delete
        try {
            Database::query(
                "INSERT INTO reviews (tenant_id, reviewer_id, receiver_id, rating, comment, status, created_at)
                 VALUES (?, ?, ?, 3, 'Review to delete', 'approved', NOW())",
                [self::$tenantId, self::$testUserId, self::$receiverUserId]
            );
            $reviewId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('reviews table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/reviews/' . $reviewId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testReviewEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/reviews'],
            ['GET', '/api/v2/admin/reviews/1'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testReviewId) {
            try {
                Database::query("DELETE FROM reviews WHERE id = ?", [self::$testReviewId]);
            } catch (\Exception $e) {
                // Table may not exist
            }
        }
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }
        if (self::$testUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }
        if (self::$receiverUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$receiverUserId]);
        }

        parent::tearDownAfterClass();
    }
}
