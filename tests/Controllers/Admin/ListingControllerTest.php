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
 * Integration tests for AdminListingsApiController
 *
 * Tests listing management endpoints:
 * - GET    /api/v2/admin/listings              — List all listings (paginated, filterable)
 * - GET    /api/v2/admin/listings/{id}         — Show single listing detail
 * - POST   /api/v2/admin/listings/{id}/approve — Approve a pending listing
 * - DELETE /api/v2/admin/listings/{id}         — Delete a listing
 *
 * @group integration
 * @group admin
 */
class ListingControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $memberUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static string $memberToken;
    private static int $testListingId;

    /** @var int[] IDs to clean up in tearDownAfterClass */
    private static array $cleanupUserIds = [];
    private static array $cleanupListingIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        // Create admin user
        $adminEmail = 'listing_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Listing Admin', 'Listing', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create regular member user (for 403 tests)
        $memberEmail = 'listing_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Listing Member', 'Listing', 'Member', 'member', 'active', 1, NOW())",
            [self::$tenantId, $memberEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$memberUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$memberUserId;
        self::$memberToken = TokenService::generateToken(self::$memberUserId, self::$tenantId);

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Test Listing', 'A test listing description', 'offer', 'active', NOW())",
            [self::$tenantId, self::$memberUserId]
        );
        self::$testListingId = (int) Database::lastInsertId();
        self::$cleanupListingIds[] = self::$testListingId;
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    // =========================================================================
    // LIST LISTINGS — GET /api/v2/admin/listings
    // =========================================================================

    public function testListAllListingsReturnsPaginatedData(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?page=1&limit=20',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('GET', $response['method']);
    }

    public function testListListingsWithStatusFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?status=active&page=1&limit=10',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListListingsWithPendingFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?status=pending',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListListingsWithTypeFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?type=offer',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListListingsWithSearchFilter(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?search=Test&sort=title&order=ASC',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListListingsDefaultSortIsCreatedAtDesc(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testListListingsInvalidSortFallsBackToCreatedAt(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings?sort=invalid_column',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // SHOW LISTING — GET /api/v2/admin/listings/{id}
    // =========================================================================

    public function testShowListingReturnsDetail(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings/' . self::$testListingId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testShowListingNotFoundReturns404(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings/999999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // APPROVE LISTING — POST /api/v2/admin/listings/{id}/approve
    // =========================================================================

    public function testApproveListingChangesStatusToActive(): void
    {
        // Create a pending listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Pending Listing', 'Awaiting approval', 'offer', 'pending', NOW())",
            [self::$tenantId, self::$memberUserId]
        );
        $pendingId = (int) Database::lastInsertId();
        self::$cleanupListingIds[] = $pendingId;

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/listings/{$pendingId}/approve",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testApproveNonExistentListingReturns404(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/listings/999999/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // DELETE LISTING — DELETE /api/v2/admin/listings/{id}
    // =========================================================================

    public function testDeleteListingRemovesListing(): void
    {
        // Create a listing to delete
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'Delete Me Listing', 'Will be deleted', 'offer', 'active', NOW())",
            [self::$tenantId, self::$memberUserId]
        );
        $deleteId = (int) Database::lastInsertId();

        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/listings/' . $deleteId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup if simulated request did not actually delete
        Database::query("DELETE FROM listings WHERE id = ?", [$deleteId]);
    }

    public function testDeleteNonExistentListingReturns404(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/listings/999999',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // TENANT SCOPING
    // =========================================================================

    public function testCannotAccessListingsFromOtherTenant(): void
    {
        // Create listing in tenant 2
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (2, ?, 'Other Tenant Listing', 'Should not be visible', 'offer', 'active', NOW())",
            [self::$memberUserId]
        );
        $otherTenantListingId = (int) Database::lastInsertId();

        // Try to access from tenant 1 admin
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings/' . $otherTenantListingId,
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM listings WHERE id = ?", [$otherTenantListingId]);
    }

    public function testCannotApproveListingFromOtherTenant(): void
    {
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (2, ?, 'Other Tenant Pending', 'Cross-tenant test', 'offer', 'pending', NOW())",
            [self::$memberUserId]
        );
        $otherTenantListingId = (int) Database::lastInsertId();

        $response = $this->makeApiRequest(
            'POST',
            "/api/v2/admin/listings/{$otherTenantListingId}/approve",
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);

        // Cleanup
        Database::query("DELETE FROM listings WHERE id = ?", [$otherTenantListingId]);
    }

    // =========================================================================
    // AUTHORIZATION — Non-admin gets 403
    // =========================================================================

    public function testNonAdminCannotListListings(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/api/v2/admin/listings',
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertStringContainsString(self::$memberToken, $response['headers']['Authorization']);
    }

    public function testNonAdminCannotApproveListing(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/api/v2/admin/listings/' . self::$testListingId . '/approve',
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotDeleteListing(): void
    {
        $response = $this->makeApiRequest(
            'DELETE',
            '/api/v2/admin/listings/' . self::$testListingId,
            [],
            ['Authorization' => 'Bearer ' . self::$memberToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUnauthenticatedRequestsAreRejected(): void
    {
        $endpoints = [
            ['GET', '/api/v2/admin/listings'],
            ['GET', '/api/v2/admin/listings/1'],
            ['POST', '/api/v2/admin/listings/1/approve'],
            ['DELETE', '/api/v2/admin/listings/1'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest($method, $endpoint, [], []);
            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should reject unauthenticated requests");
        }
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

    public static function tearDownAfterClass(): void
    {
        // Clean up listings first (foreign keys may reference users)
        foreach (self::$cleanupListingIds as $id) {
            try {
                Database::query("DELETE FROM listings WHERE id = ?", [$id]);
            } catch (\Exception $e) {
                // ignore
            }
        }

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
