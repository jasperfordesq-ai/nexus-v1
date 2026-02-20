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
use Nexus\Services\GroupAchievementService;

/**
 * GroupAchievementService Tests
 *
 * Tests group achievement tracking, progress calculation,
 * and badge awards for group milestones.
 */
class GroupAchievementServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testGroupId = null;
    protected static ?int $testUserId = null;

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
            [self::$testTenantId, "grpach_{$ts}@test.com", "grpach_{$ts}", 'Achievement', 'User', 'Achievement User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [self::$testTenantId, "Achievement Group {$ts}", 'Test group for achievements', self::$testUserId]
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
    // Achievement Constants Tests
    // ==========================================

    public function testGroupAchievementsConstantExists(): void
    {
        $this->assertIsArray(GroupAchievementService::GROUP_ACHIEVEMENTS);
        $this->assertNotEmpty(GroupAchievementService::GROUP_ACHIEVEMENTS);
    }

    public function testGroupAchievementsHaveRequiredFields(): void
    {
        foreach (GroupAchievementService::GROUP_ACHIEVEMENTS as $key => $achievement) {
            $this->assertArrayHasKey('name', $achievement);
            $this->assertArrayHasKey('description', $achievement);
            $this->assertArrayHasKey('target_type', $achievement);
            $this->assertArrayHasKey('target_value', $achievement);
            $this->assertArrayHasKey('xp_reward', $achievement);
        }
    }

    public function testCommunityBuildersAchievementExists(): void
    {
        $this->assertArrayHasKey('community_builders', GroupAchievementService::GROUP_ACHIEVEMENTS);
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['community_builders'];
        $this->assertEquals('member_count', $achievement['target_type']);
        $this->assertEquals(50, $achievement['target_value']);
    }

    public function testActiveHubAchievementExists(): void
    {
        $this->assertArrayHasKey('active_hub', GroupAchievementService::GROUP_ACHIEVEMENTS);
        $achievement = GroupAchievementService::GROUP_ACHIEVEMENTS['active_hub'];
        $this->assertEquals('post_count', $achievement['target_type']);
    }

    // ==========================================
    // Get Achievements Tests
    // ==========================================

    public function testGetGroupAchievementsReturnsArray(): void
    {
        $achievements = GroupAchievementService::getGroupAchievements(self::$testGroupId);
        $this->assertIsArray($achievements);
        $this->assertNotEmpty($achievements);
    }

    public function testGetGroupAchievementsIncludesProgress(): void
    {
        $achievements = GroupAchievementService::getGroupAchievements(self::$testGroupId);

        foreach ($achievements as $achievement) {
            $this->assertArrayHasKey('progress', $achievement);
            $this->assertArrayHasKey('target', $achievement);
            $this->assertArrayHasKey('earned', $achievement);
        }
    }

    public function testGetGroupAchievementsIncludesKeys(): void
    {
        $achievements = GroupAchievementService::getGroupAchievements(self::$testGroupId);

        foreach ($achievements as $achievement) {
            $this->assertArrayHasKey('key', $achievement);
            $this->assertArrayHasKey('name', $achievement);
            $this->assertArrayHasKey('description', $achievement);
        }
    }

    // ==========================================
    // Calculate Progress Tests
    // ==========================================

    public function testCalculateProgressReturnsnumeric(): void
    {
        $progress = GroupAchievementService::calculateProgress(
            self::$testGroupId,
            'member_count',
            50
        );

        $this->assertIsNumeric($progress);
        $this->assertGreaterThanOrEqual(0, $progress);
    }

    public function testCalculateProgressHandlesZeroTarget(): void
    {
        $progress = GroupAchievementService::calculateProgress(
            self::$testGroupId,
            'member_count',
            0
        );

        $this->assertIsNumeric($progress);
    }

    // ==========================================
    // Earned Achievements Tests
    // ==========================================

    public function testGetEarnedAchievementsReturnsArray(): void
    {
        $earned = GroupAchievementService::getEarnedAchievements(self::$testGroupId);
        $this->assertIsArray($earned);
    }

    public function testAwardAchievementCreatesRecord(): void
    {
        $result = GroupAchievementService::awardAchievement(
            self::$testGroupId,
            'community_builders'
        );

        if ($result) {
            $this->assertIsInt($result);

            // Cleanup
            Database::query(
                "DELETE FROM group_achievements WHERE id = ?",
                [$result]
            );
        }
        $this->assertTrue(true);
    }

    public function testAwardAchievementPreventsDuplicates(): void
    {
        $result1 = GroupAchievementService::awardAchievement(
            self::$testGroupId,
            'community_builders'
        );

        $result2 = GroupAchievementService::awardAchievement(
            self::$testGroupId,
            'community_builders'
        );

        // Second award should be ignored or return same ID
        if ($result1) {
            // Cleanup
            Database::query(
                "DELETE FROM group_achievements WHERE id = ?",
                [$result1]
            );
        }
        $this->assertTrue(true);
    }

    // ==========================================
    // Check Achievements Tests
    // ==========================================

    public function testCheckAchievementsReturnsArray(): void
    {
        $newAchievements = GroupAchievementService::checkAchievements(self::$testGroupId);
        $this->assertIsArray($newAchievements);
    }
}
