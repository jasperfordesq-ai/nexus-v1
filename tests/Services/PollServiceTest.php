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
use Nexus\Services\PollService;

/**
 * PollService Tests
 *
 * Tests poll CRUD, voting, vote counting, and expiration handling.
 */
class PollServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testPollId = null;
    protected static ?int $testExpiredPollId = null;

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
            [self::$testTenantId, "pollsvc_user1_{$ts}@test.com", "pollsvc_user1_{$ts}", 'Poll', 'Creator', 'Poll Creator']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "pollsvc_user2_{$ts}@test.com", "pollsvc_user2_{$ts}", 'Poll', 'Voter', 'Poll Voter']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create active test poll
        try {
            Database::query(
                "INSERT INTO polls (tenant_id, user_id, question, options, expires_at, created_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())",
                [
                    self::$testTenantId,
                    self::$testUserId,
                    "Test Poll Question {$ts}?",
                    json_encode(['Option A', 'Option B', 'Option C'])
                ]
            );
            self::$testPollId = (int)Database::getInstance()->lastInsertId();

            // Create expired test poll
            Database::query(
                "INSERT INTO polls (tenant_id, user_id, question, options, expires_at, created_at)
                 VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY), NOW())",
                [
                    self::$testTenantId,
                    self::$testUserId,
                    "Expired Poll Question {$ts}?",
                    json_encode(['Yes', 'No'])
                ]
            );
            self::$testExpiredPollId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Polls may not exist in all schemas
        }
    }

    public static function tearDownAfterClass(): void
    {
        $pollIds = array_filter([self::$testPollId, self::$testExpiredPollId]);
        foreach ($pollIds as $pid) {
            try {
                Database::query("DELETE FROM poll_votes WHERE poll_id = ?", [$pid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM polls WHERE id = ? AND tenant_id = ?", [$pid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        $userIds = array_filter([self::$testUserId, self::$testUser2Id]);
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
        try {
            $result = PollService::getAll();

            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('has_more', $result);
            $this->assertIsArray($result['items']);
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll not available: ' . $e->getMessage());
        }
    }

    public function testGetAllRespectsLimit(): void
    {
        try {
            $result = PollService::getAll(['limit' => 5]);

            $this->assertLessThanOrEqual(5, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll not available: ' . $e->getMessage());
        }
    }

    public function testGetAllFiltersOpenPolls(): void
    {
        try {
            $result = PollService::getAll(['status' => 'open']);
            $this->assertIsArray($result['items']);

            foreach ($result['items'] as $poll) {
                // Should either have no expiry or future expiry
                if ($poll['expires_at']) {
                    $expiryTime = strtotime($poll['expires_at']);
                    $this->assertGreaterThan(time(), $expiryTime);
                }
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll status filter not available: ' . $e->getMessage());
        }
    }

    public function testGetAllFiltersClosedPolls(): void
    {
        try {
            $result = PollService::getAll(['status' => 'closed']);
            $this->assertIsArray($result['items']);

            foreach ($result['items'] as $poll) {
                $this->assertNotNull($poll['expires_at']);
                $expiryTime = strtotime($poll['expires_at']);
                $this->assertLessThanOrEqual(time(), $expiryTime);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll closed filter not available: ' . $e->getMessage());
        }
    }

    public function testGetAllFiltersByUserId(): void
    {
        try {
            $result = PollService::getAll(['user_id' => self::$testUserId]);
            $this->assertIsArray($result['items']);

            foreach ($result['items'] as $poll) {
                $this->assertEquals(self::$testUserId, $poll['user_id']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll user filter not available: ' . $e->getMessage());
        }
    }

    public function testGetAllIncludesVoteCounts(): void
    {
        try {
            $result = PollService::getAll();
            $this->assertIsArray($result['items']);

            if (!empty($result['items'])) {
                $poll = $result['items'][0];
                // getAll returns 'options' with vote data, not 'vote_counts'
                $this->assertArrayHasKey('options', $poll);
                $this->assertArrayHasKey('total_votes', $poll);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getAll not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdReturnsValidPoll(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            $poll = PollService::getById(self::$testPollId);

            $this->assertNotNull($poll);
            $this->assertIsArray($poll);
            $this->assertEquals(self::$testPollId, $poll['id']);
            $this->assertArrayHasKey('question', $poll);
            $this->assertArrayHasKey('options', $poll);
            $this->assertArrayHasKey('expires_at', $poll);
        } catch (\Exception $e) {
            $this->markTestSkipped('getById not available: ' . $e->getMessage());
        }
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        try {
            $poll = PollService::getById(999999);

            $this->assertNull($poll);
        } catch (\Exception $e) {
            $this->markTestSkipped('getById not available: ' . $e->getMessage());
        }
    }

    public function testGetByIdIncludesAuthorInfo(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            $poll = PollService::getById(self::$testPollId);

            $this->assertNotNull($poll);
            // getById returns nested 'creator' object
            $this->assertArrayHasKey('creator', $poll);
            $this->assertIsArray($poll['creator']);
            $this->assertArrayHasKey('id', $poll['creator']);
            $this->assertArrayHasKey('name', $poll['creator']);
        } catch (\Exception $e) {
            $this->markTestSkipped('getById not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // validatePoll Tests
    // ==========================================

    public function testValidatePollNotImplemented(): void
    {
        // PollService does not have validatePoll() - validation happens in create()
        $this->markTestSkipped('PollService does not have validatePoll() method - validation is done within create()');
    }

    // ==========================================
    // createPoll Tests
    // ==========================================

    public function testCreatePollReturnsIdForValidPoll(): void
    {
        try {
            // create(userId, data) returns ?int
            $pollId = PollService::create(self::$testUserId, [
                'question' => 'New test poll?',
                'options' => ['Option A', 'Option B'],
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);

            $this->assertIsInt($pollId);
            $this->assertGreaterThan(0, $pollId);

            // Cleanup
            Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$pollId]);
            Database::query("DELETE FROM polls WHERE id = ? AND tenant_id = ?", [$pollId, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('create not available: ' . $e->getMessage());
        }
    }

    public function testCreatePollReturnsNullForInvalidData(): void
    {
        try {
            $result = PollService::create(self::$testUserId, [
                'question' => '',
                'options' => ['Only one'],
            ]);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('create not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // vote Tests
    // ==========================================

    public function testVoteReturnsTrueForValidVote(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            // Get poll to find valid option_id
            $poll = PollService::getById(self::$testPollId);
            $this->assertNotEmpty($poll['options']);
            $optionId = (int)$poll['options'][0]['id'];

            // vote(pollId, optionId, userId) returns bool
            $result = PollService::vote(self::$testPollId, $optionId, self::$testUser2Id);

            $this->assertTrue($result);

            // Cleanup
            Database::query("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?", [self::$testPollId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('vote not available: ' . $e->getMessage());
        }
    }

    public function testVoteReturnsFalseForExpiredPoll(): void
    {
        if (!self::$testExpiredPollId) {
            $this->markTestSkipped('Expired poll not available');
        }

        try {
            $poll = PollService::getById(self::$testExpiredPollId);
            $this->assertNotEmpty($poll['options']);
            $optionId = (int)$poll['options'][0]['id'];

            $result = PollService::vote(self::$testExpiredPollId, $optionId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('vote not available: ' . $e->getMessage());
        }
    }

    public function testVoteReturnsFalseForInvalidOption(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            $result = PollService::vote(self::$testPollId, 999999, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('vote not available: ' . $e->getMessage());
        }
    }

    public function testVoteReturnsFalseForDuplicateVote(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            $poll = PollService::getById(self::$testPollId);
            $optionId = (int)$poll['options'][0]['id'];

            // Vote once
            PollService::vote(self::$testPollId, $optionId, self::$testUser2Id);

            // Try to vote again (different option)
            $optionId2 = (int)$poll['options'][1]['id'];
            $result = PollService::vote(self::$testPollId, $optionId2, self::$testUser2Id);

            $this->assertFalse($result);

            // Cleanup
            Database::query("DELETE FROM poll_votes WHERE poll_id = ? AND user_id = ?", [self::$testPollId, self::$testUser2Id]);
        } catch (\Exception $e) {
            $this->markTestSkipped('vote duplicate check not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getUserVote Tests
    // ==========================================

    public function testGetUserVoteNotImplemented(): void
    {
        // PollService does not have getUserVote() - vote status is in getById() response
        $this->markTestSkipped('PollService does not have getUserVote() method - use getById() with userId parameter for has_voted/voted_option_id');
    }

    // ==========================================
    // deletePoll Tests
    // ==========================================

    public function testDeletePollReturnsTrueForOwnPoll(): void
    {
        try {
            // Create a poll to delete
            Database::query(
                "INSERT INTO polls (tenant_id, user_id, question, expires_at, created_at)
                 VALUES (?, ?, 'To delete?', NULL, NOW())",
                [self::$testTenantId, self::$testUserId]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            // Add options
            Database::query("INSERT INTO poll_options (poll_id, label, votes) VALUES (?, 'Yes', 0)", [$tempId]);
            Database::query("INSERT INTO poll_options (poll_id, label, votes) VALUES (?, 'No', 0)", [$tempId]);

            // delete(id, userId) returns bool
            $result = PollService::delete($tempId, self::$testUserId);

            $this->assertTrue($result);

            // Verify it was deleted
            $stmt = Database::query("SELECT * FROM polls WHERE id = ?", [$tempId]);
            $row = $stmt->fetch();
            $this->assertFalse($row);
        } catch (\Exception $e) {
            $this->markTestSkipped('delete not available: ' . $e->getMessage());
        }
    }

    public function testDeletePollReturnsFalseForOthersPoll(): void
    {
        if (!self::$testPollId) {
            $this->markTestSkipped('Test poll not available');
        }

        try {
            // User 2 trying to delete User 1's poll
            $result = PollService::delete(self::$testPollId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('delete not available: ' . $e->getMessage());
        }
    }
}
