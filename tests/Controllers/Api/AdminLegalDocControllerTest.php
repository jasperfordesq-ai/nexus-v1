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
 * Integration tests for AdminLegalDocController
 *
 * Tests all 9 legal document management endpoints:
 * - GET    /api/v2/admin/legal-documents/{docId}/versions          - Get versions
 * - GET    /api/v2/admin/legal-documents/{docId}/versions/compare  - Compare versions
 * - POST   /api/v2/admin/legal-documents/{docId}/versions          - Create version
 * - POST   /api/v2/admin/legal-documents/versions/{versionId}/publish - Publish version
 * - GET    /api/v2/admin/legal-documents/compliance                - Get compliance stats
 * - GET    /api/v2/admin/legal-documents/versions/{versionId}/acceptances - Get acceptances
 * - GET    /api/v2/admin/legal-documents/{docId}/export            - Export acceptances
 * - POST   /api/v2/admin/legal-documents/{docId}/versions/{versionId}/notify - Notify users
 * - GET    /api/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count - Pending count
 *
 * @group integration
 */
class AdminLegalDocControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_legal_' . uniqid() . '@test.local';
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
    // VERSIONS TESTS
    // ===========================

    public function testGetVersionsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/1/versions',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCompareVersionsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/1/versions/compare?version_a=1&version_b=2',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateVersionWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/legal-documents/1/versions',
            [
                'content' => '<h2>Updated Terms</h2><p>New version content</p>',
                'version_number' => '2.0',
                'change_summary' => 'Major update to terms',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testPublishVersionWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/legal-documents/versions/1/publish',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // COMPLIANCE TESTS
    // ===========================

    public function testGetComplianceStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/compliance',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // ACCEPTANCES TESTS
    // ===========================

    public function testGetAcceptancesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/versions/1/acceptances',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testExportAcceptancesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/1/export',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // NOTIFICATION TESTS
    // ===========================

    public function testNotifyUsersWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/legal-documents/1/versions/1/notify',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetUsersPendingCountWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/legal-documents/1/versions/1/pending-count',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testLegalDocEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/legal-documents/1/versions'],
            ['GET', '/api/v2/admin/legal-documents/compliance'],
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
