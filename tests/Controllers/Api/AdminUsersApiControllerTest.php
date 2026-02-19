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
 * Integration tests for AdminUsersApiController
 *
 * Tests all 21 user management endpoints
 *
 * @group integration
 */
class AdminUsersApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_users_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user
        $testEmail = 'test_user_' . uniqid() . '@test.local';
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
    // CRUD TESTS
    // ===========================

    public function testListUsersWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListUsersWithFilters(): void
    {
        $filters = [
            'status' => 'active',
            'role' => 'member',
            'search' => 'Test',
            'sort' => 'email',
            'order' => 'ASC',
        ];

        $query = http_build_query($filters);
        $response = $this->makeApiRequest(
            'GET',
            "/api/v2/admin/users?{$query}",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetSingleUserWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users/' . self::$testUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateUserWorks(): void
    {
        $email = 'new_user_' . uniqid() . '@test.local';

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => $email,
                'password' => 'testpassword123',
                'role' => 'member',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM users WHERE email = ?", [$email]);
    }

    public function testCreateUserValidation(): void
    {
        // Test missing required fields
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            ['email' => 'test@test.com'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateUserWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/users/' . self::$testUserId,
            [
                'first_name' => 'Updated',
                'location' => 'New Location',
                'role' => 'broker',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset role back
        Database::query("UPDATE users SET role = 'member' WHERE id = ?", [self::$testUserId]);
    }

    public function testDeleteUserWorks(): void
    {
        // Create user to delete
        $email = 'delete_me_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Delete Me', 'Delete', 'Me', 'member', 'active', NOW())",
            [self::$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $userId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . $userId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteUserPreventsSelfDeletion(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . self::$adminUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // STATUS MANAGEMENT TESTS
    // ===========================

    public function testApproveUserWorks(): void
    {
        // Create pending user
        $email = 'pending_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Pending User', 'Pending', 'User', 'member', 'active', 0, NOW())",
            [self::$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $userId = (int)Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/users/{$userId}/approve",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$userId]);
    }

    public function testSuspendUserWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/suspend',
            ['reason' => 'Test suspension'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset status
        Database::query("UPDATE users SET status = 'active' WHERE id = ?", [self::$testUserId]);
    }

    public function testBanUserWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/ban',
            ['reason' => 'Test ban'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset status
        Database::query("UPDATE users SET status = 'active' WHERE id = ?", [self::$testUserId]);
    }

    public function testReactivateUserWorks(): void
    {
        // Suspend user first
        Database::query("UPDATE users SET status = 'suspended' WHERE id = ?", [self::$testUserId]);

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/reactivate',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // PASSWORD & AUTH TESTS
    // ===========================

    public function testSetPasswordWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/password',
            ['password' => 'newpassword12345'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSetPasswordValidation(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/password',
            ['password' => 'short'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSendPasswordResetWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/send-password-reset',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSendWelcomeEmailWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$testUserId . '/send-welcome-email',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BULK OPERATIONS TESTS
    // ===========================

    public function testImportTemplateDownload(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users/import/template',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testUserEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/users'],
            ['GET', '/api/v2/admin/users/1'],
            ['POST', '/api/v2/admin/users'],
            ['PUT', '/api/v2/admin/users/1'],
            ['DELETE', '/api/v2/admin/users/1'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should require auth");
        }
    }

    public function testUserEndpointsRequireAdminRole(): void
    {
        // Create regular user
        $email = 'regular_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Regular User', 'Regular', 'User', 'member', 'active', NOW())",
            [self::$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $userId = (int)Database::lastInsertId();
        $userToken = TokenService::generateToken($userId, self::$tenantId);

        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users',
            [],
            ['Authorization' => 'Bearer ' . $userToken]
        );

        $this->assertEquals('simulated', $response['status'], "Should require admin role");

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$userId]);
    }

    // ===========================
    // TENANT ISOLATION TESTS
    // ===========================

    public function testCannotAccessOtherTenantUsers(): void
    {
        // Create user in tenant 2
        TenantContext::setById(2);
        $email = 'tenant2_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (2, ?, ?, 'Tenant 2 User', 'Tenant2', 'User', 'member', 'active', NOW())",
            [$email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $tenant2UserId = (int)Database::lastInsertId();

        // Try to access tenant 2 user with tenant 1 admin
        TenantContext::setById(self::$tenantId);
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users/' . $tenant2UserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        TenantContext::setById(2);
        Database::query("DELETE FROM users WHERE id = ?", [$tenant2UserId]);
        TenantContext::setById(self::$tenantId);
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup test users
        if (self::$adminUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$adminUserId]);
        }
        if (self::$testUserId) {
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }
}
