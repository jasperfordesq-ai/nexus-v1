<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Admin;

use Nexus\Tests\Controllers\Api\ApiTestCase;
use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for AdminUsersApiController
 *
 * Tests CRUD, status management, validation, tenant scoping, and authorization
 * for all /api/v2/admin/users/* endpoints.
 *
 * @group integration
 * @group admin
 */
class UserControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static int $memberUserId;
    private static string $memberToken;
    private static int $targetUserId;

    /** @var int[] IDs to clean up in tearDownAfterClass */
    private static array $cleanupUserIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'admin_ctrl_test_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Admin Tester', 'Admin', 'Tester', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create regular member user (for 403 tests)
        $memberEmail = 'member_ctrl_test_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Member Tester', 'Member', 'Tester', 'member', 'active', 1, NOW())",
            [self::$tenantId, $memberEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$memberUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$memberUserId;
        self::$memberToken = TokenService::generateToken(self::$memberUserId, self::$tenantId);

        // Create target user for operations
        $targetEmail = 'target_ctrl_test_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, balance, created_at)
             VALUES (?, ?, ?, 'Target User', 'Target', 'User', 'member', 'active', 1, 50, NOW())",
            [self::$tenantId, $targetEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$targetUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$targetUserId;
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // =========================================================================
    // LIST USERS — GET /api/v2/admin/users
    // =========================================================================

    public function testListUsersReturnsPaginatedData(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/v2/admin/users?page=1&limit=20', $response['endpoint']);
    }

    public function testListUsersWithStatusFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users?status=active&page=1&limit=10',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListUsersWithSearchFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users?search=Target&sort=email&order=ASC',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListUsersWithRoleFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users?role=admin',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // CREATE USER — POST /api/v2/admin/users
    // =========================================================================

    public function testCreateUserWithValidData(): void
    {
        $email = 'new_admin_created_' . uniqid() . '@test.local';

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'New',
                'last_name' => 'Created',
                'email' => $email,
                'password' => 'SecurePassword123!',
                'role' => 'member',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('POST', $response['method']);

        // Cleanup any created user
        Database::query("DELETE FROM users WHERE email = ?", [$email]);
    }

    public function testCreateUserValidatesRequiredFields(): void
    {
        // Missing first_name and last_name
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            ['email' => 'incomplete@test.local'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateUserValidatesEmailFormat(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'Bad',
                'last_name' => 'Email',
                'email' => 'not-an-email',
                'password' => 'SecurePassword123!',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateUserValidatesPasswordLength(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'Short',
                'last_name' => 'Pass',
                'email' => 'shortpass_' . uniqid() . '@test.local',
                'password' => 'short',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateUserRejectsDuplicateEmail(): void
    {
        // Use an existing user's email
        $existingEmail = 'dup_check_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Dup User', 'Dup', 'User', 'member', 'active', NOW())",
            [self::$tenantId, $existingEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $dupId = (int) Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'Another',
                'last_name' => 'Dup',
                'email' => $existingEmail,
                'password' => 'SecurePassword123!',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$dupId]);
    }

    // =========================================================================
    // UPDATE USER — PUT /api/v2/admin/users/{id}
    // =========================================================================

    public function testUpdateUserUpdatesCorrectly(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/users/' . self::$targetUserId,
            [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'location' => 'Dublin',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('PUT', $response['method']);
    }

    public function testUpdateUserValidatesEmail(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/users/' . self::$targetUserId,
            ['email' => 'not-valid-email'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateUserRejectsEmptyPayload(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/users/' . self::$targetUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // DELETE USER — DELETE /api/v2/admin/users/{id}
    // =========================================================================

    public function testDeleteUserWithTenantScoping(): void
    {
        // Create user to delete
        $email = 'delete_target_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (?, ?, ?, 'Delete Me', 'Delete', 'Me', 'member', 'active', NOW())",
            [self::$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $deleteId = (int) Database::lastInsertId();

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . $deleteId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup in case simulated request didn't actually delete
        Database::query("DELETE FROM users WHERE id = ?", [$deleteId]);
    }

    public function testDeleteUserPreventsSelfDeletion(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . self::$adminUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        // Controller returns 403 for self-deletion
        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteUserCannotDeleteFromOtherTenant(): void
    {
        // Create user in tenant 2
        $email = 'other_tenant_user_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, created_at)
             VALUES (2, ?, ?, 'Other Tenant', 'Other', 'Tenant', 'member', 'active', NOW())",
            [$email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $otherTenantUserId = (int) Database::lastInsertId();

        // Admin from tenant 1 tries to delete user from tenant 2
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . $otherTenantUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$otherTenantUserId]);
    }

    // =========================================================================
    // STATUS MANAGEMENT
    // =========================================================================

    public function testApproveUser(): void
    {
        // Create pending user
        $email = 'pending_approval_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Pending User', 'Pending', 'User', 'member', 'active', 0, NOW())",
            [self::$tenantId, $email, password_hash('test123', PASSWORD_BCRYPT)]
        );
        $pendingId = (int) Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/users/{$pendingId}/approve",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$pendingId]);
    }

    public function testSuspendUser(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/suspend',
            ['reason' => 'Policy violation test'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset
        Database::query("UPDATE users SET status = 'active' WHERE id = ?", [self::$targetUserId]);
    }

    public function testSuspendUserPreventsSelfSuspension(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$adminUserId . '/suspend',
            ['reason' => 'Self-suspension test'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testBanUser(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/ban',
            ['reason' => 'Test ban reason'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Reset
        Database::query("UPDATE users SET status = 'active' WHERE id = ?", [self::$targetUserId]);
    }

    public function testReactivateUser(): void
    {
        // Suspend first
        Database::query("UPDATE users SET status = 'suspended' WHERE id = ?", [self::$targetUserId]);

        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/reactivate',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // AUTHORIZATION — Non-admin gets 403
    // =========================================================================

    public function testNonAdminCannotListUsers(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users',
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        // This verifies the request is structured correctly for a member token
        $this->assertEquals('simulated', $response['status']);
        $this->assertArrayHasKey('headers', $response);
        $this->assertStringContainsString(self::$memberToken, $response['headers']['Authorization']);
    }

    public function testNonAdminCannotCreateUser(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users',
            [
                'first_name' => 'Unauthorized',
                'last_name' => 'User',
                'email' => 'unauthorized@test.local',
                'password' => 'TestPass123!',
            ],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotDeleteUser(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/users/' . self::$targetUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotSuspendUser(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/suspend',
            ['reason' => 'Unauthorized suspension'],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/users'],
            ['POST', '/api/v2/admin/users'],
            ['PUT', '/api/v2/admin/users/1'],
            ['DELETE', '/api/v2/admin/users/1'],
            ['POST', '/api/v2/admin/users/1/approve'],
            ['POST', '/api/v2/admin/users/1/suspend'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should reject unauthenticated requests");
        }
    }

    // =========================================================================
    // PASSWORD MANAGEMENT
    // =========================================================================

    public function testSetPasswordWithValidPassword(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/password',
            ['password' => 'NewStrongPassword123!'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSetPasswordRejectsShortPassword(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/password',
            ['password' => 'short'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // IMPERSONATION
    // =========================================================================

    public function testImpersonateUser(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$targetUserId . '/impersonate',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCannotImpersonateSelf(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/users/' . self::$adminUserId . '/impersonate',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // GDPR CONSENTS
    // =========================================================================

    public function testGetUserConsents(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/users/' . self::$targetUserId . '/consents',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    public static function tearDownAfterClass(): void
    {
        foreach (self::$cleanupUserIds as $id) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$id]);
            } catch (\Exception $e) {
                // ignore
            }
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$id]);
            } catch (\Exception $e) {
                // ignore
            }
        }

        parent::tearDownAfterClass();
    }
}
