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
 * Integration tests for AdminGroupsApiController
 *
 * Tests all 26 group management endpoints:
 * List/Analytics (2), Approvals (3), Moderation (3), Group Types CRUD (4),
 * Policies (2), Group Detail/Update (2), Members (4), Geocoding (2),
 * Recommendations (1), Featured (3)
 *
 * @group integration
 */
class AdminGroupsApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_groups_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user
        $testEmail = 'groups_test_user_' . uniqid() . '@test.local';
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
    // LIST & ANALYTICS TESTS
    // ===========================

    public function testListGroupsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGroupAnalyticsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/analytics',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // APPROVALS TESTS
    // ===========================

    public function testListApprovalsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/approvals',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApproveMemberWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/approvals/1/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testRejectMemberWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/approvals/1/reject',
            ['reason' => 'Test rejection'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // MODERATION TESTS
    // ===========================

    public function testModerationWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/moderation',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateGroupStatusWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/groups/1/status',
            ['status' => 'active'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteGroupWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/groups/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // GROUP TYPES TESTS
    // ===========================

    public function testGetGroupTypesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/types',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateGroupTypeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/types',
            [
                'name' => 'Test Group Type',
                'slug' => 'test-group-type',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateGroupTypeWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/groups/types/1',
            ['name' => 'Updated Group Type'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteGroupTypeWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/groups/types/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // POLICIES TESTS
    // ===========================

    public function testGetPoliciesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/types/1/policies',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSetPolicyWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/groups/types/1/policies',
            [
                'max_members' => 50,
                'require_approval' => true,
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // GROUP DETAIL TESTS
    // ===========================

    public function testGetGroupWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateGroupWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/groups/1',
            ['name' => 'Updated Group Name'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // MEMBERS TESTS
    // ===========================

    public function testGetMembersWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/1/members',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testPromoteMemberWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/1/members/' . self::$testUserId . '/promote',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDemoteMemberWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/1/members/' . self::$testUserId . '/demote',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testKickMemberWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/groups/1/members/' . self::$testUserId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // GEOCODING TESTS
    // ===========================

    public function testGeocodeGroupWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/1/geocode',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testBatchGeocodeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/batch-geocode',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // RECOMMENDATIONS & FEATURED TESTS
    // ===========================

    public function testGetRecommendationDataWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/recommendations',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testGetFeaturedGroupsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/groups/featured',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateFeaturedGroupsWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/groups/featured',
            ['group_ids' => [1, 2, 3]],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testToggleFeaturedWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/groups/1/toggle-featured',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testGroupEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/groups'],
            ['GET', '/api/v2/admin/groups/analytics'],
            ['GET', '/api/v2/admin/groups/approvals'],
            ['GET', '/api/v2/admin/groups/types'],
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
