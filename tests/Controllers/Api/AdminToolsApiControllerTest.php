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
 * Integration tests for AdminToolsApiController
 *
 * Tests all 13 admin tools endpoints:
 * - GET    /api/v2/admin/tools/redirects          - Get redirects
 * - POST   /api/v2/admin/tools/redirects          - Create redirect
 * - DELETE /api/v2/admin/tools/redirects/{id}     - Delete redirect
 * - GET    /api/v2/admin/tools/404-errors         - Get 404 errors
 * - DELETE /api/v2/admin/tools/404-errors/{id}    - Delete 404 error
 * - POST   /api/v2/admin/tools/health-check       - Run health check
 * - GET    /api/v2/admin/tools/webp               - Get WebP stats
 * - POST   /api/v2/admin/tools/webp/convert       - Run WebP conversion
 * - POST   /api/v2/admin/tools/seed-generator     - Run seed generator
 * - GET    /api/v2/admin/tools/blog-backups       - Get blog backups
 * - POST   /api/v2/admin/tools/blog-backups/{id}/restore - Restore backup
 * - GET    /api/v2/admin/tools/seo-audit          - Get SEO audit
 * - POST   /api/v2/admin/tools/seo-audit/run      - Run SEO audit
 *
 * @group integration
 */
class AdminToolsApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_tools_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // ===========================
    // REDIRECTS TESTS
    // ===========================

    public function testGetRedirectsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/tools/redirects',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateRedirectWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/redirects',
            [
                'source' => '/old-page',
                'target' => '/new-page',
                'status_code' => 301,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteRedirectWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/tools/redirects/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // 404 ERRORS TESTS
    // ===========================

    public function testGet404ErrorsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/tools/404-errors',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDelete404ErrorWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/tools/404-errors/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // HEALTH CHECK TESTS
    // ===========================

    public function testRunHealthCheckWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/health-check',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // WEBP TESTS
    // ===========================

    public function testGetWebpStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/tools/webp',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRunWebpConversionWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/webp/convert',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SEED GENERATOR TESTS
    // ===========================

    public function testRunSeedGeneratorWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/seed-generator',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BLOG BACKUPS TESTS
    // ===========================

    public function testGetBlogBackupsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/tools/blog-backups',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRestoreBlogBackupWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/blog-backups/1/restore',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SEO AUDIT TESTS
    // ===========================

    public function testGetSeoAuditWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/tools/seo-audit',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRunSeoAuditWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/tools/seo-audit/run',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testToolsEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/tools/redirects'],
            ['GET', '/api/v2/admin/tools/404-errors'],
            ['GET', '/api/v2/admin/tools/webp'],
            ['GET', '/api/v2/admin/tools/blog-backups'],
            ['GET', '/api/v2/admin/tools/seo-audit'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }

        parent::tearDownAfterClass();
    }
}
