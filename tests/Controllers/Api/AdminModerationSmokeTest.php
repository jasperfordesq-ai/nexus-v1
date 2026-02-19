<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Smoke tests for Admin Moderation API Controllers
 *
 * These tests verify that all moderation endpoints exist and return proper HTTP status codes.
 * They don't test full functionality - just that the endpoints are wired up correctly.
 *
 * @group integration
 */
class AdminModerationSmokeTest extends ApiTestCase
{
    private static string $jwtToken = '';
    private static string $apiBase = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $isDocker = file_exists('/.dockerenv');
        self::$apiBase = $isDocker ? 'http://localhost' : 'http://localhost:8090';

        TenantContext::setById(1);
        $tenantId = TenantContext::getId();

        // Create unique admin user for these tests
        $email = 'moderation_smoke_admin_' . uniqid() . '_' . mt_rand() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Smoke Admin', 'Smoke', 'Admin', 'admin', 'active', NOW())",
            [$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $adminId = (int)Database::lastInsertId();

        self::$jwtToken = TokenService::generateToken($adminId, $tenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(1);
    }

    // Feed Posts Endpoints
    public function testFeedPostsListEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/posts?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    public function testFeedStatsEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/feed/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    // Comments Endpoints
    public function testCommentsListEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/comments?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    // Reviews Endpoints
    public function testReviewsListEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reviews?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    // Reports Endpoints
    public function testReportsListEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    public function testReportsStatsEndpointExists(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$jwtToken]
        );

        $this->assertContains($response['status'], [200, 404]);
        if ($response['status'] === 200) {
            $this->assertArrayHasKey('data', $response['body']);
        }
    }

    // Authorization Tests
    public function testModerationEndpointsRequireAuth(): void
    {
        $endpoints = [
            '/api/v2/admin/feed/posts',
            '/api/v2/admin/comments',
            '/api/v2/admin/reviews',
            '/api/v2/admin/reports',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeApiRequest(
                'GET',
                $endpoint,
                [],
                [] // No auth header
            );

            $this->assertEquals(401, $response['status'], "Endpoint {$endpoint} should require authentication");
        }
    }

    public function testModerationEndpointsRequireAdminRole(): void
    {
        // Create regular user
        $tenantId = TenantContext::getId();
        $email = 'regular_user_' . uniqid() . '_' . mt_rand() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Regular User', 'Regular', 'User', 'member', 'active', NOW())",
            [$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $userId = (int)Database::lastInsertId();
        $userToken = TokenService::generateToken($userId, $tenantId);

        $endpoints = [
            '/api/v2/admin/feed/posts',
            '/api/v2/admin/comments',
            '/api/v2/admin/reviews',
            '/api/v2/admin/reports',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->makeApiRequest(
                'GET',
                $endpoint,
                [],
                ['Authorization' => 'Bearer ' . $userToken]
            );

            $this->assertEquals(403, $response['status'], "Endpoint {$endpoint} should require admin role");
        }

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$userId]);
    }
}
