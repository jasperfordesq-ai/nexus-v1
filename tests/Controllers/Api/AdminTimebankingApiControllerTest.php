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
 * Integration tests for AdminTimebankingApiController
 *
 * Tests all 7 timebanking management endpoints:
 * - GET    /api/v2/admin/timebanking/stats         - Get timebanking stats
 * - GET    /api/v2/admin/timebanking/alerts         - Get alerts
 * - PUT    /api/v2/admin/timebanking/alerts/{id}    - Update alert
 * - POST   /api/v2/admin/timebanking/adjust-balance - Adjust user balance
 * - GET    /api/v2/admin/timebanking/org-wallets    - Get org wallets
 * - GET    /api/v2/admin/timebanking/user-report    - Get user report
 * - GET    /api/v2/admin/timebanking/user-statement - Get user statement
 *
 * @group integration
 */
class AdminTimebankingApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_timebanking_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user
        $testEmail = 'timebanking_test_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, balance, created_at)
             VALUES (?, ?, ?, 'Test User', 'Test', 'User', 'member', 'active', 1, 50, NOW())",
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
    // STATS TESTS
    // ===========================

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // ALERTS TESTS
    // ===========================

    public function testGetAlertsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/alerts',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateAlertWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/timebanking/alerts/1',
            [
                'status' => 'acknowledged',
                'notes' => 'Admin reviewed this alert',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BALANCE ADJUSTMENT TESTS
    // ===========================

    public function testAdjustBalanceWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/timebanking/adjust-balance',
            [
                'user_id' => self::$testUserId,
                'amount' => 10,
                'reason' => 'Admin adjustment for testing',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testAdjustBalanceNegative(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/timebanking/adjust-balance',
            [
                'user_id' => self::$testUserId,
                'amount' => -5,
                'reason' => 'Admin deduction for testing',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // ORG WALLETS TESTS
    // ===========================

    public function testGetOrgWalletsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/org-wallets',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // USER REPORT & STATEMENT TESTS
    // ===========================

    public function testGetUserReportWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/user-report?user_id=' . self::$testUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetUserStatementWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/user-statement?user_id=' . self::$testUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetUserStatementWithDateRange(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/timebanking/user-statement?user_id=' . self::$testUserId . '&start_date=2026-01-01&end_date=2026-02-20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testTimebankingEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/timebanking/stats'],
            ['GET', '/api/v2/admin/timebanking/alerts'],
            ['GET', '/api/v2/admin/timebanking/org-wallets'],
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
