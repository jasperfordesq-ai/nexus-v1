<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Goal;

/**
 * Goal Model Tests
 *
 * Tests goal creation, retrieval, update, status changes,
 * mentor assignment, public/private goals, and deletion.
 */
class GoalTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUserId2 = null;

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

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "goal_test1_{$timestamp}@test.com", "goal_test1_{$timestamp}", 'Goal', 'Tester1', 'Goal Tester1']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "goal_test2_{$timestamp}@test.com", "goal_test2_{$timestamp}", 'Goal', 'Tester2', 'Goal Tester2']
        );
        self::$testUserId2 = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testUserId2]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM goals WHERE user_id = ?", [$uid]);
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        try {
            Database::query("DELETE FROM goals WHERE user_id IN (?, ?)", [self::$testUserId, self::$testUserId2]);
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Test Goal', 'A test goal', '2026-12-31', 1);

        $this->assertNotEmpty($id);
        $this->assertIsNumeric($id);
    }

    public function testCreateSetsDefaultStatus(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Status Goal', 'Check status', null, 0);

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertNotFalse($goal);
        $this->assertEquals('active', $goal['status']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsGoalWithUserInfo(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Find Goal', 'Description', null, 1);

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertNotFalse($goal);
        $this->assertEquals('Find Goal', $goal['title']);
        $this->assertArrayHasKey('author_name', $goal);
    }

    public function testFindReturnsNullishForNonExistent(): void
    {
        $goal = Goal::find(999999999, self::$testTenantId);
        $this->assertEmpty($goal);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Original', 'Desc', null, 0);

        Goal::update($id, 'Updated Title', 'Updated Desc', '2026-06-30', 1);

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertEquals('Updated Title', $goal['title']);
        $this->assertEquals('Updated Desc', $goal['description']);
        $this->assertEquals(1, (int)$goal['is_public']);
    }

    // ==========================================
    // Status Tests
    // ==========================================

    public function testSetStatusChangesGoalStatus(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Status Test', 'Desc', null, 0);

        Goal::setStatus($id, 'completed');

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertEquals('completed', $goal['status']);
    }

    // ==========================================
    // Mentor Tests
    // ==========================================

    public function testSetMentorAssignsMentor(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Mentor Goal', 'Desc', null, 1);

        Goal::setMentor($id, self::$testUserId2);

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertEquals(self::$testUserId2, (int)$goal['mentor_id']);
        $this->assertArrayHasKey('mentor_name', $goal);
    }

    // ==========================================
    // List Tests
    // ==========================================

    public function testMyGoalsReturnsArray(): void
    {
        Goal::create(self::$testTenantId, self::$testUserId, 'My Goal 1', 'Desc', null, 0);
        Goal::create(self::$testTenantId, self::$testUserId, 'My Goal 2', 'Desc', null, 1);

        $goals = Goal::myGoals(self::$testUserId, self::$testTenantId);
        $this->assertIsArray($goals);
        $this->assertGreaterThanOrEqual(2, count($goals));
    }

    public function testAllPublicReturnsOnlyPublicActiveGoals(): void
    {
        Goal::create(self::$testTenantId, self::$testUserId, 'Public Goal', 'Desc', null, 1);
        Goal::create(self::$testTenantId, self::$testUserId, 'Private Goal', 'Desc', null, 0);

        $goals = Goal::allPublic(self::$testTenantId);
        $this->assertIsArray($goals);

        foreach ($goals as $goal) {
            $this->assertEquals(1, (int)$goal['is_public']);
            $this->assertEquals('active', $goal['status']);
        }
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesGoal(): void
    {
        $id = Goal::create(self::$testTenantId, self::$testUserId, 'Delete Me', 'Desc', null, 0);

        Goal::delete($id);

        $goal = Goal::find($id, self::$testTenantId);
        $this->assertEmpty($goal);
    }
}
