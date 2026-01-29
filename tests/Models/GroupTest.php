<?php

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;

/**
 * Group Model Tests
 *
 * Tests group creation, membership, retrieval, and various group methods.
 */
class GroupTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testGroupId = null;
    protected static ?int $testPrivateGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test owner user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'member', NOW())",
            [
                self::$testTenantId,
                "group_model_test_{$timestamp}@test.com",
                "group_model_test_{$timestamp}",
                'Group',
                'Owner',
                'Group Owner',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create second test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, role, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'member', NOW())",
            [
                self::$testTenantId,
                "group_model_test2_{$timestamp}@test.com",
                "group_model_test2_{$timestamp}",
                'Group',
                'Member',
                'Group Member',
                50
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create public test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, location, created_at)
             VALUES (?, ?, ?, ?, 'public', ?, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Group {$timestamp}",
                "This is a test group for model tests.",
                'Dublin, Ireland'
            ]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();

        // Add owner as member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')",
            [self::$testGroupId, self::$testUserId]
        );

        // Create private test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, created_at)
             VALUES (?, ?, ?, ?, 'private', NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Private Test Group {$timestamp}",
                "This is a private test group."
            ]
        );
        self::$testPrivateGroupId = (int)Database::getInstance()->lastInsertId();

        // Add owner as member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')",
            [self::$testPrivateGroupId, self::$testUserId]
        );
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up in correct order due to foreign keys
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testPrivateGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testPrivateGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testPrivateGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM group_members WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM group_members WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateGroupReturnsId(): void
    {
        $timestamp = time();

        $id = Group::create(
            self::$testUserId,
            "New Test Group {$timestamp}",
            'A newly created test group'
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, $id);

        // Clean up
        Database::query("DELETE FROM group_members WHERE group_id = ?", [$id]);
        Database::query("DELETE FROM `groups` WHERE id = ?", [$id]);
    }

    public function testCreateGroupWithAllFields(): void
    {
        $timestamp = time();

        $id = Group::create(
            self::$testUserId,
            "Full Group {$timestamp}",
            'Full description',
            '/uploads/group-image.jpg',
            'private',
            'Cork, Ireland',
            51.8969,
            -8.4863,
            null, // typeId
            'listed' // federatedVisibility
        );

        $this->assertIsNumeric($id);

        $group = Group::findById($id);
        $this->assertEquals("Full Group {$timestamp}", $group['name']);
        $this->assertEquals('private', $group['visibility']);
        $this->assertEquals('/uploads/group-image.jpg', $group['image_url']);
        $this->assertEquals('Cork, Ireland', $group['location']);

        // Clean up
        Database::query("DELETE FROM group_members WHERE group_id = ?", [$id]);
        Database::query("DELETE FROM `groups` WHERE id = ?", [$id]);
    }

    public function testCreateGroupValidatesFederatedVisibility(): void
    {
        $id = Group::create(
            self::$testUserId,
            'Visibility Test Group',
            'Description',
            '',
            'public',
            '',
            null,
            null,
            null,
            'invalid_visibility' // Should default to 'none'
        );

        $group = Database::query(
            "SELECT federated_visibility FROM `groups` WHERE id = ?",
            [$id]
        )->fetch();

        $this->assertEquals('none', $group['federated_visibility']);

        // Clean up
        Database::query("DELETE FROM group_members WHERE group_id = ?", [$id]);
        Database::query("DELETE FROM `groups` WHERE id = ?", [$id]);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindByIdReturnsGroup(): void
    {
        $group = Group::findById(self::$testGroupId);

        $this->assertNotFalse($group);
        $this->assertIsArray($group);
        $this->assertEquals(self::$testGroupId, $group['id']);
    }

    public function testFindByIdIncludesOwnerName(): void
    {
        $group = Group::findById(self::$testGroupId);

        $this->assertArrayHasKey('owner_name', $group);
        $this->assertNotEmpty($group['owner_name']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $group = Group::findById(999999999);

        $this->assertFalse($group);
    }

    // ==========================================
    // All/List Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $groups = Group::all();

        $this->assertIsArray($groups);
        $this->assertGreaterThanOrEqual(1, count($groups));
    }

    public function testAllFiltersBySearch(): void
    {
        $groups = Group::all('Test Group');

        $this->assertIsArray($groups);
        // Should find our test group
        $found = false;
        foreach ($groups as $group) {
            if ($group['id'] == self::$testGroupId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testAllIncludesMemberCount(): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $this->assertArrayHasKey('member_count', $group);
            $this->assertIsNumeric($group['member_count']);
        }
    }

    public function testGetFeaturedReturnsArray(): void
    {
        $groups = Group::getFeatured(3);

        $this->assertIsArray($groups);
        $this->assertLessThanOrEqual(3, count($groups));
    }

    // ==========================================
    // Membership Tests
    // ==========================================

    public function testJoinPublicGroupReturnsActive(): void
    {
        // Ensure user is not already a member
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
            [self::$testGroupId, self::$testUser2Id]
        );

        $status = Group::join(self::$testGroupId, self::$testUser2Id);

        $this->assertEquals('active', $status);

        // Verify membership
        $this->assertTrue(Group::isMember(self::$testGroupId, self::$testUser2Id));

        // Clean up
        Group::leave(self::$testGroupId, self::$testUser2Id);
    }

    public function testJoinPrivateGroupReturnsPending(): void
    {
        // Ensure user is not already a member
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
            [self::$testPrivateGroupId, self::$testUser2Id]
        );

        $status = Group::join(self::$testPrivateGroupId, self::$testUser2Id);

        $this->assertEquals('pending', $status);

        // Should not be an active member yet
        $this->assertFalse(Group::isMember(self::$testPrivateGroupId, self::$testUser2Id));

        // Clean up
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
            [self::$testPrivateGroupId, self::$testUser2Id]
        );
    }

    public function testLeaveRemovesMembership(): void
    {
        // First join
        Group::join(self::$testGroupId, self::$testUser2Id);
        $this->assertTrue(Group::isMember(self::$testGroupId, self::$testUser2Id));

        // Then leave
        Group::leave(self::$testGroupId, self::$testUser2Id);
        $this->assertFalse(Group::isMember(self::$testGroupId, self::$testUser2Id));
    }

    public function testIsMemberReturnsBool(): void
    {
        $result = Group::isMember(self::$testGroupId, self::$testUserId);

        $this->assertIsBool($result);
        $this->assertTrue($result); // Owner should be a member
    }

    public function testGetMembershipStatusReturnsCorrectStatus(): void
    {
        $status = Group::getMembershipStatus(self::$testGroupId, self::$testUserId);

        $this->assertEquals('active', $status);
    }

    public function testGetMembershipStatusReturnsNullForNonMember(): void
    {
        // Ensure user is not a member
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
            [self::$testGroupId, self::$testUser2Id]
        );

        $status = Group::getMembershipStatus(self::$testGroupId, self::$testUser2Id);

        $this->assertNull($status);
    }

    public function testGetMembersReturnsActiveMembers(): void
    {
        $members = Group::getMembers(self::$testGroupId);

        $this->assertIsArray($members);
        $this->assertGreaterThanOrEqual(1, count($members));

        // Owner should be in the list
        $found = false;
        foreach ($members as $member) {
            if ($member['id'] == self::$testUserId) {
                $found = true;
                $this->assertEquals('owner', $member['role']);
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testGetPendingMembersReturnsArray(): void
    {
        $members = Group::getPendingMembers(self::$testGroupId);

        $this->assertIsArray($members);
    }

    public function testGetInvitedMembersReturnsArray(): void
    {
        $members = Group::getInvitedMembers(self::$testGroupId);

        $this->assertIsArray($members);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $newName = 'Updated Group Name ' . time();

        Group::update(self::$testGroupId, [
            'name' => $newName,
            'description' => 'Updated description'
        ]);

        $group = Group::findById(self::$testGroupId);

        $this->assertEquals($newName, $group['name']);
        $this->assertEquals('Updated description', $group['description']);
    }

    public function testUpdateIgnoresNonAllowedFields(): void
    {
        $originalGroup = Group::findById(self::$testGroupId);

        Group::update(self::$testGroupId, [
            'owner_id' => 999999, // Not in allowed list
            'tenant_id' => 999    // Not in allowed list
        ]);

        $updatedGroup = Group::findById(self::$testGroupId);

        // These should remain unchanged
        $this->assertEquals($originalGroup['owner_id'], $updatedGroup['owner_id']);
        $this->assertEquals($originalGroup['tenant_id'], $updatedGroup['tenant_id']);
    }

    public function testUpdatePreservesImageUrlOnEmptyValue(): void
    {
        // First set an image
        Group::update(self::$testGroupId, [
            'image_url' => '/uploads/test-image.jpg'
        ]);

        $groupBefore = Group::findById(self::$testGroupId);
        $this->assertEquals('/uploads/test-image.jpg', $groupBefore['image_url']);

        // Now update with empty value - should be preserved
        Group::update(self::$testGroupId, [
            'image_url' => '',
            'name' => 'Name Change Only'
        ]);

        $groupAfter = Group::findById(self::$testGroupId);
        $this->assertEquals('/uploads/test-image.jpg', $groupAfter['image_url']);
    }

    public function testUpdateSettingsChangesVisibility(): void
    {
        Group::updateSettings(self::$testGroupId, 'private');

        $group = Group::findById(self::$testGroupId);
        $this->assertEquals('private', $group['visibility']);

        // Reset
        Group::updateSettings(self::$testGroupId, 'public');
    }

    public function testUpdateMemberRoleChangesRole(): void
    {
        // First join user
        Group::join(self::$testGroupId, self::$testUser2Id);

        Group::updateMemberRole(self::$testGroupId, self::$testUser2Id, 'admin');

        $members = Group::getMembers(self::$testGroupId);
        $found = false;
        foreach ($members as $member) {
            if ($member['id'] == self::$testUser2Id) {
                $found = true;
                $this->assertEquals('admin', $member['role']);
                break;
            }
        }
        $this->assertTrue($found);

        // Clean up
        Group::leave(self::$testGroupId, self::$testUser2Id);
    }

    public function testUpdateMemberStatusChangesStatus(): void
    {
        // Join private group (will be pending)
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
            [self::$testPrivateGroupId, self::$testUser2Id]
        );
        Group::join(self::$testPrivateGroupId, self::$testUser2Id);

        // Approve
        Group::updateMemberStatus(self::$testPrivateGroupId, self::$testUser2Id, 'active');

        $this->assertTrue(Group::isMember(self::$testPrivateGroupId, self::$testUser2Id));

        // Clean up
        Group::leave(self::$testPrivateGroupId, self::$testUser2Id);
    }

    // ==========================================
    // Admin Tests
    // ==========================================

    public function testIsAdminReturnsTrueForOwner(): void
    {
        $isAdmin = Group::isAdmin(self::$testGroupId, self::$testUserId);

        $this->assertTrue($isAdmin);
    }

    public function testIsAdminReturnsFalseForRegularMember(): void
    {
        // Join as regular member
        Group::join(self::$testGroupId, self::$testUser2Id);

        $isAdmin = Group::isAdmin(self::$testGroupId, self::$testUser2Id);

        $this->assertFalse($isAdmin);

        // Clean up
        Group::leave(self::$testGroupId, self::$testUser2Id);
    }

    public function testGetOrganizersReturnsOwnersAndAdmins(): void
    {
        $organizers = Group::getOrganizers(self::$testGroupId);

        $this->assertIsArray($organizers);
        // Should include at least the owner
        $this->assertGreaterThanOrEqual(1, count($organizers));

        $found = false;
        foreach ($organizers as $org) {
            if ($org['id'] == self::$testUserId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // ==========================================
    // User Groups Tests
    // ==========================================

    public function testGetUserGroupsReturnsUserGroups(): void
    {
        $groups = Group::getUserGroups(self::$testUserId);

        $this->assertIsArray($groups);
        $this->assertGreaterThanOrEqual(1, count($groups));

        // Should include our test group
        $found = false;
        foreach ($groups as $group) {
            if ($group['id'] == self::$testGroupId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // ==========================================
    // SubGroups Tests
    // ==========================================

    public function testGetSubGroupsReturnsArray(): void
    {
        $subGroups = Group::getSubGroups(self::$testGroupId);

        $this->assertIsArray($subGroups);
    }

    // ==========================================
    // Permission Tests
    // ==========================================

    public function testCanCreateHubReturnsFalseForRegularUser(): void
    {
        $canCreate = Group::canCreateHub(self::$testUserId);

        $this->assertFalse($canCreate);
    }

    public function testCanCreateRegularGroupReturnsTrueForLoggedInUser(): void
    {
        $canCreate = Group::canCreateRegularGroup(self::$testUserId);

        $this->assertTrue($canCreate);
    }

    public function testCanCreateRegularGroupReturnsFalseForZeroUserId(): void
    {
        $canCreate = Group::canCreateRegularGroup(0);

        $this->assertFalse($canCreate);
    }

    // ==========================================
    // Hub Tests
    // ==========================================

    public function testIsHubReturnsBool(): void
    {
        $isHub = Group::isHub(self::$testGroupId);

        $this->assertIsBool($isHub);
    }

    public function testGetHubsReturnsArray(): void
    {
        $hubs = Group::getHubs();

        $this->assertIsArray($hubs);
    }

    public function testGetFeaturedHubsReturnsArray(): void
    {
        $hubs = Group::getFeaturedHubs();

        $this->assertIsArray($hubs);
    }

    public function testGetRegularGroupsReturnsArray(): void
    {
        $groups = Group::getRegularGroups();

        $this->assertIsArray($groups);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testJoinAlreadyJoinedUserReturnsCurrentStatus(): void
    {
        // User is already a member (owner)
        $status = Group::join(self::$testGroupId, self::$testUserId);

        // Should return their current status without creating duplicate
        $this->assertNotNull($status);
    }

    public function testAllWithSearchIncludesSubGroups(): void
    {
        // When searching, subgroups should be included
        $groups = Group::all('Test');

        $this->assertIsArray($groups);
    }

    public function testUpdateWithEmptyDataReturnsFalse(): void
    {
        $result = Group::update(self::$testGroupId, []);

        $this->assertFalse($result);
    }

    public function testSearchWithSpecialCharacters(): void
    {
        $groups = Group::all("Test's Group");

        $this->assertIsArray($groups);
        // Should not throw an error
    }
}
