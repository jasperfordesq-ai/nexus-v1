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
use Nexus\Models\GroupFeedback;

/**
 * GroupFeedback Model Tests
 *
 * Tests feedback submission, retrieval, average rating,
 * rating breakdown, user feedback check, and deletion.
 */
class GroupFeedbackTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testGroupId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "grp_fb_test_{$timestamp}@test.com", "grp_fb_test_{$timestamp}", 'GrpFb', 'Tester', 'GrpFb Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test group (groups table uses owner_id, not user_id, and has no slug column)
        Database::query(
            "INSERT INTO `groups` (tenant_id, name, description, owner_id, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [self::$testTenantId, "Feedback Test Group {$timestamp}", 'Group for feedback tests', self::$testUserId]
        );
        self::$testGroupId = (int)Database::getInstance()->lastInsertId();

        // Ensure table exists
        GroupFeedback::ensureTable();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testGroupId) {
                Database::query("DELETE FROM group_feedback WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Submit Tests
    // ==========================================

    public function testSubmitCreatesFeedback(): void
    {
        GroupFeedback::submit(self::$testGroupId, self::$testUserId, 5, 'Great group!');

        $feedback = GroupFeedback::getUserFeedback(self::$testGroupId, self::$testUserId);
        $this->assertNotFalse($feedback);
        $this->assertEquals(5, (int)$feedback['rating']);
        $this->assertEquals('Great group!', $feedback['comment']);
    }

    public function testSubmitUpdatesExistingFeedback(): void
    {
        GroupFeedback::submit(self::$testGroupId, self::$testUserId, 4, 'Updated review');

        $feedback = GroupFeedback::getUserFeedback(self::$testGroupId, self::$testUserId);
        $this->assertEquals(4, (int)$feedback['rating']);
        $this->assertEquals('Updated review', $feedback['comment']);
    }

    // ==========================================
    // GetForGroup Tests
    // ==========================================

    public function testGetForGroupReturnsArray(): void
    {
        $feedback = GroupFeedback::getForGroup(self::$testGroupId);
        $this->assertIsArray($feedback);
    }

    public function testGetForGroupIncludesUserInfo(): void
    {
        GroupFeedback::submit(self::$testGroupId, self::$testUserId, 5, 'Feedback with user info');

        $feedback = GroupFeedback::getForGroup(self::$testGroupId);
        if (!empty($feedback)) {
            $this->assertArrayHasKey('user_name', $feedback[0]);
            $this->assertArrayHasKey('avatar_url', $feedback[0]);
        }
    }

    public function testGetForGroupReturnsEmptyForNonExistent(): void
    {
        $feedback = GroupFeedback::getForGroup(999999999);
        $this->assertIsArray($feedback);
        $this->assertEmpty($feedback);
    }

    // ==========================================
    // GetAverageRating Tests
    // ==========================================

    public function testGetAverageRatingReturnsStructure(): void
    {
        $result = GroupFeedback::getAverageRating(self::$testGroupId);
        $this->assertNotFalse($result);
        $this->assertArrayHasKey('avg_rating', $result);
        $this->assertArrayHasKey('total_count', $result);
    }

    // ==========================================
    // GetRatingBreakdown Tests
    // ==========================================

    public function testGetRatingBreakdownReturnsAllStars(): void
    {
        $breakdown = GroupFeedback::getRatingBreakdown(self::$testGroupId);
        $this->assertIsArray($breakdown);
        $this->assertArrayHasKey(5, $breakdown);
        $this->assertArrayHasKey(4, $breakdown);
        $this->assertArrayHasKey(3, $breakdown);
        $this->assertArrayHasKey(2, $breakdown);
        $this->assertArrayHasKey(1, $breakdown);
    }

    // ==========================================
    // GetUserFeedback Tests
    // ==========================================

    public function testGetUserFeedbackReturnsFalseForNonExistent(): void
    {
        $feedback = GroupFeedback::getUserFeedback(self::$testGroupId, 999999999);
        $this->assertFalse($feedback);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesFeedback(): void
    {
        GroupFeedback::submit(self::$testGroupId, self::$testUserId, 3, 'To be deleted');
        $feedback = GroupFeedback::getUserFeedback(self::$testGroupId, self::$testUserId);
        $feedbackId = $feedback['id'];

        GroupFeedback::delete($feedbackId, self::$testGroupId);

        $deleted = GroupFeedback::getUserFeedback(self::$testGroupId, self::$testUserId);
        $this->assertFalse($deleted);
    }
}
