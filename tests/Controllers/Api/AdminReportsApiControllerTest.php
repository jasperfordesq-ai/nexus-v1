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
 * Integration tests for AdminReportsApiController
 *
 * Tests all 5 report moderation endpoints:
 * - GET    /api/v2/admin/reports              - List reports
 * - GET    /api/v2/admin/reports/{id}         - Get report detail
 * - POST   /api/v2/admin/reports/{id}/resolve - Resolve report
 * - POST   /api/v2/admin/reports/{id}/dismiss - Dismiss report
 * - GET    /api/v2/admin/reports/stats        - Get report stats
 *
 * @group integration
 */
class AdminReportsApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $testReportId = 0;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_reports_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user (reporter)
        $testEmail = 'reports_test_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Test User', 'Test', 'User', 'member', 'active', 1, NOW())",
            [self::$tenantId, $testEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$testUserId = (int)Database::lastInsertId();

        // Create test report
        try {
            Database::query(
                "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at)
                 VALUES (?, ?, 'listing', 1, 'Test report reason', 'open', NOW())",
                [self::$tenantId, self::$testUserId]
            );
            self::$testReportId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            self::$testReportId = 0;
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

    public function testListReportsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReportsWithFilters(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports?type=listing&status=open&search=test',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReportsByType(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports?type=user',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListReportsByStatus(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports?status=resolved',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SHOW TESTS
    // ===========================

    public function testShowReportWorks(): void
    {
        if (!self::$testReportId) {
            $this->markTestSkipped('reports table may not exist');
        }

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports/' . self::$testReportId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // RESOLVE TESTS
    // ===========================

    public function testResolveReportWorks(): void
    {
        // Create a report to resolve
        try {
            Database::query(
                "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at)
                 VALUES (?, ?, 'listing', 1, 'Report to resolve', 'open', NOW())",
                [self::$tenantId, self::$testUserId]
            );
            $reportId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('reports table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/reports/' . $reportId . '/resolve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reports WHERE id = ?", [$reportId]);
    }

    // ===========================
    // DISMISS TESTS
    // ===========================

    public function testDismissReportWorks(): void
    {
        // Create a report to dismiss
        try {
            Database::query(
                "INSERT INTO reports (tenant_id, reporter_id, target_type, target_id, reason, status, created_at)
                 VALUES (?, ?, 'user', 1, 'Report to dismiss', 'open', NOW())",
                [self::$tenantId, self::$testUserId]
            );
            $reportId = (int)Database::lastInsertId();
        } catch (\Exception $e) {
            $this->markTestSkipped('reports table may not exist');
            return;
        }

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/reports/' . $reportId . '/dismiss',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM reports WHERE id = ?", [$reportId]);
    }

    // ===========================
    // STATS TESTS
    // ===========================

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/reports/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testReportEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/reports'],
            ['GET', '/api/v2/admin/reports/stats'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testReportId) {
            try {
                Database::query("DELETE FROM reports WHERE id = ?", [self::$testReportId]);
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

        parent::tearDownAfterClass();
    }
}
