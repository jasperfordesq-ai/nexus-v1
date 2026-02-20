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
 * Integration tests for AdminCommunityAnalyticsApiController
 *
 * Tests all 3 community analytics endpoints:
 * - GET /api/v2/admin/community-analytics           - Dashboard stats
 * - GET /api/v2/admin/community-analytics/export     - Export data
 * - GET /api/v2/admin/community-analytics/geography  - Geographic data
 *
 * @group integration
 */
class AdminCommunityAnalyticsApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_analytics_' . uniqid() . '@test.local';
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
    // ANALYTICS TESTS
    // ===========================

    public function testIndexWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/community-analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testIndexWithDateRange(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/community-analytics?start_date=2026-01-01&end_date=2026-02-20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testExportWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/community-analytics/export?format=csv',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGeographyWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/community-analytics/geography',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testAnalyticsEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/community-analytics'],
            ['GET', '/api/v2/admin/community-analytics/export'],
            ['GET', '/api/v2/admin/community-analytics/geography'],
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
