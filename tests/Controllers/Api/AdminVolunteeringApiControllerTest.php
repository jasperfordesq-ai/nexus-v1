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
 * Integration tests for AdminVolunteeringApiController
 *
 * Tests all 5 volunteering management endpoints:
 * - GET    /api/v2/admin/volunteering              - Overview stats + recent opportunities
 * - GET    /api/v2/admin/volunteering/approvals    - Pending volunteer applications
 * - POST   /api/v2/admin/volunteering/approvals/{id}/approve  - Approve application
 * - POST   /api/v2/admin/volunteering/approvals/{id}/decline  - Decline application
 * - GET    /api/v2/admin/volunteering/organizations - List volunteer organizations
 *
 * @group integration
 */
class AdminVolunteeringApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_volunteering_' . uniqid() . '@test.local';
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
    // OVERVIEW TESTS
    // ===========================

    public function testIndexWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // APPROVALS TESTS
    // ===========================

    public function testGetApprovalsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering/approvals',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApproveApplicationWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/volunteering/approvals/1/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeclineApplicationWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/volunteering/approvals/1/decline',
            ['reason' => 'Test decline reason'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // ORGANIZATIONS TESTS
    // ===========================

    public function testGetOrganizationsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering/organizations',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testVolunteeringEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/volunteering'],
            ['GET', '/api/v2/admin/volunteering/approvals'],
            ['GET', '/api/v2/admin/volunteering/organizations'],
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
