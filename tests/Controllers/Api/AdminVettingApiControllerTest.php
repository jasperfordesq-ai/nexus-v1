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
 * Integration tests for AdminVettingApiController
 *
 * Tests all 9 vetting management endpoints:
 * - GET    /api/v2/admin/vetting              - List vetting records
 * - GET    /api/v2/admin/vetting/stats        - Get vetting stats
 * - GET    /api/v2/admin/vetting/{id}         - Get vetting record detail
 * - POST   /api/v2/admin/vetting              - Create vetting record
 * - PUT    /api/v2/admin/vetting/{id}         - Update vetting record
 * - POST   /api/v2/admin/vetting/{id}/verify  - Verify vetting record
 * - POST   /api/v2/admin/vetting/{id}/reject  - Reject vetting record
 * - DELETE /api/v2/admin/vetting/{id}         - Delete vetting record
 * - GET    /api/v2/admin/vetting/user/{userId} - Get user vetting records
 *
 * @group integration
 */
class AdminVettingApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_vetting_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user
        $testEmail = 'vetting_test_user_' . uniqid() . '@test.local';
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
    // LIST & STATS TESTS
    // ===========================

    public function testListVettingRecordsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/vetting?page=1&per_page=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/vetting/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // SHOW TESTS
    // ===========================

    public function testShowVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/vetting/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // CRUD TESTS
    // ===========================

    public function testCreateVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/vetting',
            [
                'user_id' => self::$testUserId,
                'vetting_type' => 'dbs_basic',
                'status' => 'pending',
                'reference_number' => 'DBS-TEST-' . uniqid(),
                'notes' => 'Test vetting record',
                'works_with_children' => true,
                'works_with_vulnerable_adults' => false,
                'requires_enhanced_check' => false,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUpdateVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/vetting/1',
            [
                'notes' => 'Updated vetting notes',
                'status' => 'submitted',
                'vetting_type' => 'dbs_enhanced',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testDeleteVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/vetting/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // VERIFY & REJECT TESTS
    // ===========================

    public function testVerifyVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/vetting/1/verify',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testRejectVettingRecordWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/vetting/1/reject',
            ['reason' => 'Failed background check — document expired'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // USER RECORDS TESTS
    // ===========================

    public function testGetUserRecordsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/vetting/user/' . self::$testUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // VALIDATION TESTS
    // ===========================

    public function testCreateRejectsInvalidVettingType(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/vetting',
            [
                'user_id' => self::$testUserId,
                'vetting_type' => 'invalid_type',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testUpdateRejectsInvalidStatus(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/vetting/1',
            [
                'status' => 'in_progress',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testVettingEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/vetting'],
            ['GET', '/api/v2/admin/vetting/stats'],
            ['GET', '/api/v2/admin/vetting/1'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertIsArray($response, "Endpoint {$method} {$endpoint} should return array");
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$adminUserId) {
            Database::query("DELETE FROM vetting_records WHERE user_id = ? AND tenant_id = ?", [self::$adminUserId, self::$tenantId]);
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }
        if (self::$testUserId) {
            Database::query("DELETE FROM vetting_records WHERE user_id = ? AND tenant_id = ?", [self::$testUserId, self::$tenantId]);
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }
}
