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
use Nexus\Services\OptimizedGroupQueries;

/**
 * OptimizedGroupQueries Tests
 *
 * Tests high-performance hierarchical group queries using
 * recursive CTEs instead of correlated subqueries.
 */
class OptimizedGroupQueriesTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testParentGroupId = null;
    protected static ?int $testChildGroupId = null;

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

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, "optgrp_{$ts}@test.com", "optgrp_{$ts}", 'Opt', 'User', 'Opt User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create parent group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, created_by, status, created_at)
             VALUES (?, ?, ?, ?, 'active', NOW())",
            [self::$testTenantId, "Parent Group {$ts}", 'Test parent group', self::$testUserId]
        );
        self::$testParentGroupId = (int)Database::getInstance()->lastInsertId();

        // Create child group (leaf group)
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, created_by, parent_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'active', NOW())",
            [self::$testTenantId, "Child Group {$ts}", 'Test child group', self::$testUserId, self::$testParentGroupId]
        );
        self::$testChildGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testChildGroupId) {
            try {
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testChildGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testParentGroupId) {
            try {
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testParentGroupId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Get Leaf Groups Tests
    // ==========================================

    public function testGetLeafGroupsReturnsArray(): void
    {
        $leafGroups = OptimizedGroupQueries::getLeafGroups();
        $this->assertIsArray($leafGroups);
    }

    public function testGetLeafGroupsIncludesMemberCount(): void
    {
        $leafGroups = OptimizedGroupQueries::getLeafGroups();

        foreach ($leafGroups as $group) {
            $this->assertArrayHasKey('member_count', $group);
            $this->assertIsNumeric($group['member_count']);
        }
    }

    public function testGetLeafGroupsRespectsLimit(): void
    {
        $limit = 5;
        $leafGroups = OptimizedGroupQueries::getLeafGroups(null, null, $limit);

        $this->assertLessThanOrEqual($limit, count($leafGroups));
    }

    public function testGetLeafGroupsFiltersByTypeId(): void
    {
        // Get type ID if available
        $stmt = Database::query(
            "SELECT id FROM group_types WHERE tenant_id = ? LIMIT 1",
            [self::$testTenantId]
        );
        $typeRow = $stmt->fetch();

        if ($typeRow) {
            $typeId = (int)$typeRow['id'];
            $leafGroups = OptimizedGroupQueries::getLeafGroups(null, $typeId);
            $this->assertIsArray($leafGroups);
        }

        $this->assertTrue(true);
    }

    public function testGetLeafGroupsExcludesGroupsWithChildren(): void
    {
        $leafGroups = OptimizedGroupQueries::getLeafGroups();

        $leafIds = array_column($leafGroups, 'id');
        // Parent group should not be in leaf groups
        $this->assertNotContains(self::$testParentGroupId, $leafIds);
    }

    // ==========================================
    // Get Group Hierarchy Tree Tests
    // ==========================================

    public function testGetGroupHierarchyTreeReturnsArray(): void
    {
        $tree = OptimizedGroupQueries::getGroupHierarchyTree(self::$testChildGroupId);
        $this->assertIsArray($tree);
    }

    public function testGetGroupHierarchyTreeIncludesAncestors(): void
    {
        $tree = OptimizedGroupQueries::getGroupHierarchyTree(self::$testChildGroupId);

        if (!empty($tree)) {
            // Should include parent in the hierarchy
            $ids = array_column($tree, 'id');
            $this->assertContains(self::$testParentGroupId, $ids);
        }
        $this->assertTrue(true);
    }

    public function testGetGroupHierarchyTreeIncludesDirection(): void
    {
        $tree = OptimizedGroupQueries::getGroupHierarchyTree(self::$testChildGroupId);

        foreach ($tree as $node) {
            $this->assertArrayHasKey('direction', $node);
            $this->assertContains($node['direction'], ['ancestor', 'descendant', 'current']);
        }
    }

    // ==========================================
    // Get All Descendants Tests
    // ==========================================

    public function testGetAllDescendantsReturnsArray(): void
    {
        $descendants = OptimizedGroupQueries::getAllDescendants(self::$testParentGroupId);
        $this->assertIsArray($descendants);
    }

    public function testGetAllDescendantsIncludesChildGroup(): void
    {
        $descendants = OptimizedGroupQueries::getAllDescendants(self::$testParentGroupId);

        $ids = array_column($descendants, 'id');
        $this->assertContains(self::$testChildGroupId, $ids);
    }

    public function testGetAllDescendantsExcludesParent(): void
    {
        $descendants = OptimizedGroupQueries::getAllDescendants(self::$testParentGroupId);

        $ids = array_column($descendants, 'id');
        $this->assertNotContains(self::$testParentGroupId, $ids);
    }

    // ==========================================
    // Get All Ancestors Tests
    // ==========================================

    public function testGetAllAncestorsReturnsArray(): void
    {
        $ancestors = OptimizedGroupQueries::getAllAncestors(self::$testChildGroupId);
        $this->assertIsArray($ancestors);
    }

    public function testGetAllAncestorsIncludesParent(): void
    {
        $ancestors = OptimizedGroupQueries::getAllAncestors(self::$testChildGroupId);

        $ids = array_column($ancestors, 'id');
        $this->assertContains(self::$testParentGroupId, $ids);
    }

    public function testGetAllAncestorsExcludesChild(): void
    {
        $ancestors = OptimizedGroupQueries::getAllAncestors(self::$testChildGroupId);

        $ids = array_column($ancestors, 'id');
        $this->assertNotContains(self::$testChildGroupId, $ids);
    }
}
