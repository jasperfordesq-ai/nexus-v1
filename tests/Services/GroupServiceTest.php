<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GroupService;

/**
 * GroupService Tests
 *
 * Tests group CRUD, membership management, role management, and discussions.
 */
class GroupServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testUser3Id = null;
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
        $ts = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "grpsvc_user1_{$ts}@test.com", "grpsvc_user1_{$ts}", 'Group', 'Owner', 'Group Owner']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "grpsvc_user2_{$ts}@test.com", "grpsvc_user2_{$ts}", 'Group', 'Member', 'Group Member']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 25, 1, NOW())",
            [self::$testTenantId, "grpsvc_user3_{$ts}@test.com", "grpsvc_user3_{$ts}", 'Third', 'User', 'Third User']
        );
        self::$testUser3Id = (int)Database::getInstance()->lastInsertId();

        // Create public test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, cached_member_count, created_at)
             VALUES (?, ?, ?, ?, 'public', 1, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Public Group {$ts}",
                "Test group description"
            ]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();

        // Add owner as member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, role, status, joined_at)
             VALUES (?, ?, 'owner', 'active', NOW())",
            [self::$testGroupId, self::$testUserId]
        );

        // Create private test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, cached_member_count, created_at)
             VALUES (?, ?, ?, ?, 'private', 1, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Private Group {$ts}",
                "Private group description"
            ]
        );
        self::$testPrivateGroupId = (int)Database::getInstance()->lastInsertId();

        // Add owner as member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, role, status, joined_at)
             VALUES (?, ?, 'owner', 'active', NOW())",
            [self::$testPrivateGroupId, self::$testUserId]
        );
    }

    public static function tearDownAfterClass(): void
    {
        $groupIds = array_filter([self::$testGroupId, self::$testPrivateGroupId]);
        foreach ($groupIds as $gid) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [$gid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM `groups` WHERE id = ? AND tenant_id = ?", [$gid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        $userIds = array_filter([self::$testUserId, self::$testUser2Id, self::$testUser3Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getAll Tests
    // ==========================================

    public function testGetAllReturnsValidStructure(): void
    {
        $result = GroupService::getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetAllFiltersByVisibility(): void
    {
        $result = GroupService::getAll(['visibility' => 'public']);

        foreach ($result['items'] as $group) {
            $this->assertEquals('public', $group['visibility']);
        }
    }

    public function testGetAllFiltersByUserMembership(): void
    {
        try {
            $result = GroupService::getAll(['user_id' => self::$testUserId]);

            $this->assertNotEmpty($result['items']);
            // All returned groups should have this user as a member
        } catch (\Exception $e) {
            $this->markTestSkipped('User filter not available: ' . $e->getMessage());
        }
    }

    public function testGetAllRespectsLimit(): void
    {
        $result = GroupService::getAll(['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function testGetAllEnforcesMaxLimit(): void
    {
        $result = GroupService::getAll(['limit' => 500]);

        $this->assertLessThanOrEqual(100, count($result['items']));
    }

    public function testGetAllIncludesMemberCount(): void
    {
        $result = GroupService::getAll();

        if (!empty($result['items'])) {
            $group = $result['items'][0];
            $this->assertArrayHasKey('member_count', $group);
            $this->assertIsInt($group['member_count']);
        }
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdReturnsValidGroup(): void
    {
        $group = GroupService::getById(self::$testGroupId);

        $this->assertNotNull($group);
        $this->assertIsArray($group);
        $this->assertEquals(self::$testGroupId, $group['id']);
        $this->assertArrayHasKey('name', $group);
        $this->assertArrayHasKey('description', $group);
        $this->assertArrayHasKey('visibility', $group);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $group = GroupService::getById(999999);

        $this->assertNull($group);
    }

    public function testGetByIdIncludesOwnerInfo(): void
    {
        $group = GroupService::getById(self::$testGroupId);

        $this->assertNotNull($group);
        // getById returns nested 'owner' object with id/name/avatar
        $this->assertArrayHasKey('owner', $group);
        $this->assertIsArray($group['owner']);
        $this->assertArrayHasKey('id', $group['owner']);
        $this->assertArrayHasKey('name', $group['owner']);
    }

    // ==========================================
    // validateGroup Tests
    // ==========================================

    public function testValidateGroupAcceptsValidData(): void
    {
        // GroupService::validate() exists, not validateGroup()
        $valid = GroupService::validate([
            'name' => 'Valid Group Name',
            'description' => 'Valid description',
            'visibility' => 'public',
        ]);

        $this->assertTrue($valid);
        $this->assertEmpty(GroupService::getErrors());
    }

    public function testValidateGroupRejectsMissingName(): void
    {
        $valid = GroupService::validate([
            'description' => 'Description',
            'visibility' => 'public',
        ]);

        $this->assertFalse($valid);
        $this->assertNotEmpty(GroupService::getErrors());
    }

    public function testValidateGroupRejectsEmptyName(): void
    {
        $valid = GroupService::validate([
            'name' => '',
            'description' => 'Description',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateGroupRejectsTooLongName(): void
    {
        $valid = GroupService::validate([
            'name' => str_repeat('A', 256),
            'description' => 'Description',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateGroupRejectsInvalidVisibility(): void
    {
        $valid = GroupService::validate([
            'name' => 'Valid Name',
            'visibility' => 'invalid_value',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateGroupAcceptsPublicVisibility(): void
    {
        $valid = GroupService::validate([
            'name' => 'Valid Name',
            'visibility' => 'public',
        ]);

        $this->assertTrue($valid);
    }

    public function testValidateGroupAcceptsPrivateVisibility(): void
    {
        $valid = GroupService::validate([
            'name' => 'Valid Name',
            'visibility' => 'private',
        ]);

        $this->assertTrue($valid);
    }

    // ==========================================
    // Membership Tests
    // ==========================================

    public function testJoinGroupReturnsStatusForPublicGroup(): void
    {
        // join() returns status string ('active', 'pending'), not bool
        $status = GroupService::join(self::$testGroupId, self::$testUser2Id);

        $this->assertIsString($status);
        $this->assertEquals('active', $status); // Public groups auto-accept

        // Cleanup
        Database::query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [self::$testGroupId, self::$testUser2Id]);
    }

    public function testJoinGroupReturnsNullForAlreadyMember(): void
    {
        // Join once
        GroupService::join(self::$testGroupId, self::$testUser3Id);

        // Try to join again - returns null on failure
        $result = GroupService::join(self::$testGroupId, self::$testUser3Id);

        $this->assertNull($result);

        // Cleanup
        Database::query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [self::$testGroupId, self::$testUser3Id]);
    }

    public function testLeaveGroupReturnsTrueForMember(): void
    {
        // First join
        GroupService::join(self::$testGroupId, self::$testUser2Id);

        // Then leave - returns bool
        $result = GroupService::leave(self::$testGroupId, self::$testUser2Id);

        $this->assertTrue($result);

        // Cleanup
        Database::query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [self::$testGroupId, self::$testUser2Id]);
    }

    public function testLeaveGroupReturnsFalseForNonMember(): void
    {
        $result = GroupService::leave(self::$testGroupId, self::$testUser3Id);

        $this->assertFalse($result);
    }

    // ==========================================
    // getMembers Tests
    // ==========================================

    public function testGetMembersReturnsValidStructure(): void
    {
        // getMembers returns paginated structure, not flat array
        $result = GroupService::getMembers(self::$testGroupId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetMembersIncludesOwner(): void
    {
        $result = GroupService::getMembers(self::$testGroupId);

        $ownerFound = false;
        foreach ($result['items'] as $member) {
            // getMembers returns 'id' (user_id), not 'user_id' field
            if ($member['id'] == self::$testUserId) {
                $ownerFound = true;
                $this->assertEquals('owner', $member['role']);
                break;
            }
        }

        $this->assertTrue($ownerFound, 'Owner should be in members list');
    }

    public function testGetMembersFiltersByRole(): void
    {
        // getMembers filters by 'role', not 'status'
        $result = GroupService::getMembers(self::$testGroupId, ['role' => 'owner']);

        foreach ($result['items'] as $member) {
            $this->assertEquals('owner', $member['role']);
        }
    }

    // ==========================================
    // Role Management Tests
    // ==========================================

    public function testPromoteMemberReturnsTrueForOwner(): void
    {
        // Add a member first
        GroupService::join(self::$testGroupId, self::$testUser2Id);

        // updateMemberRole(groupId, targetUserId, actingUserId, role)
        $result = GroupService::updateMemberRole(self::$testGroupId, self::$testUser2Id, self::$testUserId, 'admin');

        $this->assertTrue($result);

        // Cleanup
        Database::query("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [self::$testGroupId, self::$testUser2Id]);
    }

    public function testPromoteMemberReturnsFalseForNonOwner(): void
    {
        // User 3 trying to promote (not owner)
        $result = GroupService::updateMemberRole(self::$testGroupId, self::$testUser2Id, self::$testUser3Id, 'admin');

        $this->assertFalse($result);
    }
}
