<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\OrgMember;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class OrgMemberTest extends TestCase
{
    private static $testOwnerId;
    private static $testAdminId;
    private static $testMemberId;
    private static $testNonMemberId;
    private static $testOrgId;
    private static $testTenantId = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Use unique emails with timestamp to avoid conflicts
        $timestamp = time() . rand(1000, 9999);

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, 'Test', 'Owner', 0, 1, NOW())",
            [self::$testTenantId, "member_owner_{$timestamp}@test.com"]
        );
        self::$testOwnerId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, 'Test', 'Admin', 0, 1, NOW())",
            [self::$testTenantId, "member_admin_{$timestamp}@test.com"]
        );
        self::$testAdminId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, 'Test', 'Member', 0, 1, NOW())",
            [self::$testTenantId, "member_member_{$timestamp}@test.com"]
        );
        self::$testMemberId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, 'Non', 'Member', 0, 1, NOW())",
            [self::$testTenantId, "member_nonmember_{$timestamp}@test.com"]
        );
        self::$testNonMemberId = Database::getInstance()->lastInsertId();

        // Create test organization
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, 'Member Test Org', 'Test organization', 'active', NOW())",
            [self::$testTenantId, self::$testOwnerId]
        );
        self::$testOrgId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testOrgId) {
            Database::query("DELETE FROM org_members WHERE organization_id = ?", [self::$testOrgId]);
            Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
        }
        $userIds = [self::$testOwnerId, self::$testAdminId, self::$testMemberId, self::$testNonMemberId];
        Database::query(
            "DELETE FROM users WHERE id IN (?, ?, ?, ?)",
            $userIds
        );
    }

    protected function setUp(): void
    {
        // Clean up members before each test
        Database::query("DELETE FROM org_members WHERE organization_id = ?", [self::$testOrgId]);

        // Set up standard membership structure
        OrgMember::add(self::$testOrgId, self::$testOwnerId, 'owner', 'active');
        OrgMember::add(self::$testOrgId, self::$testAdminId, 'admin', 'active');
        OrgMember::add(self::$testOrgId, self::$testMemberId, 'member', 'active');
    }

    /**
     * Test add creates membership
     */
    public function testAddCreatesMembership(): void
    {
        $isMember = OrgMember::isMember(self::$testOrgId, self::$testMemberId);
        $this->assertTrue($isMember);
    }

    /**
     * Test isOwner returns true for owner
     */
    public function testIsOwnerReturnsTrueForOwner(): void
    {
        $this->assertTrue(OrgMember::isOwner(self::$testOrgId, self::$testOwnerId));
    }

    /**
     * Test isOwner returns false for admin
     */
    public function testIsOwnerReturnsFalseForAdmin(): void
    {
        $this->assertFalse(OrgMember::isOwner(self::$testOrgId, self::$testAdminId));
    }

    /**
     * Test isOwner returns false for member
     */
    public function testIsOwnerReturnsFalseForMember(): void
    {
        $this->assertFalse(OrgMember::isOwner(self::$testOrgId, self::$testMemberId));
    }

    /**
     * Test isAdmin returns true for owner
     */
    public function testIsAdminReturnsTrueForOwner(): void
    {
        $this->assertTrue(OrgMember::isAdmin(self::$testOrgId, self::$testOwnerId));
    }

    /**
     * Test isAdmin returns true for admin
     */
    public function testIsAdminReturnsTrueForAdmin(): void
    {
        $this->assertTrue(OrgMember::isAdmin(self::$testOrgId, self::$testAdminId));
    }

    /**
     * Test isAdmin returns false for member
     */
    public function testIsAdminReturnsFalseForMember(): void
    {
        $this->assertFalse(OrgMember::isAdmin(self::$testOrgId, self::$testMemberId));
    }

    /**
     * Test isMember returns true for all active members
     */
    public function testIsMemberReturnsTrueForAllActiveMembers(): void
    {
        $this->assertTrue(OrgMember::isMember(self::$testOrgId, self::$testOwnerId));
        $this->assertTrue(OrgMember::isMember(self::$testOrgId, self::$testAdminId));
        $this->assertTrue(OrgMember::isMember(self::$testOrgId, self::$testMemberId));
    }

    /**
     * Test isMember returns false for non-member
     */
    public function testIsMemberReturnsFalseForNonMember(): void
    {
        $this->assertFalse(OrgMember::isMember(self::$testOrgId, self::$testNonMemberId));
    }

    /**
     * Test getRole returns correct role
     */
    public function testGetRoleReturnsCorrectRole(): void
    {
        $this->assertEquals('owner', OrgMember::getRole(self::$testOrgId, self::$testOwnerId));
        $this->assertEquals('admin', OrgMember::getRole(self::$testOrgId, self::$testAdminId));
        $this->assertEquals('member', OrgMember::getRole(self::$testOrgId, self::$testMemberId));
    }

    /**
     * Test getRole returns false for non-member
     */
    public function testGetRoleReturnsFalseForNonMember(): void
    {
        $role = OrgMember::getRole(self::$testOrgId, self::$testNonMemberId);
        $this->assertFalse($role);
    }

    /**
     * Test updateRole changes member role
     */
    public function testUpdateRoleChangesRole(): void
    {
        OrgMember::updateRole(self::$testOrgId, self::$testMemberId, 'admin');

        $newRole = OrgMember::getRole(self::$testOrgId, self::$testMemberId);
        $this->assertEquals('admin', $newRole);
    }

    /**
     * Test remove soft-deletes membership
     */
    public function testRemoveSoftDeletesMembership(): void
    {
        OrgMember::remove(self::$testOrgId, self::$testMemberId);

        // isMember should return false now
        $this->assertFalse(OrgMember::isMember(self::$testOrgId, self::$testMemberId));
    }

    /**
     * Test getMembers returns all active members
     */
    public function testGetMembersReturnsAllActiveMembers(): void
    {
        $members = OrgMember::getMembers(self::$testOrgId);

        $this->assertIsArray($members);
        $this->assertCount(3, $members); // owner, admin, member

        // Check member data structure
        $this->assertArrayHasKey('user_id', $members[0]);
        $this->assertArrayHasKey('role', $members[0]);
        $this->assertArrayHasKey('first_name', $members[0]);
        $this->assertArrayHasKey('last_name', $members[0]);
    }

    /**
     * Test getMembers excludes removed members
     */
    public function testGetMembersExcludesRemovedMembers(): void
    {
        OrgMember::remove(self::$testOrgId, self::$testMemberId);

        $members = OrgMember::getMembers(self::$testOrgId);
        $this->assertCount(2, $members); // only owner and admin
    }

    /**
     * Test getAdmins returns only owners and admins
     */
    public function testGetAdminsReturnsOnlyOwnersAndAdmins(): void
    {
        $admins = OrgMember::getAdmins(self::$testOrgId);

        $this->assertIsArray($admins);
        $this->assertCount(2, $admins); // owner and admin only

        // Verify roles
        foreach ($admins as $admin) {
            $this->assertContains($admin['role'], ['owner', 'admin']);
        }
    }

    /**
     * Test countMembers returns correct count
     */
    public function testCountMembersReturnsCorrectCount(): void
    {
        $count = OrgMember::countMembers(self::$testOrgId);
        $this->assertEquals(3, $count);
    }

    /**
     * Test countMembers excludes removed
     */
    public function testCountMembersExcludesRemoved(): void
    {
        OrgMember::remove(self::$testOrgId, self::$testMemberId);

        $count = OrgMember::countMembers(self::$testOrgId);
        $this->assertEquals(2, $count);
    }

    /**
     * Test getUserOrganizations returns orgs for user
     */
    public function testGetUserOrganizationsReturnsOrgsForUser(): void
    {
        $orgs = OrgMember::getUserOrganizations(self::$testMemberId);

        $this->assertIsArray($orgs);
        $this->assertNotEmpty($orgs);

        // Check structure
        $this->assertArrayHasKey('name', $orgs[0]);
        $this->assertArrayHasKey('member_role', $orgs[0]);
    }

    /**
     * Test initializeOwner sets owner role
     */
    public function testInitializeOwnerSetsOwnerRole(): void
    {
        // Create a new org for this test
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, 'Init Test Org', 'Test', 'active', NOW())",
            [self::$testTenantId, self::$testNonMemberId]
        );
        $newOrgId = Database::getInstance()->lastInsertId();

        OrgMember::initializeOwner($newOrgId, self::$testNonMemberId);

        $this->assertTrue(OrgMember::isOwner($newOrgId, self::$testNonMemberId));

        // Cleanup
        Database::query("DELETE FROM org_members WHERE organization_id = ?", [$newOrgId]);
        Database::query("DELETE FROM vol_organizations WHERE id = ?", [$newOrgId]);
    }

    /**
     * Test pending status members are not counted as active
     */
    public function testPendingMembersNotCountedAsActive(): void
    {
        OrgMember::add(self::$testOrgId, self::$testNonMemberId, 'member', 'pending');

        $this->assertFalse(OrgMember::isMember(self::$testOrgId, self::$testNonMemberId));

        $count = OrgMember::countMembers(self::$testOrgId);
        $this->assertEquals(3, $count); // Still 3, pending not counted
    }

    /**
     * Test getPendingRequests returns pending members
     */
    public function testGetPendingRequestsReturnsPendingMembers(): void
    {
        OrgMember::add(self::$testOrgId, self::$testNonMemberId, 'member', 'pending');

        $pending = OrgMember::getPendingRequests(self::$testOrgId);

        $this->assertIsArray($pending);
        $this->assertCount(1, $pending);
        $this->assertEquals(self::$testNonMemberId, $pending[0]['user_id']);
    }

    /**
     * Test updateStatus changes member status
     */
    public function testUpdateStatusChangesStatus(): void
    {
        OrgMember::add(self::$testOrgId, self::$testNonMemberId, 'member', 'pending');
        OrgMember::updateStatus(self::$testOrgId, self::$testNonMemberId, 'active');

        $this->assertTrue(OrgMember::isMember(self::$testOrgId, self::$testNonMemberId));
    }
}
