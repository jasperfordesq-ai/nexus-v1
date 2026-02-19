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
use Nexus\Models\Connection;

/**
 * Connection Model Tests
 *
 * Tests connection requests, accept/reject, bidirectional lookup,
 * friends listing, pending requests, and removal.
 */
class ConnectionTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testUser3Id = null;

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

        // Create 3 test users for various connection scenarios
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "conn_model_test1_{$timestamp}@test.com",
                "conn_model_test1_{$timestamp}",
                'Alice',
                'Connection',
                'Alice Connection',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "conn_model_test2_{$timestamp}@test.com",
                "conn_model_test2_{$timestamp}",
                'Bob',
                'Connection',
                'Bob Connection',
                50
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "conn_model_test3_{$timestamp}@test.com",
                "conn_model_test3_{$timestamp}",
                'Charlie',
                'Connection',
                'Charlie Connection',
                75
            ]
        );
        self::$testUser3Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = [self::$testUserId, self::$testUser2Id, self::$testUser3Id];

        foreach ($userIds as $uid) {
            if ($uid) {
                try {
                    Database::query(
                        "DELETE FROM connections WHERE requester_id = ? OR receiver_id = ?",
                        [$uid, $uid]
                    );
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

        // Clean up connections between test users before each test
        try {
            Database::query(
                "DELETE FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)",
                [self::$testUserId, self::$testUser2Id, self::$testUser2Id, self::$testUserId]
            );
            Database::query(
                "DELETE FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)",
                [self::$testUserId, self::$testUser3Id, self::$testUser3Id, self::$testUserId]
            );
        } catch (\Exception $e) {}
    }

    // ==========================================
    // Send Request Tests
    // ==========================================

    public function testSendRequestCreatesConnection(): void
    {
        $result = Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $this->assertTrue($result);

        // Verify in database
        $conn = Database::query(
            "SELECT * FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        $this->assertNotFalse($conn);
        $this->assertEquals('pending', $conn['status']);
    }

    public function testSendRequestPreventsDuplicates(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        // Try sending again
        $result = Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $this->assertFalse($result, 'Duplicate connection request should be rejected');
    }

    public function testSendRequestDetectsBidirectionalDuplicate(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        // Try the reverse direction
        $result = Connection::sendRequest(self::$testUser2Id, self::$testUserId);

        $this->assertFalse($result, 'Reverse connection request should be rejected');
    }

    // ==========================================
    // Accept Request Tests
    // ==========================================

    public function testAcceptRequestChangesStatus(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        // Get the connection ID
        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        $result = Connection::acceptRequest((int)$conn['id'], self::$testUser2Id);

        $this->assertTrue($result);

        // Verify status
        $updated = Database::query(
            "SELECT status FROM connections WHERE id = ?",
            [$conn['id']]
        )->fetch();

        $this->assertEquals('accepted', $updated['status']);
    }

    public function testAcceptRequestVerifiesReceiver(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        // Try accepting as the requester (not the receiver) -- should fail
        $result = Connection::acceptRequest((int)$conn['id'], self::$testUserId);

        $this->assertFalse($result, 'Only the receiver should be able to accept');
    }

    public function testAcceptRequestByWrongUser(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        // Try accepting as a third party
        $result = Connection::acceptRequest((int)$conn['id'], self::$testUser3Id);

        $this->assertFalse($result, 'Third party should not be able to accept request');
    }

    // ==========================================
    // GetStatus Tests (Bidirectional)
    // ==========================================

    public function testGetStatusReturnsPending(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $status = Connection::getStatus(self::$testUserId, self::$testUser2Id);

        $this->assertNotFalse($status);
        $this->assertEquals('pending', $status['status']);
    }

    public function testGetStatusIsBidirectional(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        // Check from both directions
        $status1 = Connection::getStatus(self::$testUserId, self::$testUser2Id);
        $status2 = Connection::getStatus(self::$testUser2Id, self::$testUserId);

        $this->assertNotFalse($status1);
        $this->assertNotFalse($status2);
        $this->assertEquals($status1['id'], $status2['id'], 'Both directions should return the same connection');
    }

    public function testGetStatusReturnsNullForNoConnection(): void
    {
        $status = Connection::getStatus(self::$testUserId, self::$testUser3Id);

        $this->assertFalse($status);
    }

    // ==========================================
    // GetPending Tests
    // ==========================================

    public function testGetPendingReturnsIncomingRequests(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $pending = Connection::getPending(self::$testUser2Id);

        $this->assertIsArray($pending);
        $this->assertGreaterThanOrEqual(1, count($pending));

        // Should include our request
        $found = false;
        foreach ($pending as $request) {
            if ((int)$request['requester_id'] === self::$testUserId) {
                $found = true;
                $this->assertArrayHasKey('requester_name', $request);
                $this->assertArrayHasKey('avatar_url', $request);
                break;
            }
        }
        $this->assertTrue($found, 'Pending requests should include our test request');
    }

    public function testGetPendingExcludesAcceptedConnections(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        Connection::acceptRequest((int)$conn['id'], self::$testUser2Id);

        $pending = Connection::getPending(self::$testUser2Id);

        // Accepted connection should not appear in pending
        $found = false;
        foreach ($pending as $request) {
            if ((int)$request['requester_id'] === self::$testUserId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Accepted connections should not appear in pending');
    }

    // ==========================================
    // GetFriends Tests
    // ==========================================

    public function testGetFriendsReturnsAcceptedConnections(): void
    {
        // Create and accept a connection
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        Connection::acceptRequest((int)$conn['id'], self::$testUser2Id);

        $friends = Connection::getFriends(self::$testUserId);

        $this->assertIsArray($friends);
        $this->assertGreaterThanOrEqual(1, count($friends));

        // Should include user2
        $found = false;
        foreach ($friends as $friend) {
            if ((int)$friend['id'] === self::$testUser2Id) {
                $found = true;
                $this->assertArrayHasKey('name', $friend);
                $this->assertArrayHasKey('avatar_url', $friend);
                break;
            }
        }
        $this->assertTrue($found, 'Friends list should include accepted connection');
    }

    public function testGetFriendsIsBidirectional(): void
    {
        // Create and accept a connection
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        Connection::acceptRequest((int)$conn['id'], self::$testUser2Id);

        // Both users should see each other as friends
        $user1Friends = Connection::getFriends(self::$testUserId);
        $user2Friends = Connection::getFriends(self::$testUser2Id);

        $user1HasUser2 = false;
        foreach ($user1Friends as $f) {
            if ((int)$f['id'] === self::$testUser2Id) {
                $user1HasUser2 = true;
                break;
            }
        }

        $user2HasUser1 = false;
        foreach ($user2Friends as $f) {
            if ((int)$f['id'] === self::$testUserId) {
                $user2HasUser1 = true;
                break;
            }
        }

        $this->assertTrue($user1HasUser2, 'User1 should see User2 as friend');
        $this->assertTrue($user2HasUser1, 'User2 should see User1 as friend');
    }

    public function testGetFriendsExcludesPendingConnections(): void
    {
        // Only create the request, don't accept
        Connection::sendRequest(self::$testUserId, self::$testUser3Id);

        $friends = Connection::getFriends(self::$testUserId);

        $found = false;
        foreach ($friends as $friend) {
            if ((int)$friend['id'] === self::$testUser3Id) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Pending connections should not appear in friends list');
    }

    // ==========================================
    // Remove Connection Tests
    // ==========================================

    public function testRemoveConnectionDeletesRecord(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        $result = Connection::removeConnection((int)$conn['id'], self::$testUserId);

        $this->assertTrue($result);

        // Verify it is gone
        $remaining = Database::query(
            "SELECT * FROM connections WHERE id = ?",
            [$conn['id']]
        )->fetch();

        $this->assertFalse($remaining, 'Connection should be removed from database');
    }

    public function testRemoveConnectionVerifiesUserIsParticipant(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        // Try to remove as a third party
        $result = Connection::removeConnection((int)$conn['id'], self::$testUser3Id);

        $this->assertFalse($result, 'Third party should not be able to remove connection');
    }

    public function testRemoveConnectionAllowsRequesterToRemove(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        $result = Connection::removeConnection((int)$conn['id'], self::$testUserId);
        $this->assertTrue($result, 'Requester should be able to remove connection');
    }

    public function testRemoveConnectionAllowsReceiverToRemove(): void
    {
        Connection::sendRequest(self::$testUserId, self::$testUser2Id);

        $conn = Database::query(
            "SELECT id FROM connections WHERE requester_id = ? AND receiver_id = ?",
            [self::$testUserId, self::$testUser2Id]
        )->fetch();

        $result = Connection::removeConnection((int)$conn['id'], self::$testUser2Id);
        $this->assertTrue($result, 'Receiver should be able to remove connection');
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testGetFriendsReturnsEmptyForUserWithNoConnections(): void
    {
        $friends = Connection::getFriends(self::$testUser3Id);

        $this->assertIsArray($friends);
    }

    public function testGetPendingReturnsEmptyForUserWithNoPendingRequests(): void
    {
        $pending = Connection::getPending(self::$testUser3Id);

        $this->assertIsArray($pending);
    }

    public function testGetStatusForNonExistentUsers(): void
    {
        $status = Connection::getStatus(999999, 999998);

        $this->assertFalse($status);
    }
}
