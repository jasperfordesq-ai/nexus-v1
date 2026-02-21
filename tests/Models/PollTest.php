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
use Nexus\Models\Poll;

/**
 * Poll Model Tests
 *
 * Tests poll creation, option management, voting, result calculation,
 * updates, deletion, and tenant scoping.
 */
class PollTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testPollId = null;
    protected static ?int $testOptionId1 = null;
    protected static ?int $testOptionId2 = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "poll_model_test_{$timestamp}@test.com",
                "poll_model_test_{$timestamp}",
                'Poll',
                'Creator',
                'Poll Creator',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "poll_model_test2_{$timestamp}@test.com",
                "poll_model_test2_{$timestamp}",
                'Poll',
                'Voter',
                'Poll Voter',
                50
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create a test poll
        $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));
        self::$testPollId = (int)Poll::create(
            self::$testTenantId,
            self::$testUserId,
            "Test Poll Question {$timestamp}?",
            "This is a test poll description",
            $endDate
        );

        // Add options
        Poll::addOption(self::$testPollId, 'Option A');
        Poll::addOption(self::$testPollId, 'Option B');

        // Get option IDs
        $options = Poll::getOptions(self::$testPollId);
        if (count($options) >= 2) {
            self::$testOptionId1 = (int)$options[0]['id'];
            self::$testOptionId2 = (int)$options[1]['id'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testPollId) {
            try {
                Database::query("DELETE FROM poll_votes WHERE poll_id = ?", [self::$testPollId]);
                Database::query("DELETE FROM poll_options WHERE poll_id = ?", [self::$testPollId]);
                Database::query("DELETE FROM polls WHERE id = ?", [self::$testPollId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM poll_votes WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM poll_votes WHERE user_id = ?", [self::$testUser2Id]);
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

    public function testCreatePollReturnsId(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));

        $id = Poll::create(
            self::$testTenantId,
            self::$testUserId,
            'New poll question?',
            'Poll description',
            $endDate
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$id]);
        Database::query("DELETE FROM polls WHERE id = ?", [$id]);
    }

    public function testCreatePollStoresTenantId(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));

        $id = Poll::create(
            self::$testTenantId,
            self::$testUserId,
            'Tenant poll?',
            'Description',
            $endDate
        );

        $poll = Database::query("SELECT * FROM polls WHERE id = ?", [$id])->fetch();
        $this->assertEquals(self::$testTenantId, (int)$poll['tenant_id']);

        // Clean up
        Database::query("DELETE FROM polls WHERE id = ?", [$id]);
    }

    // ==========================================
    // Option Tests
    // ==========================================

    public function testAddOptionCreatesOption(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        $pollId = Poll::create(self::$testTenantId, self::$testUserId, 'Options test?', 'Desc', $endDate);

        Poll::addOption((int)$pollId, 'First Option');
        Poll::addOption((int)$pollId, 'Second Option');
        Poll::addOption((int)$pollId, 'Third Option');

        $options = Poll::getOptions((int)$pollId);

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        // Each option should have label and vote_count
        foreach ($options as $option) {
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('vote_count', $option);
            $this->assertEquals(0, (int)$option['vote_count']);
        }

        // Clean up
        Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$pollId]);
        Database::query("DELETE FROM polls WHERE id = ?", [$pollId]);
    }

    public function testGetOptionsReturnsVoteCounts(): void
    {
        $options = Poll::getOptions(self::$testPollId);

        $this->assertIsArray($options);
        $this->assertGreaterThanOrEqual(2, count($options));

        foreach ($options as $option) {
            $this->assertArrayHasKey('vote_count', $option);
            $this->assertIsNumeric($option['vote_count']);
        }
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsPoll(): void
    {
        $poll = Poll::find(self::$testPollId);

        $this->assertNotFalse($poll);
        $this->assertIsArray($poll);
        $this->assertEquals(self::$testPollId, (int)$poll['id']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $poll = Poll::find(999999999);

        $this->assertFalse($poll);
    }

    // ==========================================
    // All (List) Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $polls = Poll::all(self::$testTenantId);

        $this->assertIsArray($polls);
    }

    public function testAllScopesByTenant(): void
    {
        $polls = Poll::all(self::$testTenantId);

        foreach ($polls as $poll) {
            $this->assertEquals(self::$testTenantId, (int)$poll['tenant_id']);
        }
    }

    public function testAllIncludesCreatorName(): void
    {
        $polls = Poll::all(self::$testTenantId);

        foreach ($polls as $poll) {
            $this->assertArrayHasKey('creator_name', $poll);
        }
    }

    public function testAllIncludesTotalVotes(): void
    {
        $polls = Poll::all(self::$testTenantId);

        foreach ($polls as $poll) {
            $this->assertArrayHasKey('total_votes', $poll);
            $this->assertIsNumeric($poll['total_votes']);
        }
    }

    public function testAllIncludesStatus(): void
    {
        $polls = Poll::all(self::$testTenantId);

        foreach ($polls as $poll) {
            $this->assertArrayHasKey('status', $poll);
            $this->assertContains($poll['status'], ['open', 'closed']);
        }
    }

    // ==========================================
    // Voting Tests
    // ==========================================

    public function testHasVotedReturnsFalseBeforeVoting(): void
    {
        $hasVoted = Poll::hasVoted(self::$testPollId, self::$testUser2Id);

        $this->assertFalse($hasVoted);
    }

    public function testCastVoteSucceeds(): void
    {
        if (!self::$testOptionId1) {
            $this->markTestSkipped('No test option available');
        }

        $result = Poll::castVote(self::$testPollId, self::$testOptionId1, self::$testUser2Id);

        $this->assertTrue($result, 'First vote should succeed');
    }

    public function testHasVotedReturnsTrueAfterVoting(): void
    {
        // Ensure user2 has voted (from previous test or set up here)
        if (!Poll::hasVoted(self::$testPollId, self::$testUser2Id)) {
            Poll::castVote(self::$testPollId, self::$testOptionId1, self::$testUser2Id);
        }

        $hasVoted = Poll::hasVoted(self::$testPollId, self::$testUser2Id);

        $this->assertTrue($hasVoted);
    }

    public function testCastVotePreventsDuplicateVote(): void
    {
        // Ensure user2 has voted
        if (!Poll::hasVoted(self::$testPollId, self::$testUser2Id)) {
            Poll::castVote(self::$testPollId, self::$testOptionId1, self::$testUser2Id);
        }

        // Try to vote again
        $result = Poll::castVote(self::$testPollId, self::$testOptionId2, self::$testUser2Id);

        $this->assertFalse($result, 'Duplicate vote should be rejected');
    }

    public function testVoteCountIncrementsCorrectly(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        $pollId = Poll::create(self::$testTenantId, self::$testUserId, 'Vote count test?', 'Desc', $endDate);

        Poll::addOption((int)$pollId, 'Yes');
        Poll::addOption((int)$pollId, 'No');

        $options = Poll::getOptions((int)$pollId);
        $yesOptionId = (int)$options[0]['id'];

        // Cast votes
        Poll::castVote((int)$pollId, $yesOptionId, self::$testUserId);
        Poll::castVote((int)$pollId, $yesOptionId, self::$testUser2Id);

        // Check counts
        $updatedOptions = Poll::getOptions((int)$pollId);
        $yesOption = null;
        foreach ($updatedOptions as $opt) {
            if ((int)$opt['id'] === $yesOptionId) {
                $yesOption = $opt;
                break;
            }
        }

        $this->assertNotNull($yesOption);
        $this->assertEquals(2, (int)$yesOption['vote_count']);

        // Clean up
        Database::query("DELETE FROM poll_votes WHERE poll_id = ?", [$pollId]);
        Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$pollId]);
        Database::query("DELETE FROM polls WHERE id = ?", [$pollId]);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $newQuestion = 'Updated question ' . time() . '?';
        $newDesc = 'Updated description';
        $newEnd = date('Y-m-d H:i:s', strtotime('+60 days'));

        Poll::update(self::$testPollId, $newQuestion, $newDesc, $newEnd);

        $poll = Poll::find(self::$testPollId);

        $this->assertEquals($newQuestion, $poll['question']);
        $this->assertEquals($newDesc, $poll['description']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesPoll(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        $pollId = Poll::create(self::$testTenantId, self::$testUserId, 'To be deleted?', 'Desc', $endDate);

        Poll::delete((int)$pollId);

        $poll = Poll::find((int)$pollId);
        $this->assertFalse($poll, 'Deleted poll should not be found');
    }

    public function testDeleteEnforcesTenantScoping(): void
    {
        $endDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        $pollId = Poll::create(self::$testTenantId, self::$testUserId, 'Tenant delete test?', 'Desc', $endDate);

        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        // Attempt delete from wrong tenant (poll belongs to tenant 2)
        Poll::delete((int)$pollId);

        // Switch back
        TenantContext::setById(self::$testTenantId);

        // Poll should still exist (delete was scoped to tenant 1, not tenant 2)
        $poll = Poll::find((int)$pollId);
        $this->assertNotFalse($poll, 'Poll should survive delete attempt from wrong tenant');

        // Clean up
        Poll::delete((int)$pollId);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testAllReturnsEmptyForNonExistentTenant(): void
    {
        $polls = Poll::all(999999);

        $this->assertIsArray($polls);
        $this->assertEmpty($polls);
    }

    public function testGetOptionsForNonExistentPoll(): void
    {
        $options = Poll::getOptions(999999999);

        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    public function testHasVotedForNonExistentPoll(): void
    {
        $hasVoted = Poll::hasVoted(999999999, self::$testUserId);

        $this->assertFalse($hasVoted);
    }
}
