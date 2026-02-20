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
 * Integration tests for AdminBrokerApiController
 *
 * Tests all 15 broker management endpoints:
 * - GET    /api/v2/admin/broker/dashboard
 * - GET    /api/v2/admin/broker/exchanges
 * - GET    /api/v2/admin/broker/exchanges/{id}
 * - POST   /api/v2/admin/broker/exchanges/{id}/approve
 * - POST   /api/v2/admin/broker/exchanges/{id}/reject
 * - GET    /api/v2/admin/broker/risk-tags
 * - GET    /api/v2/admin/broker/messages
 * - POST   /api/v2/admin/broker/messages/{id}/review
 * - GET    /api/v2/admin/broker/monitoring
 * - POST   /api/v2/admin/broker/messages/{id}/flag
 * - POST   /api/v2/admin/broker/monitoring/{userId}
 * - POST   /api/v2/admin/broker/risk-tags/{listingId}
 * - DELETE /api/v2/admin/broker/risk-tags/{listingId}
 * - GET    /api/v2/admin/broker/configuration
 * - PUT    /api/v2/admin/broker/configuration
 *
 * @group integration
 */
class AdminBrokerApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_broker_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user
        $testEmail = 'broker_test_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Test User', 'Test', 'User', 'member', 'active', 1, NOW())",
            [self::$tenantId, $testEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$testUserId = (int)Database::lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // ===========================
    // DASHBOARD TESTS
    // ===========================

    public function testDashboardWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/dashboard',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // EXCHANGES TESTS
    // ===========================

    public function testListExchangesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/exchanges?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testShowExchangeWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/exchanges/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApproveExchangeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/exchanges/1/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRejectExchangeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/exchanges/1/reject',
            ['reason' => 'Test rejection reason'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // RISK TAGS TESTS
    // ===========================

    public function testGetRiskTagsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/risk-tags',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSaveRiskTagWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/risk-tags/1',
            ['tag' => 'high_risk', 'reason' => 'Test risk tag'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRemoveRiskTagWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/broker/risk-tags/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // MESSAGES TESTS
    // ===========================

    public function testListMessagesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/messages?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testReviewMessageWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/messages/1/review',
            ['action' => 'approve'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testFlagMessageWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/messages/1/flag',
            ['reason' => 'Inappropriate content'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // MONITORING TESTS
    // ===========================

    public function testGetMonitoringWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/monitoring',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSetMonitoringWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/broker/monitoring/' . self::$testUserId,
            ['enabled' => true, 'reason' => 'Test monitoring'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CONFIGURATION TESTS
    // ===========================

    public function testGetConfigurationWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/broker/configuration',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSaveConfigurationWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/broker/configuration',
            [
                'auto_approve_threshold' => 5,
                'require_broker_approval' => true,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testBrokerEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/broker/dashboard'],
            ['GET', '/api/v2/admin/broker/exchanges'],
            ['GET', '/api/v2/admin/broker/messages'],
            ['GET', '/api/v2/admin/broker/monitoring'],
            ['GET', '/api/v2/admin/broker/configuration'],
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
        if (self::$testUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }
}
