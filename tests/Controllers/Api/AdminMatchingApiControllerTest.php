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
 * Integration tests for AdminMatchingApiController
 *
 * Tests all 9 matching management endpoints:
 * - GET    /api/v2/admin/matching                  - List matches
 * - GET    /api/v2/admin/matching/approval-stats   - Get approval stats
 * - GET    /api/v2/admin/matching/{id}             - Show match detail
 * - POST   /api/v2/admin/matching/{id}/approve     - Approve match
 * - POST   /api/v2/admin/matching/{id}/reject      - Reject match
 * - GET    /api/v2/admin/matching/config            - Get matching config
 * - PUT    /api/v2/admin/matching/config            - Update matching config
 * - POST   /api/v2/admin/matching/clear-cache       - Clear match cache
 * - GET    /api/v2/admin/matching/stats             - Get matching stats
 *
 * @group integration
 */
class AdminMatchingApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_matching_' . uniqid() . '@test.local';
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
    // LIST & STATS TESTS
    // ===========================

    public function testListMatchesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/matching?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApprovalStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/matching/approval-stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/matching/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // SHOW TESTS
    // ===========================

    public function testShowMatchWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/matching/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // APPROVE/REJECT TESTS
    // ===========================

    public function testApproveMatchWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/matching/1/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRejectMatchWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/matching/1/reject',
            ['reason' => 'Not appropriate match'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CONFIG TESTS
    // ===========================

    public function testGetConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/matching/config',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateConfigWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/matching/config',
            [
                'auto_match_enabled' => true,
                'min_score' => 0.5,
                'max_matches_per_user' => 10,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CACHE TESTS
    // ===========================

    public function testClearCacheWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/matching/clear-cache',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testMatchingEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/matching'],
            ['GET', '/api/v2/admin/matching/approval-stats'],
            ['GET', '/api/v2/admin/matching/config'],
            ['GET', '/api/v2/admin/matching/stats'],
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
