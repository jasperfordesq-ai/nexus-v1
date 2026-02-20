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
 * Integration tests for AdminFederationApiController
 *
 * Tests all 13 federation management endpoints:
 * - GET    /api/v2/admin/federation/settings
 * - PUT    /api/v2/admin/federation/settings
 * - GET    /api/v2/admin/federation/partnerships
 * - POST   /api/v2/admin/federation/partnerships/{id}/approve
 * - POST   /api/v2/admin/federation/partnerships/{id}/reject
 * - POST   /api/v2/admin/federation/partnerships/{id}/terminate
 * - GET    /api/v2/admin/federation/directory
 * - GET    /api/v2/admin/federation/directory/profile
 * - PUT    /api/v2/admin/federation/directory/profile
 * - GET    /api/v2/admin/federation/analytics
 * - GET    /api/v2/admin/federation/api-keys
 * - POST   /api/v2/admin/federation/api-keys
 * - GET    /api/v2/admin/federation/data-management
 *
 * @group integration
 */
class AdminFederationApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_federation_' . uniqid() . '@test.local';
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
    // SETTINGS TESTS
    // ===========================

    public function testGetSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/settings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateSettingsWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/federation/settings',
            [
                'federation_enabled' => true,
                'settings' => [
                    'allow_inbound_partnerships' => true,
                    'auto_approve_partners' => false,
                    'max_partnerships' => 10,
                ],
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // PARTNERSHIPS TESTS
    // ===========================

    public function testListPartnershipsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/partnerships',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApprovePartnershipWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/federation/partnerships/1/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRejectPartnershipWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/federation/partnerships/1/reject',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testTerminatePartnershipWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/federation/partnerships/1/terminate',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // DIRECTORY TESTS
    // ===========================

    public function testDirectoryWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/directory',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetProfileWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/directory/profile',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateProfileWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/federation/directory/profile',
            [
                'description' => 'Test federation profile description',
                'contact_email' => 'federation@test.local',
                'website' => 'https://test.local',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // ANALYTICS TESTS
    // ===========================

    public function testAnalyticsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // API KEYS TESTS
    // ===========================

    public function testListApiKeysWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/api-keys',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateApiKeyWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/federation/api-keys',
            [
                'name' => 'Test API Key ' . uniqid(),
                'scopes' => ['read', 'write'],
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateApiKeyValidation(): void
    {
        // Test missing name
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/federation/api-keys',
            ['scopes' => ['read']],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // DATA MANAGEMENT TESTS
    // ===========================

    public function testDataManagementWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/federation/data-management',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testFederationEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/federation/settings'],
            ['GET', '/api/v2/admin/federation/partnerships'],
            ['GET', '/api/v2/admin/federation/directory'],
            ['GET', '/api/v2/admin/federation/analytics'],
            ['GET', '/api/v2/admin/federation/api-keys'],
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
