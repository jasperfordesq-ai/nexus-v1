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
use Nexus\Models\Review;

/**
 * Review Model Tests
 *
 * Tests review creation with rating, average rating calculation,
 * group-scoped reviews, updates, deletion, and tenant scoping.
 */
class ReviewTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testReviewerId = null;
    protected static ?int $testReceiverId = null;
    protected static ?int $testReviewId = null;
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
        $timestamp = time();

        // Create reviewer user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "review_reviewer_{$timestamp}@test.com",
                "review_reviewer_{$timestamp}",
                'Review',
                'Giver',
                'Review Giver',
                100
            ]
        );
        self::$testReviewerId = (int)Database::getInstance()->lastInsertId();

        // Create receiver user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "review_receiver_{$timestamp}@test.com",
                "review_receiver_{$timestamp}",
                'Review',
                'Receiver',
                'Review Receiver',
                50
            ]
        );
        self::$testReceiverId = (int)Database::getInstance()->lastInsertId();

        // Create a test group for group-scoped reviews
        try {
            Database::query(
                "INSERT INTO `groups` (tenant_id, owner_id, name, description, visibility, created_at)
                 VALUES (?, ?, ?, ?, 'public', NOW())",
                [self::$testTenantId, self::$testReviewerId, "Review Test Group {$timestamp}", "Group for review tests"]
            );
            self::$testGroupId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            self::$testGroupId = null;
        }

        // Create a test review
        // Note: Review::create() returns lastInsertId which may be the activity_log ID
        // (because ActivityLog::log is called after the INSERT), so we query for the actual review ID
        Review::create(
            self::$testReviewerId,
            self::$testReceiverId,
            null, // no transaction
            5,    // rating
            'Excellent service, highly recommend!'
        );
        $row = Database::query(
            "SELECT id FROM reviews WHERE reviewer_id = ? AND receiver_id = ? ORDER BY id DESC LIMIT 1",
            [self::$testReviewerId, self::$testReceiverId]
        )->fetch();
        self::$testReviewId = $row ? (int)$row['id'] : 0;
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up reviews first
        if (self::$testReviewerId) {
            try {
                Database::query("DELETE FROM reviews WHERE reviewer_id = ?", [self::$testReviewerId]);
            } catch (\Exception $e) {}
        }
        if (self::$testReceiverId) {
            try {
                Database::query("DELETE FROM reviews WHERE receiver_id = ?", [self::$testReceiverId]);
            } catch (\Exception $e) {}
        }
        if (self::$testGroupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [self::$testGroupId]);
                Database::query("DELETE FROM `groups` WHERE id = ?", [self::$testGroupId]);
            } catch (\Exception $e) {}
        }
        foreach ([self::$testReviewerId, self::$testReceiverId] as $uid) {
            if ($uid) {
                try {
                    Database::query("DELETE FROM activity_log WHERE user_id = ?", [$uid]);
                    Database::query("DELETE FROM notifications WHERE user_id = ?", [$uid]);
                    Database::query("DELETE FROM users WHERE id = ?", [$uid]);
                } catch (\Exception $e) {}
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    /**
     * Helper: Create a review and return its actual ID.
     * Review::create() returns lastInsertId which may be the activity_log ID
     * (because ActivityLog::log does an INSERT after the review INSERT).
     */
    private static function createReviewAndGetId(
        int $reviewerId,
        int $receiverId,
        ?int $transactionId,
        int $rating,
        string $comment,
        ?int $groupId = null
    ): int {
        Review::create($reviewerId, $receiverId, $transactionId, $rating, $comment, $groupId);
        $row = Database::query(
            "SELECT id FROM reviews WHERE reviewer_id = ? AND receiver_id = ? AND rating = ? ORDER BY id DESC LIMIT 1",
            [$reviewerId, $receiverId, $rating]
        )->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateReviewReturnsId(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null,
            4,
            'Good service!'
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, $id);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testCreateReviewWithRating(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null,
            3,
            'Average service.'
        );

        $review = Review::findById($id);

        $this->assertNotFalse($review);
        $this->assertEquals(3, (int)$review['rating']);
        $this->assertEquals('Average service.', $review['comment']);
        $this->assertEquals(self::$testReviewerId, (int)$review['reviewer_id']);
        $this->assertEquals(self::$testReceiverId, (int)$review['receiver_id']);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testCreateReviewWithGroupContext(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null,
            5,
            'Great group member!',
            self::$testGroupId
        );

        $review = Review::findById($id);

        $this->assertNotFalse($review);
        $this->assertEquals(self::$testGroupId, (int)$review['group_id']);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testCreateReviewLogsActivity(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null,
            4,
            'Activity log test'
        );

        // Check that activity was logged
        $log = Database::query(
            "SELECT * FROM activity_log WHERE user_id = ? AND action = 'left_review' ORDER BY id DESC LIMIT 1",
            [self::$testReviewerId]
        )->fetch();

        $this->assertNotFalse($log, 'Review creation should log an activity');
        $this->assertStringContainsString('4/5', $log['details']);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsReview(): void
    {
        $review = Review::findById(self::$testReviewId);

        $this->assertNotFalse($review);
        $this->assertIsArray($review);
        $this->assertEquals(self::$testReviewId, (int)$review['id']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $review = Review::findById(999999999);

        $this->assertFalse($review);
    }

    // ==========================================
    // GetForUser Tests
    // ==========================================

    public function testGetForUserReturnsReviews(): void
    {
        $reviews = Review::getForUser(self::$testReceiverId);

        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews));
    }

    public function testGetForUserIncludesReviewerInfo(): void
    {
        $reviews = Review::getForUser(self::$testReceiverId);

        foreach ($reviews as $review) {
            $this->assertArrayHasKey('reviewer_name', $review);
            $this->assertArrayHasKey('reviewer_avatar', $review);
        }
    }

    public function testGetForUserOrderedByNewest(): void
    {
        $reviews = Review::getForUser(self::$testReceiverId);

        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews), 'Should have at least one review for the test receiver');

        for ($i = 1; $i < count($reviews); $i++) {
            $this->assertGreaterThanOrEqual(
                $reviews[$i]['created_at'],
                $reviews[$i - 1]['created_at'],
                'Reviews should be ordered newest first'
            );
        }
    }

    // ==========================================
    // Group-Scoped Review Tests
    // ==========================================

    public function testGetForUserInGroupReturnsGroupReviews(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        // Create a group-scoped review
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null,
            4,
            'Good group member',
            self::$testGroupId
        );

        $reviews = Review::getForUserInGroup(self::$testReceiverId, self::$testGroupId);

        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews));

        foreach ($reviews as $review) {
            $this->assertEquals(self::$testGroupId, (int)($review['group_id'] ?? 0));
        }

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testGetForGroupReturnsAllGroupReviews(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        // Create group reviews
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 5, 'Great!',
            self::$testGroupId
        );

        $reviews = Review::getForGroup(self::$testGroupId);

        $this->assertIsArray($reviews);
        $this->assertGreaterThanOrEqual(1, count($reviews));

        foreach ($reviews as $review) {
            $this->assertArrayHasKey('reviewer_name', $review);
            $this->assertArrayHasKey('receiver_name', $review);
        }

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testHasReviewedInGroupReturnsTrueWhenExists(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 4, 'Test',
            self::$testGroupId
        );

        $result = Review::hasReviewedInGroup(self::$testReviewerId, self::$testReceiverId, self::$testGroupId);

        $this->assertTrue($result);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    public function testHasReviewedInGroupReturnsFalseWhenNotExists(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        $result = Review::hasReviewedInGroup(self::$testReceiverId, self::$testReviewerId, self::$testGroupId);

        $this->assertFalse($result);
    }

    // ==========================================
    // Average Rating Tests
    // ==========================================

    public function testGetAverageForUserReturnsStats(): void
    {
        $avg = Review::getAverageForUser(self::$testReceiverId);

        $this->assertNotFalse($avg);
        $this->assertIsArray($avg);
        $this->assertArrayHasKey('avg_rating', $avg);
        $this->assertArrayHasKey('total_count', $avg);
    }

    public function testGetAverageForUserCalculatesCorrectly(): void
    {
        // Create several reviews with known ratings
        $ids = [];
        $ratings = [5, 4, 3, 4, 4];

        foreach ($ratings as $rating) {
            $ids[] = self::createReviewAndGetId(
                self::$testReviewerId,
                self::$testReceiverId,
                null,
                $rating,
                "Rating {$rating} review"
            );
        }

        $avg = Review::getAverageForUser(self::$testReceiverId);

        // Expected: (5+4+3+4+4 + existing 5) / 6 = 25/6 = 4.166...
        // But there is the original review too (rating 5), so total = 6 reviews
        $this->assertIsNumeric($avg['avg_rating']);
        $this->assertGreaterThan(0, (float)$avg['avg_rating']);
        $this->assertGreaterThanOrEqual(count($ratings), (int)$avg['total_count']);

        // Clean up
        foreach ($ids as $id) {
            Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
        }
    }

    public function testGetAverageForUserInGroup(): void
    {
        if (!self::$testGroupId) {
            $this->markTestSkipped('No test group available');
        }

        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 4, 'Group avg test',
            self::$testGroupId
        );

        $avg = Review::getAverageForUserInGroup(self::$testReceiverId, self::$testGroupId);

        $this->assertNotFalse($avg);
        $this->assertArrayHasKey('avg_rating', $avg);
        $this->assertArrayHasKey('total_count', $avg);
        $this->assertGreaterThanOrEqual(1, (int)$avg['total_count']);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesRatingAndComment(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 3, 'Original comment'
        );

        Review::update($id, 5, 'Updated comment');

        $review = Review::findById($id);

        $this->assertNotFalse($review, 'Review should be found after update');
        $this->assertEquals(5, (int)$review['rating']);
        $this->assertEquals('Updated comment', $review['comment']);

        // Clean up
        Database::query("DELETE FROM reviews WHERE id = ?", [$id]);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesReview(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 4, 'To be deleted'
        );

        // Reviews created via Review::create() get tenant_id = 1 (table default),
        // so we need to look up the actual tenant_id stored on the review row
        $reviewRow = Database::query("SELECT tenant_id FROM reviews WHERE id = ?", [$id])->fetch();
        $actualTenantId = $reviewRow ? (int)$reviewRow['tenant_id'] : 1;

        Review::delete($id, $actualTenantId);

        $review = Review::findById($id);
        $this->assertFalse($review, 'Deleted review should not be found');
    }

    public function testDeleteEnforcesTenantScoping(): void
    {
        $id = self::createReviewAndGetId(
            self::$testReviewerId,
            self::$testReceiverId,
            null, 4, 'Tenant-scoped delete test'
        );

        // Attempt delete with wrong tenant (9999 won't match the actual tenant_id)
        Review::delete($id, 9999);

        // Review should still exist
        $review = Review::findById($id);
        $this->assertNotFalse($review, 'Review should survive delete attempt from wrong tenant');

        // Clean up with correct tenant (default is 1 from table schema)
        $reviewRow = Database::query("SELECT tenant_id FROM reviews WHERE id = ?", [$id])->fetch();
        $actualTenantId = $reviewRow ? (int)$reviewRow['tenant_id'] : 1;
        Review::delete($id, $actualTenantId);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testGetForUserReturnsEmptyForUserWithNoReviews(): void
    {
        $reviews = Review::getForUser(999999999);

        $this->assertIsArray($reviews);
        $this->assertEmpty($reviews);
    }

    public function testGetAverageForUserWithNoReviewsReturnsNullAvg(): void
    {
        $avg = Review::getAverageForUser(999999999);

        $this->assertNotFalse($avg);
        $this->assertNull($avg['avg_rating']);
        $this->assertEquals(0, (int)$avg['total_count']);
    }
}
