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
use Nexus\Services\GoalService;

/**
 * GoalService Tests
 *
 * Tests goal CRUD, progress tracking, buddy system, and validation.
 */
class GoalServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testGoalId = null;
    protected static bool $goalsTableExists = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        try {
            Database::query("SELECT 1 FROM goals LIMIT 1");
        } catch (\Exception $e) {
            self::$goalsTableExists = false;
            return;
        }

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$testTenantId, "goal_user1_{$timestamp}@test.com", "goal_user1_{$timestamp}", 'Goal', 'Owner', 'Goal Owner', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$testTenantId, "goal_user2_{$timestamp}@test.com", "goal_user2_{$timestamp}", 'Goal', 'Buddy', 'Goal Buddy', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$goalsTableExists) {
            try {
                if (self::$testUserId) {
                    Database::query("DELETE FROM goals WHERE user_id = ? AND tenant_id = ?", [self::$testUserId, self::$testTenantId]);
                }
                if (self::$testUser2Id) {
                    Database::query("DELETE FROM goals WHERE user_id = ? AND tenant_id = ?", [self::$testUser2Id, self::$testTenantId]);
                    Database::query("DELETE FROM goals WHERE mentor_id = ?", [self::$testUser2Id]);
                }
            } catch (\Exception $e) {}
        }

        if (self::$testUserId) {
            try { Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]); } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try { Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUser2Id]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]); } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateGoalSucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Test Goal Creation',
            'description' => 'A test goal for unit tests',
            'target_value' => 100,
            'is_public' => true,
        ]);

        $this->assertNotNull($goalId);
        $this->assertIsInt($goalId);
        $this->assertGreaterThan(0, $goalId);

        // Clean up
        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testCreateGoalWithEmptyTitleFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => '',
            'target_value' => 10,
        ]);

        $this->assertNull($goalId);
        $errors = GoalService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('title', $errors[0]['field'] ?? null);
    }

    public function testCreateGoalWithTitleTooLongFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => str_repeat('A', 256),
            'target_value' => 10,
        ]);

        $this->assertNull($goalId);
        $errors = GoalService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateGoalWithNegativeTargetValueFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Negative Target Goal',
            'target_value' => -5,
        ]);

        $this->assertNull($goalId);
        $errors = GoalService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateGoalWithZeroTargetValueSucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Zero Target Goal',
            'target_value' => 0,
        ]);

        $this->assertNotNull($goalId);
        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    // ==========================================
    // Get Tests
    // ==========================================

    public function testGetByIdReturnsGoal(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Get By ID Test',
            'description' => 'Testing retrieval',
            'target_value' => 50,
            'is_public' => true,
        ]);

        $this->assertNotNull($goalId);

        $goal = GoalService::getById($goalId);

        $this->assertNotNull($goal);
        $this->assertEquals('Get By ID Test', $goal['title']);
        $this->assertArrayHasKey('progress_percentage', $goal);
        $this->assertArrayHasKey('owner', $goal);
        $this->assertArrayHasKey('mentor', $goal);
        $this->assertTrue($goal['is_public']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goal = GoalService::getById(999999);
        $this->assertNull($goal);
    }

    public function testGetAllReturnsPaginatedResults(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        // Create a test goal
        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Pagination Test Goal',
            'target_value' => 10,
            'is_public' => true,
        ]);

        $result = GoalService::getAll(['user_id' => self::$testUserId, 'limit' => 5]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsBool($result['has_more']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testGetAllWithStatusFilter(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Active Filter Test',
            'target_value' => 100,
        ]);

        $result = GoalService::getAll([
            'user_id' => self::$testUserId,
            'status' => 'active',
        ]);

        $this->assertIsArray($result['items']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testGetAllWithVisibilityFilter(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Public Visibility Test',
            'target_value' => 10,
            'is_public' => true,
        ]);

        $result = GoalService::getAll([
            'user_id' => self::$testUserId,
            'visibility' => 'public',
        ]);

        $this->assertIsArray($result['items']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateGoalSucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Before Update',
            'target_value' => 10,
        ]);

        $result = GoalService::update($goalId, self::$testUserId, [
            'title' => 'After Update',
            'description' => 'Updated description',
        ]);

        $this->assertTrue($result);

        $goal = GoalService::getById($goalId);
        $this->assertEquals('After Update', $goal['title']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateGoalByNonOwnerFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Ownership Test',
            'target_value' => 10,
        ]);

        $result = GoalService::update($goalId, self::$testUser2Id, [
            'title' => 'Hacked Title',
        ]);

        $this->assertFalse($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateNonExistentGoalFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $result = GoalService::update(999999, self::$testUserId, [
            'title' => 'Ghost Goal',
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateGoalWithEmptyTitleFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Original Title',
            'target_value' => 10,
        ]);

        $result = GoalService::update($goalId, self::$testUserId, [
            'title' => '',
        ]);

        $this->assertFalse($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateGoalWithNoChangeSucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'No Change Goal',
            'target_value' => 10,
        ]);

        $result = GoalService::update($goalId, self::$testUserId, []);
        $this->assertTrue($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    // ==========================================
    // Progress Tests
    // ==========================================

    public function testUpdateProgressIncrementsValue(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Progress Test',
            'target_value' => 100,
        ]);

        $updated = GoalService::updateProgress($goalId, self::$testUserId, 25);

        $this->assertNotNull($updated);
        $this->assertEquals(25, (float)$updated['current_value']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateProgressCompletesGoal(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Completion Test',
            'target_value' => 10,
        ]);

        $updated = GoalService::updateProgress($goalId, self::$testUserId, 10);

        $this->assertNotNull($updated);
        $this->assertEquals('completed', $updated['status']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateProgressByNonOwnerFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Non-Owner Progress',
            'target_value' => 100,
        ]);

        $result = GoalService::updateProgress($goalId, self::$testUser2Id, 5);
        $this->assertNull($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateProgressOnCompletedGoalFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Already Complete',
            'target_value' => 10,
        ]);

        // Complete it
        GoalService::updateProgress($goalId, self::$testUserId, 10);

        // Try to update again
        $result = GoalService::updateProgress($goalId, self::$testUserId, 5);
        $this->assertNull($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testUpdateProgressNeverGoesBelowZero(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Floor Test',
            'target_value' => 100,
        ]);

        $updated = GoalService::updateProgress($goalId, self::$testUserId, -50);

        $this->assertNotNull($updated);
        $this->assertGreaterThanOrEqual(0, (float)$updated['current_value']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    // ==========================================
    // Buddy Tests
    // ==========================================

    public function testOfferBuddySucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Buddy Test Goal',
            'target_value' => 50,
            'is_public' => true,
        ]);

        $result = GoalService::offerBuddy($goalId, self::$testUser2Id);
        $this->assertTrue($result);

        $goal = GoalService::getById($goalId);
        $this->assertNotNull($goal['mentor']);
        $this->assertEquals(self::$testUser2Id, $goal['mentor']['id']);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testOfferBuddyOwnGoalFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Self Buddy Fail',
            'target_value' => 50,
            'is_public' => true,
        ]);

        $result = GoalService::offerBuddy($goalId, self::$testUserId);
        $this->assertFalse($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testOfferBuddyPrivateGoalFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Private Goal',
            'target_value' => 50,
            'is_public' => false,
        ]);

        $result = GoalService::offerBuddy($goalId, self::$testUser2Id);
        $this->assertFalse($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteGoalByOwnerSucceeds(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Delete Me',
            'target_value' => 10,
        ]);

        $result = GoalService::delete($goalId, self::$testUserId);
        $this->assertTrue($result);

        $goal = GoalService::getById($goalId);
        $this->assertNull($goal);
    }

    public function testDeleteGoalByNonOwnerFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Cannot Delete',
            'target_value' => 10,
        ]);

        $result = GoalService::delete($goalId, self::$testUser2Id);
        $this->assertFalse($result);

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testDeleteNonExistentGoalFails(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $result = GoalService::delete(999999, self::$testUserId);
        $this->assertFalse($result);
    }

    // ==========================================
    // Public Buddy Listing Tests
    // ==========================================

    public function testGetPublicForBuddyExcludesOwnGoals(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        $goalId = GoalService::create(self::$testUserId, [
            'title' => 'Public Buddy Listing',
            'target_value' => 50,
            'is_public' => true,
        ]);

        $result = GoalService::getPublicForBuddy(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);

        // None of the returned items should belong to the excluded user
        foreach ($result['items'] as $item) {
            $this->assertNotEquals(self::$testUserId, $item['user_id']);
        }

        Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
    }

    public function testGetErrorsClearsOnNewCall(): void
    {
        if (!self::$goalsTableExists) {
            $this->markTestSkipped('goals table does not exist');
        }

        // Trigger an error
        GoalService::create(self::$testUserId, ['title' => '', 'target_value' => 10]);
        $this->assertNotEmpty(GoalService::getErrors());

        // New successful call should clear errors
        $goalId = GoalService::create(self::$testUserId, ['title' => 'Valid Goal', 'target_value' => 10]);
        $this->assertEmpty(GoalService::getErrors());

        if ($goalId) {
            Database::query("DELETE FROM goals WHERE id = ?", [$goalId]);
        }
    }
}
