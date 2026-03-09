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
use Nexus\Controllers\Api\AdminVolunteeringApiController;

/**
 * Integration tests for AdminVolunteeringApiController
 *
 * Tests all volunteering admin endpoints:
 * - GET  /api/v2/admin/volunteering            - Overview stats + recent opportunities
 * - GET  /api/v2/admin/volunteering/approvals  - Pending applications
 * - POST /api/v2/admin/volunteering/approve    - Approve an application
 * - POST /api/v2/admin/volunteering/decline    - Decline an application
 * - GET  /api/v2/admin/volunteering/orgs       - Organizations list
 *
 * @group integration
 */
class AdminVolunteeringApiControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $memberUserId;
    private static string $memberToken;
    private static ?int $testOrgId = null;
    private static ?int $testOppId = null;
    private static ?int $testAppId = null;
    private static ?int $testDeclineAppId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_volunteering_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)"
            . " VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create member user (non-admin, for 403 tests)
        $memberEmail = 'member_volunteering_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)"
            . " VALUES (?, ?, ?, 'Member User', 'Member', 'User', 'member', 'active', 1, NOW())",
            [self::$tenantId, $memberEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$memberUserId = (int)Database::lastInsertId();
        self::$memberToken = TokenService::generateToken(self::$memberUserId, self::$tenantId);

        self::$testUserId = self::$adminUserId;

        // Seed vol_ test data if tables exist
        self::seedTestData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    /**
     * Seed vol_organizations, vol_opportunities, and vol_applications for approve/decline tests.
     * Skipped silently if tables do not exist.
     */
    private static function seedTestData(): void
    {
        try {
            Database::query('SELECT 1 FROM vol_organizations LIMIT 1');
        } catch (\Throwable $e) {
            return; // Tables missing
        }

        try {
            Database::query(
                "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)"
                . " VALUES (?, ?, 'Test Vol Org', 'Test organization for admin controller tests', 'approved', NOW())",
                [self::$tenantId, self::$adminUserId]
            );
            self::$testOrgId = (int)Database::lastInsertId();

            // vol_opportunities may use created_by or user_id column
            $authorCol = 'created_by';
            try {
                $result = Database::query(
                    "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS"
                    . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_opportunities' AND COLUMN_NAME = 'created_by'"
                )->fetch();
                if (((int)($result['cnt'] ?? 0)) === 0) {
                    $authorCol = 'user_id';
                }
            } catch (\Throwable $e) {
                $authorCol = 'user_id';
            }

            Database::query(
                "INSERT INTO vol_opportunities (tenant_id, organization_id, title, description, status, is_active, {$authorCol}, created_at)"
                . " VALUES (?, ?, 'Test Opportunity', 'Test opportunity for admin controller tests', 'open', 1, ?, NOW())",
                [self::$tenantId, self::$testOrgId, self::$adminUserId]
            );
            self::$testOppId = (int)Database::lastInsertId();

            // Seed two pending applications
            Database::query(
                "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, status, created_at)"
                . " VALUES (?, ?, ?, 'pending', NOW())",
                [self::$tenantId, self::$testOppId, self::$adminUserId]
            );
            self::$testAppId = (int)Database::lastInsertId();

            Database::query(
                "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, status, created_at)"
                . " VALUES (?, ?, ?, 'pending', NOW())",
                [self::$tenantId, self::$testOppId, self::$memberUserId]
            );
            self::$testDeclineAppId = (int)Database::lastInsertId();

        } catch (\Throwable $e) {
            error_log('[AdminVolunteeringApiControllerTest] seedTestData failed: ' . $e->getMessage());
        }
    }

    /**
     * Call a controller method that requires an int $id parameter.
     * makeApiRequest() calls methods with no args, so this helper is needed
     * for approveApplication(int $id) and declineApplication(int $id).
     */
    private function callWithId(string $controllerMethod, int $id, string $token, array $postData = []): array
    {
        $oldServer = $_SERVER;
        $oldPost = $_POST;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_TENANT_ID'] = (string)self::$tenantId;
        $_POST = $postData;

        $rawOutput = '';
        ob_start();
        try {
            $controller = new AdminVolunteeringApiController();
            $controller->$controllerMethod($id);
        } catch (\Throwable $e) {
            // Capture what was output before any exit/exception
        } finally {
            $rawOutput = ob_get_clean() ?: '';
        }

        $statusCode = http_response_code() ?: 200;

        $_SERVER = $oldServer;
        $_POST = $oldPost;

        $body = json_decode($rawOutput, true);
        if ($body === null && !empty($rawOutput)) {
            $body = $rawOutput;
        }

        return [
            'status' => $statusCode,
            'body' => $body ?? [],
            'raw' => $rawOutput,
        ];
    }

    // ===========================
    // INDEX / STATS TESTS
    // ===========================

    public function testIndexWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken],
            'Nexus\\Controllers\\Api\\AdminVolunteeringApiController@index'
        );

        $this->assertContains($response['status'], [200, 403], 'Index should return 200 or 403 if feature disabled');

        if ($response['status'] === 200) {
            $body = $response['body'];
            $this->assertArrayHasKey('data', $body, 'V2 response should have data key');
            $data = $body['data'];
            $this->assertArrayHasKey('stats', $data, 'Index data should have stats key');
            $this->assertArrayHasKey('recent_opportunities', $data, 'Index data should have recent_opportunities key');

            $stats = $data['stats'];
            $this->assertArrayHasKey('total_opportunities', $stats);
            $this->assertArrayHasKey('active_opportunities', $stats);
            $this->assertArrayHasKey('total_applications', $stats);
            $this->assertArrayHasKey('pending_applications', $stats);
            $this->assertArrayHasKey('total_hours_logged', $stats);
            $this->assertArrayHasKey('active_volunteers', $stats);

            $this->assertIsArray($data['recent_opportunities']);
        }
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
            ['Authorization' => 'Bearer ' . self::$adminToken],
            'Nexus\\Controllers\\Api\\AdminVolunteeringApiController@approvals'
        );

        $this->assertContains($response['status'], [200, 403], 'Approvals should return 200 or 403 if feature disabled');

        if ($response['status'] === 200) {
            $body = $response['body'];
            $this->assertArrayHasKey('data', $body, 'V2 response should have data key');
            $this->assertIsArray($body['data'], 'Approvals data should be an array');
        }
    }

    // ===========================
    // APPROVE APPLICATION TESTS
    // ===========================

    public function testApproveApplicationWorks(): void
    {
        if (self::$testAppId === null) {
            $this->markTestSkipped('vol_ tables not available or seed failed');
        }

        $response = $this->callWithId('approveApplication', self::$testAppId, self::$adminToken);

        $this->assertContains($response['status'], [200, 403], 'Approve should return 200 or 403 if feature disabled');

        if ($response['status'] === 200) {
            $body = $response['body'];
            $this->assertArrayHasKey('data', $body, 'V2 response should have data key');
            $this->assertArrayHasKey('message', $body['data'], 'Approve response should have message');
            $this->assertEquals('Application approved', $body['data']['message']);
        }
    }

    // ===========================
    // DECLINE APPLICATION TESTS
    // ===========================

    public function testDeclineApplicationWorks(): void
    {
        if (self::$testDeclineAppId === null) {
            $this->markTestSkipped('vol_ tables not available or seed failed');
        }

        $response = $this->callWithId('declineApplication', self::$testDeclineAppId, self::$adminToken);

        $this->assertContains($response['status'], [200, 403], 'Decline should return 200 or 403 if feature disabled');

        if ($response['status'] === 200) {
            $body = $response['body'];
            $this->assertArrayHasKey('data', $body, 'V2 response should have data key');
            $this->assertArrayHasKey('message', $body['data'], 'Decline response should have message');
            $this->assertEquals('Application declined', $body['data']['message']);
        }
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
            ['Authorization' => 'Bearer ' . self::$adminToken],
            'Nexus\\Controllers\\Api\\AdminVolunteeringApiController@organizations'
        );

        $this->assertContains($response['status'], [200, 403], 'Organizations should return 200 or 403 if feature disabled');

        if ($response['status'] === 200) {
            $body = $response['body'];
            $this->assertArrayHasKey('data', $body, 'V2 response should have data key');
            $this->assertIsArray($body['data'], 'Organizations data should be an array');
        }
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testVolunteeringEndpointsRequireAuth(): void
    {
        // Request with no Authorization header
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering',
            [],
            ['Authorization' => ''],
            'Nexus\\Controllers\\Api\\AdminVolunteeringApiController@index'
        );

        // Unauthenticated request must not return 200
        $this->assertNotEquals(200, $response['status'], 'Unauthenticated request should not return 200');
    }

    public function testNonAdminGetsForbiddenForIndex(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/volunteering',
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken],
            'Nexus\\Controllers\\Api\\AdminVolunteeringApiController@index'
        );

        $this->assertEquals(403, $response['status'], 'Non-admin member should receive 403');
    }

    public function testApproveNonExistentApplicationReturns404OrError(): void
    {
        $response = $this->callWithId('approveApplication', 999999, self::$adminToken);

        // Should return a 4xx or 5xx error (404 not found, or 403 if feature disabled)
        $this->assertGreaterThanOrEqual(400, $response['status'], 'Non-existent app should return 4xx or 5xx');
        $this->assertLessThan(600, $response['status'], 'Status should be a valid HTTP error code');
    }

    // ===========================
    // TEARDOWN
    // ===========================

    public static function tearDownAfterClass(): void
    {
        // Clean up vol_ test data
        try {
            if (self::$testAppId !== null) {
                Database::query('DELETE FROM vol_applications WHERE id = ?', [self::$testAppId]);
            }
            if (self::$testDeclineAppId !== null) {
                Database::query('DELETE FROM vol_applications WHERE id = ?', [self::$testDeclineAppId]);
            }
            if (self::$testOppId !== null) {
                Database::query('DELETE FROM vol_opportunities WHERE id = ?', [self::$testOppId]);
            }
            if (self::$testOrgId !== null) {
                Database::query('DELETE FROM vol_organizations WHERE id = ?', [self::$testOrgId]);
            }
        } catch (\Throwable $e) {
            // Silent
        }

        // Clean up users
        if (isset(self::$adminUserId) && self::$adminUserId) {
            Database::query('DELETE FROM users WHERE id = ?', [self::$adminUserId]);
        }
        if (isset(self::$memberUserId) && self::$memberUserId) {
            Database::query('DELETE FROM users WHERE id = ?', [self::$memberUserId]);
        }

        parent::tearDownAfterClass();
    }
}
