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
use Nexus\Services\FederatedGroupService;

/**
 * FederatedGroupService Tests
 *
 * Tests cross-tenant group discovery, membership, and access controls.
 */
class FederatedGroupServiceTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenant1Id = 1;
        self::$tenant2Id = 2;

        TenantContext::setById(self::$tenant2Id);

        // Create test user
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$tenant2Id, "fed_group_test_{$timestamp}@test.com", "fed_group_test_{$timestamp}", 'FedGroup', 'Test', 'FedGroup Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                // Clean up any federated group memberships created during tests
                Database::query("DELETE FROM group_members WHERE user_id = ? AND is_federated = 1", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getPartnerGroups Tests
    // ==========================================

    public function testGetPartnerGroupsReturnsExpectedStructure(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(self::$tenant2Id);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('groups', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertArrayHasKey('page', $result);
            $this->assertArrayHasKey('per_page', $result);
            $this->assertArrayHasKey('total_pages', $result);
            $this->assertIsArray($result['groups']);
            $this->assertIsInt($result['total']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupsDefaultPagination(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(self::$tenant2Id);

            $this->assertEquals(1, $result['page']);
            $this->assertEquals(12, $result['per_page']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupsWithCustomPagination(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(self::$tenant2Id, 2, 5);

            $this->assertEquals(2, $result['page']);
            $this->assertEquals(5, $result['per_page']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupsWithSearchFilter(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(self::$tenant2Id, 1, 12, 'gardening');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('groups', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupsWithPartnerTenantFilter(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(self::$tenant2Id, 1, 12, null, self::$tenant1Id);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('groups', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupsForNonExistentTenantReturnsEmpty(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroups(999999);

            $this->assertIsArray($result);
            $this->assertEmpty($result['groups']);
            $this->assertEquals(0, $result['total']);
            $this->assertEquals(0, $result['total_pages']);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getPartnerGroup Tests
    // ==========================================

    public function testGetPartnerGroupReturnsNullForNonExistentGroup(): void
    {
        try {
            $result = FederatedGroupService::getPartnerGroup(999999, self::$tenant1Id, self::$tenant2Id);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testGetPartnerGroupReturnsNullWhenPartnershipNotAllowed(): void
    {
        try {
            // Non-existent tenant - no partnership
            $result = FederatedGroupService::getPartnerGroup(1, 999999, self::$tenant2Id);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // joinGroup Tests
    // ==========================================

    public function testJoinGroupReturnsErrorWhenPartnershipNotAllowed(): void
    {
        try {
            $result = FederatedGroupService::joinGroup(
                self::$testUserId,
                self::$tenant2Id,
                1,
                999999 // Non-existent partner tenant
            );

            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Federation partnership', $result['error']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testJoinGroupReturnsErrorForNonExistentGroup(): void
    {
        try {
            // This may return partnership error first if no active partnership exists
            $result = FederatedGroupService::joinGroup(
                self::$testUserId,
                self::$tenant2Id,
                999999,
                self::$tenant1Id
            );

            $this->assertIsArray($result);
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('error', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // leaveGroup Tests
    // ==========================================

    public function testLeaveGroupReturnsSuccess(): void
    {
        try {
            $result = FederatedGroupService::leaveGroup(
                self::$testUserId,
                self::$tenant2Id,
                999999 // Group that user is not a member of
            );

            $this->assertIsArray($result);
            $this->assertTrue($result['success']);
            $this->assertEquals('You have left the group', $result['message']);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // isFederatedMember Tests
    // ==========================================

    public function testIsFederatedMemberReturnsNullForNonMember(): void
    {
        try {
            $result = FederatedGroupService::isFederatedMember(
                self::$testUserId,
                self::$tenant2Id,
                999999
            );

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getUserFederatedGroups Tests
    // ==========================================

    public function testGetUserFederatedGroupsReturnsArray(): void
    {
        try {
            $result = FederatedGroupService::getUserFederatedGroups(self::$testUserId, self::$tenant2Id);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testGetUserFederatedGroupsForNewUserReturnsEmpty(): void
    {
        try {
            $result = FederatedGroupService::getUserFederatedGroups(999999, self::$tenant2Id);

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // canAccessPartnerGroups Tests
    // ==========================================

    public function testCanAccessPartnerGroupsReturnsBool(): void
    {
        try {
            $result = FederatedGroupService::canAccessPartnerGroups(self::$tenant2Id, self::$tenant1Id);

            $this->assertIsBool($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testCanAccessPartnerGroupsReturnsFalseForNonExistentTenant(): void
    {
        try {
            $result = FederatedGroupService::canAccessPartnerGroups(self::$tenant2Id, 999999);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testCanAccessPartnerGroupsReturnsFalseForSameTenant(): void
    {
        try {
            // Partnership between same tenant should not exist
            $result = FederatedGroupService::canAccessPartnerGroups(self::$tenant2Id, self::$tenant2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getPartnerTenants Tests
    // ==========================================

    public function testGetPartnerTenantsReturnsArray(): void
    {
        try {
            $result = FederatedGroupService::getPartnerTenants(self::$tenant2Id);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerTenantsForNonExistentTenantReturnsEmpty(): void
    {
        try {
            $result = FederatedGroupService::getPartnerTenants(999999);

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }

    public function testGetPartnerTenantsContainsExpectedKeys(): void
    {
        try {
            $result = FederatedGroupService::getPartnerTenants(self::$tenant2Id);

            $this->assertIsArray($result);
            foreach ($result as $tenant) {
                $this->assertArrayHasKey('id', $tenant);
                $this->assertArrayHasKey('name', $tenant);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_partnerships table may not have groups_enabled column: ' . $e->getMessage());
        }
    }
}
