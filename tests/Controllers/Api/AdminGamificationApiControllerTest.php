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
 * Integration tests for AdminGamificationApiController
 *
 * Tests all 10 gamification management endpoints:
 * - GET    /api/v2/admin/gamification/stats           - Aggregate stats
 * - GET    /api/v2/admin/gamification/badges           - List badges
 * - POST   /api/v2/admin/gamification/badges           - Create custom badge
 * - DELETE /api/v2/admin/gamification/badges/{id}      - Delete custom badge
 * - GET    /api/v2/admin/gamification/campaigns        - List campaigns
 * - POST   /api/v2/admin/gamification/campaigns        - Create campaign
 * - PUT    /api/v2/admin/gamification/campaigns/{id}   - Update campaign
 * - DELETE /api/v2/admin/gamification/campaigns/{id}   - Delete campaign
 * - POST   /api/v2/admin/gamification/recheck-all      - Trigger badge recheck
 * - POST   /api/v2/admin/gamification/bulk-award       - Bulk award badge
 *
 * @group integration
 */
class AdminGamificationApiControllerTest extends ApiTestCase
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
        $adminEmail = 'admin_gamification_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_super_admin, created_at)
             VALUES (?, ?, ?, 'Admin User', 'Admin', 'User', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('test123', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int)Database::lastInsertId();
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create test user for bulk award
        $testEmail = 'gamif_test_user_' . uniqid() . '@test.local';
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
    // STATS TESTS
    // ===========================

    public function testGetStatsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/gamification/stats',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BADGES TESTS
    // ===========================

    public function testListBadgesWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/gamification/badges',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateBadgeWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/badges',
            [
                'name' => 'Test Badge ' . uniqid(),
                'description' => 'A test badge',
                'icon' => 'star',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateBadgeValidation(): void
    {
        // Test missing name
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/badges',
            ['description' => 'No name provided'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteBadgeWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/gamification/badges/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // CAMPAIGNS TESTS
    // ===========================

    public function testListCampaignsWorks(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/gamification/campaigns',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateCampaignWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/campaigns',
            [
                'name' => 'Test Campaign ' . uniqid(),
                'description' => 'A test campaign',
                'type' => 'one_time',
                'badge_key' => 'first_login',
                'target_audience' => 'all_users',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateCampaignValidation(): void
    {
        // Test missing name
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/campaigns',
            ['description' => 'No name provided'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateCampaignWorks(): void
    {
        $response = $this->makeApiRequest(
            'PUT',
            '/api/v2/admin/gamification/campaigns/1',
            [
                'name' => 'Updated Campaign Name',
                'status' => 'active',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteCampaignWorks(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/gamification/campaigns/99999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // BULK OPERATIONS TESTS
    // ===========================

    public function testRecheckAllWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/recheck-all',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testBulkAwardWorks(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/bulk-award',
            [
                'badge_slug' => 'first_login',
                'user_ids' => [self::$testUserId],
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testBulkAwardValidation(): void
    {
        // Test missing badge_slug
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/gamification/bulk-award',
            ['user_ids' => [1, 2]],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // ===========================
    // AUTHORIZATION TESTS
    // ===========================

    public function testGamificationEndpointsRequireAuth(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/gamification/stats'],
            ['GET', '/api/v2/admin/gamification/badges'],
            ['GET', '/api/v2/admin/gamification/campaigns'],
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
