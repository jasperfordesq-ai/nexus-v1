<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationPartnershipService;

/**
 * FederationPartnershipService Tests
 *
 * Tests partnership management between tenants for federation.
 */
class FederationPartnershipServiceTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenant1Id = 1;
        self::$tenant2Id = 2;

        TenantContext::setById(self::$tenant1Id);

        // Create test user
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$tenant1Id, "partnership_test_{$timestamp}@test.com", "partnership_test_{$timestamp}", 'Partnership', 'Test', 'Partnership Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Constants Tests
    // ==========================================

    public function testStatusConstants(): void
    {
        $this->assertEquals('pending', FederationPartnershipService::STATUS_PENDING);
        $this->assertEquals('active', FederationPartnershipService::STATUS_ACTIVE);
        $this->assertEquals('suspended', FederationPartnershipService::STATUS_SUSPENDED);
        $this->assertEquals('terminated', FederationPartnershipService::STATUS_TERMINATED);
    }

    public function testLevelConstants(): void
    {
        $this->assertEquals(1, FederationPartnershipService::LEVEL_DISCOVERY);
        $this->assertEquals(2, FederationPartnershipService::LEVEL_SOCIAL);
        $this->assertEquals(3, FederationPartnershipService::LEVEL_ECONOMIC);
        $this->assertEquals(4, FederationPartnershipService::LEVEL_INTEGRATED);
    }

    // ==========================================
    // getDefaultPermissions Tests
    // ==========================================

    public function testGetDefaultPermissionsDiscoveryLevel(): void
    {
        $perms = FederationPartnershipService::getDefaultPermissions(FederationPartnershipService::LEVEL_DISCOVERY);

        $this->assertIsArray($perms);
        $this->assertTrue($perms['profiles']);
        $this->assertFalse($perms['messaging']);
        $this->assertFalse($perms['transactions']);
        $this->assertFalse($perms['listings']);
        $this->assertFalse($perms['events']);
        $this->assertFalse($perms['groups']);
    }

    public function testGetDefaultPermissionsSocialLevel(): void
    {
        $perms = FederationPartnershipService::getDefaultPermissions(FederationPartnershipService::LEVEL_SOCIAL);

        $this->assertTrue($perms['profiles']);
        $this->assertTrue($perms['messaging']);
        $this->assertFalse($perms['transactions']);
        $this->assertTrue($perms['listings']);
        $this->assertTrue($perms['events']);
        $this->assertFalse($perms['groups']);
    }

    public function testGetDefaultPermissionsEconomicLevel(): void
    {
        $perms = FederationPartnershipService::getDefaultPermissions(FederationPartnershipService::LEVEL_ECONOMIC);

        $this->assertTrue($perms['profiles']);
        $this->assertTrue($perms['messaging']);
        $this->assertTrue($perms['transactions']);
        $this->assertTrue($perms['listings']);
        $this->assertTrue($perms['events']);
        $this->assertFalse($perms['groups']);
    }

    public function testGetDefaultPermissionsIntegratedLevel(): void
    {
        $perms = FederationPartnershipService::getDefaultPermissions(FederationPartnershipService::LEVEL_INTEGRATED);

        $this->assertTrue($perms['profiles']);
        $this->assertTrue($perms['messaging']);
        $this->assertTrue($perms['transactions']);
        $this->assertTrue($perms['listings']);
        $this->assertTrue($perms['events']);
        $this->assertTrue($perms['groups']);
    }

    public function testGetDefaultPermissionsUnknownLevelDefaultsToDiscovery(): void
    {
        $perms = FederationPartnershipService::getDefaultPermissions(99);

        $this->assertTrue($perms['profiles']);
        $this->assertFalse($perms['messaging']);
    }

    // ==========================================
    // getLevelName Tests
    // ==========================================

    public function testGetLevelNameForAllLevels(): void
    {
        $this->assertEquals('Discovery', FederationPartnershipService::getLevelName(1));
        $this->assertEquals('Social', FederationPartnershipService::getLevelName(2));
        $this->assertEquals('Economic', FederationPartnershipService::getLevelName(3));
        $this->assertEquals('Integrated', FederationPartnershipService::getLevelName(4));
    }

    public function testGetLevelNameForUnknownLevel(): void
    {
        $this->assertEquals('Unknown', FederationPartnershipService::getLevelName(99));
    }

    // ==========================================
    // getLevelDescription Tests
    // ==========================================

    public function testGetLevelDescriptionForAllLevels(): void
    {
        $this->assertNotEmpty(FederationPartnershipService::getLevelDescription(1));
        $this->assertNotEmpty(FederationPartnershipService::getLevelDescription(2));
        $this->assertNotEmpty(FederationPartnershipService::getLevelDescription(3));
        $this->assertNotEmpty(FederationPartnershipService::getLevelDescription(4));
    }

    public function testGetLevelDescriptionForUnknownLevel(): void
    {
        $this->assertEquals('', FederationPartnershipService::getLevelDescription(99));
    }

    // ==========================================
    // Query Methods Tests
    // ==========================================

    public function testGetPartnershipByIdReturnsNullForNonExistent(): void
    {
        $result = FederationPartnershipService::getPartnershipById(999999);

        $this->assertNull($result);
    }

    public function testGetPartnershipBetweenTenants(): void
    {
        $result = FederationPartnershipService::getPartnership(self::$tenant1Id, self::$tenant2Id);

        // May or may not exist, but should return array or null
        $this->assertTrue($result === null || is_array($result));
    }

    public function testGetTenantPartnershipsReturnsArray(): void
    {
        $result = FederationPartnershipService::getTenantPartnerships(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    public function testGetTenantPartnershipsWithStatusFilter(): void
    {
        $result = FederationPartnershipService::getTenantPartnerships(self::$tenant1Id, 'active');

        $this->assertIsArray($result);
        foreach ($result as $partnership) {
            $this->assertEquals('active', $partnership['status']);
        }
    }

    public function testGetPendingRequestsReturnsArray(): void
    {
        $result = FederationPartnershipService::getPendingRequests(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    public function testGetCounterProposalsReturnsArray(): void
    {
        $result = FederationPartnershipService::getCounterProposals(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    public function testGetOutgoingRequestsReturnsArray(): void
    {
        $result = FederationPartnershipService::getOutgoingRequests(self::$tenant1Id);

        $this->assertIsArray($result);
    }

    public function testGetAllPartnershipsReturnsArray(): void
    {
        $result = FederationPartnershipService::getAllPartnerships();

        $this->assertIsArray($result);
    }

    public function testGetAllPartnershipsWithStatusFilter(): void
    {
        $result = FederationPartnershipService::getAllPartnerships('active');

        $this->assertIsArray($result);
    }

    public function testGetAllPartnershipsWithLimit(): void
    {
        $result = FederationPartnershipService::getAllPartnerships(null, 5);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }

    // ==========================================
    // getStats Tests
    // ==========================================

    public function testGetStatsReturnsExpectedStructure(): void
    {
        $result = FederationPartnershipService::getStats();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('suspended', $result);
        $this->assertArrayHasKey('terminated', $result);
        $this->assertArrayHasKey('recent', $result);
        $this->assertIsArray($result['recent']);
    }

    // ==========================================
    // Action Methods (Error Cases)
    // ==========================================

    public function testApprovePartnershipWithNonExistentId(): void
    {
        $result = FederationPartnershipService::approvePartnership(999999, self::$testUserId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testRejectPartnershipWithNonExistentId(): void
    {
        $result = FederationPartnershipService::rejectPartnership(999999, self::$testUserId, 'Testing');

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testSuspendPartnershipWithNonExistentId(): void
    {
        $result = FederationPartnershipService::suspendPartnership(999999, self::$testUserId, 'Testing');

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testReactivatePartnershipWithNonExistentId(): void
    {
        $result = FederationPartnershipService::reactivatePartnership(999999, self::$testUserId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testTerminatePartnershipWithNonExistentId(): void
    {
        $result = FederationPartnershipService::terminatePartnership(999999, self::$testUserId, 'Testing');

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testCounterProposeWithNonExistentId(): void
    {
        $result = FederationPartnershipService::counterPropose(999999, self::$testUserId, 2);

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testAcceptCounterProposalWithNonExistentId(): void
    {
        $result = FederationPartnershipService::acceptCounterProposal(999999, self::$testUserId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }

    public function testUpdatePermissionsWithNonExistentId(): void
    {
        $result = FederationPartnershipService::updatePermissions(
            999999,
            ['profiles' => true],
            self::$testUserId
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Partnership not found', $result['error']);
    }
}
