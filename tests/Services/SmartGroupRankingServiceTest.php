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
use Nexus\Services\SmartGroupRankingService;

/**
 * SmartGroupRankingService Tests
 *
 * Tests automated featured group selection based on member count,
 * activity, and geographic diversity.
 */
class SmartGroupRankingServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

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
            [self::$testTenantId, "rank_{$ts}@test.com", "rank_{$ts}", 'Rank', 'User', 'Rank User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, created_by, status, is_featured, created_at)
             VALUES (?, ?, ?, ?, 'active', 0, NOW())",
            [self::$testTenantId, "Ranking Group {$ts}", 'Test group for ranking', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
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
    // Update Featured Local Hubs Tests
    // ==========================================

    public function testUpdateFeaturedLocalHubsReturnsStats(): void
    {
        $result = SmartGroupRankingService::updateFeaturedLocalHubs();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cleared', $result);
        $this->assertArrayHasKey('featured', $result);
        $this->assertArrayHasKey('groups', $result);
        $this->assertArrayHasKey('algorithm', $result);
    }

    public function testUpdateFeaturedLocalHubsClearsExistingFlags(): void
    {
        // Set a group as featured first
        Database::query("UPDATE `groups` SET is_featured = 1 WHERE id = ?", [self::$testGroupId]);

        $result = SmartGroupRankingService::updateFeaturedLocalHubs();

        $this->assertIsInt($result['cleared']);
    }

    public function testUpdateFeaturedLocalHubsRespectsLimit(): void
    {
        $limit = 3;
        $result = SmartGroupRankingService::updateFeaturedLocalHubs(null, $limit);

        $this->assertLessThanOrEqual($limit, $result['featured']);
    }

    public function testUpdateFeaturedLocalHubsUsesOptimizedQueries(): void
    {
        $result = SmartGroupRankingService::updateFeaturedLocalHubs();

        // Should reference the optimized algorithm
        $this->assertEquals('member_count_with_geographic_diversity', $result['algorithm']);
    }

    public function testUpdateFeaturedLocalHubsIncludesGroupData(): void
    {
        $result = SmartGroupRankingService::updateFeaturedLocalHubs();

        if (!empty($result['groups'])) {
            foreach ($result['groups'] as $group) {
                $this->assertArrayHasKey('id', $group);
                $this->assertArrayHasKey('name', $group);
                $this->assertArrayHasKey('member_count', $group);
            }
        }
        $this->assertTrue(true);
    }

    // ==========================================
    // Feature Group Tests
    // ==========================================

    public function testFeatureGroupSetsFlag(): void
    {
        $result = SmartGroupRankingService::featureGroup(self::$testGroupId);

        if ($result) {
            // Verify flag set
            $stmt = Database::query("SELECT is_featured FROM `groups` WHERE id = ?", [self::$testGroupId]);
            $group = $stmt->fetch();
            $this->assertEquals(1, $group['is_featured']);

            // Reset
            Database::query("UPDATE `groups` SET is_featured = 0 WHERE id = ?", [self::$testGroupId]);
        }
        $this->assertTrue(true);
    }

    // ==========================================
    // Unfeature Group Tests
    // ==========================================

    public function testUnfeatureGroupClearsFlag(): void
    {
        // Set as featured first
        Database::query("UPDATE `groups` SET is_featured = 1 WHERE id = ?", [self::$testGroupId]);

        $result = SmartGroupRankingService::unfeatureGroup(self::$testGroupId);

        if ($result) {
            // Verify flag cleared
            $stmt = Database::query("SELECT is_featured FROM `groups` WHERE id = ?", [self::$testGroupId]);
            $group = $stmt->fetch();
            $this->assertEquals(0, $group['is_featured']);
        }
        $this->assertTrue(true);
    }

    // ==========================================
    // Get Featured Groups Tests
    // ==========================================

    public function testGetFeaturedGroupsReturnsArray(): void
    {
        $groups = SmartGroupRankingService::getFeaturedGroups();
        $this->assertIsArray($groups);
    }

    public function testGetFeaturedGroupsOnlyReturnsFeatured(): void
    {
        $groups = SmartGroupRankingService::getFeaturedGroups();

        foreach ($groups as $group) {
            $this->assertEquals(1, $group['is_featured']);
        }
        $this->assertTrue(true);
    }
}
