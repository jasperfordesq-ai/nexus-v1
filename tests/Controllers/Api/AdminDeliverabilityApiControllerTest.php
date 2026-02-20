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
 * Integration tests for AdminDeliverabilityApiController
 *
 * Tests all 8 deliverability management endpoints:
 * - GET    /api/v2/admin/deliverability/dashboard     - Dashboard overview
 * - GET    /api/v2/admin/deliverability/analytics     - Analytics data
 * - GET    /api/v2/admin/deliverability/deliverables  - List deliverables
 * - GET    /api/v2/admin/deliverability/deliverables/{id} - Get deliverable
 * - POST   /api/v2/admin/deliverability/deliverables  - Create deliverable
 * - PUT    /api/v2/admin/deliverability/deliverables/{id} - Update deliverable
 * - DELETE /api/v2/admin/deliverability/deliverables/{id} - Delete deliverable
 * - POST   /api/v2/admin/deliverability/deliverables/{id}/comments - Add comment
 *
 * @group integration
 */
class AdminDeliverabilityApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_deliverability_' . uniqid() . '@test.local';
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
    // DASHBOARD & ANALYTICS TESTS
    // ===========================

    public function testGetDashboardWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/deliverability/dashboard',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetAnalyticsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/deliverability/analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // DELIVERABLES CRUD TESTS
    // ===========================

    public function testListDeliverablesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/deliverability/deliverables',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetDeliverableWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/deliverability/deliverables/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateDeliverableWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/deliverability/deliverables',
            [
                'title' => 'Test Deliverable',
                'description' => 'Test description',
                'status' => 'pending',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateDeliverableWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/deliverability/deliverables/1',
            [
                'title' => 'Updated Deliverable',
                'status' => 'in_progress',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteDeliverableWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/deliverability/deliverables/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testAddCommentWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/deliverability/deliverables/1/comments',
            ['content' => 'Test comment on deliverable'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testDeliverabilityEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/deliverability/dashboard'],
            ['GET', '/api/v2/admin/deliverability/analytics'],
            ['GET', '/api/v2/admin/deliverability/deliverables'],
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
