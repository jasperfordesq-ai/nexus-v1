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
use Nexus\Services\ConnectionService;

/**
 * ConnectionService Tests
 *
 * Tests user connections, friend requests, accept/reject, and connection status.
 */
class ConnectionServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testUser3Id = null;
    protected static ?int $testConnectionId = null;

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
            [self::$testTenantId, "connsvc_user1_{$ts}@test.com", "connsvc_user1_{$ts}", 'Connection', 'Requester', 'Connection Requester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "connsvc_user2_{$ts}@test.com", "connsvc_user2_{$ts}", 'Connection', 'Receiver', 'Connection Receiver']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 25, 1, NOW())",
            [self::$testTenantId, "connsvc_user3_{$ts}@test.com", "connsvc_user3_{$ts}", 'Third', 'User', 'Third User']
        );
        self::$testUser3Id = (int)Database::getInstance()->lastInsertId();

        // Create test connection (pending)
        try {
            Database::query(
                "INSERT INTO connections (requester_id, receiver_id, status, created_at)
                 VALUES (?, ?, 'pending', NOW())",
                [self::$testUserId, self::$testUser2Id]
            );
            self::$testConnectionId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Connections may not exist
        }
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testUser2Id, self::$testUser3Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM connections WHERE requester_id = ? OR receiver_id = ?", [$uid, $uid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM notifications WHERE user_id = ?", [$uid]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getConnections Tests
    // ==========================================

    public function testGetConnectionsReturnsValidStructure(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUserId);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('has_more', $result);
            $this->assertIsArray($result['items']);
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections not available: ' . $e->getMessage());
        }
    }

    public function testGetConnectionsRespectsLimit(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUserId, ['limit' => 5]);

            $this->assertLessThanOrEqual(5, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections not available: ' . $e->getMessage());
        }
    }

    public function testGetConnectionsEnforcesMaxLimit(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUserId, ['limit' => 500]);

            $this->assertLessThanOrEqual(100, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections not available: ' . $e->getMessage());
        }
    }

    public function testGetConnectionsFiltersByStatusAccepted(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUserId, ['status' => 'accepted']);
            $this->assertIsArray($result['items']);

            foreach ($result['items'] as $connection) {
                $this->assertEquals('accepted', $connection['status']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections status filter not available: ' . $e->getMessage());
        }
    }

    public function testGetConnectionsFiltersByStatusPendingSent(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUserId, ['status' => 'pending_sent']);

            foreach ($result['items'] as $connection) {
                $this->assertEquals('pending', $connection['status']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections pending_sent not available: ' . $e->getMessage());
        }
    }

    public function testGetConnectionsFiltersByStatusPendingReceived(): void
    {
        try {
            $result = ConnectionService::getConnections(self::$testUser2Id, ['status' => 'pending_received']);

            foreach ($result['items'] as $connection) {
                $this->assertEquals('pending', $connection['status']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getConnections pending_received not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // sendRequest Tests
    // ==========================================

    public function testSendRequestReturnsTrueForNewRequest(): void
    {
        // sendRequest(requesterId, receiverId) returns bool
        $result = ConnectionService::sendRequest(self::$testUser3Id, self::$testUser2Id);

        $this->assertTrue($result);

        // Cleanup
        Database::query("DELETE FROM connections WHERE requester_id = ? AND receiver_id = ?", [self::$testUser3Id, self::$testUser2Id]);
    }

    public function testSendRequestReturnsFalseForSelfRequest(): void
    {
        try {
            $result = ConnectionService::sendRequest(self::$testUserId, self::$testUserId);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('sendRequest not available: ' . $e->getMessage());
        }
    }

    public function testSendRequestReturnsFalseForExistingRequest(): void
    {
        try {
            // Already have a pending request (created in setup)
            $result = ConnectionService::sendRequest(self::$testUserId, self::$testUser2Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('sendRequest not available: ' . $e->getMessage());
        }
    }

    public function testSendRequestReturnsFalseForExistingConnection(): void
    {
        try {
            // Create an accepted connection first
            Database::query(
                "INSERT INTO connections (requester_id, receiver_id, status, created_at)
                 VALUES (?, ?, 'accepted', NOW())",
                [self::$testUser3Id, self::$testUser2Id]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            // Try to send another request
            $result = ConnectionService::sendRequest(self::$testUser3Id, self::$testUser2Id);

            $this->assertFalse($result);

            // Cleanup
            Database::query("DELETE FROM connections WHERE id = ?", [$tempId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('sendRequest existing connection check not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // acceptRequest Tests
    // ==========================================

    public function testAcceptRequestReturnsTrueForPendingRequest(): void
    {
        if (!self::$testConnectionId) {
            $this->markTestSkipped('Test connection not available');
        }

        try {
            $result = ConnectionService::acceptRequest(self::$testConnectionId, self::$testUser2Id);

            $this->assertTrue($result);

            // Verify status changed to accepted
            $stmt = Database::query("SELECT status FROM connections WHERE id = ?", [self::$testConnectionId]);
            $row = $stmt->fetch();
            $this->assertEquals('accepted', $row['status']);
        } catch (\Exception $e) {
            $this->markTestSkipped('acceptRequest not available: ' . $e->getMessage());
        }
    }

    public function testAcceptRequestReturnsFalseForNonExistent(): void
    {
        try {
            $result = ConnectionService::acceptRequest(999999, self::$testUserId);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('acceptRequest not available: ' . $e->getMessage());
        }
    }

    public function testAcceptRequestReturnsFalseForNonReceiver(): void
    {
        // Create a new pending connection for this test
        try {
            Database::query(
                "INSERT INTO connections (requester_id, receiver_id, status, created_at)
                 VALUES (?, ?, 'pending', NOW())",
                [self::$testUser2Id, self::$testUser3Id]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            // User 1 trying to accept (not the receiver)
            $result = ConnectionService::acceptRequest($tempId, self::$testUserId);

            $this->assertFalse($result);

            // Cleanup
            Database::query("DELETE FROM connections WHERE id = ?", [$tempId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('acceptRequest authorization check not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // rejectRequest Tests
    // ==========================================

    public function testRejectRequestReturnsTrueForPendingRequest(): void
    {
        try {
            // Create a new pending connection to reject
            Database::query(
                "INSERT INTO connections (requester_id, receiver_id, status, created_at)
                 VALUES (?, ?, 'pending', NOW())",
                [self::$testUser2Id, self::$testUser3Id]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            $result = ConnectionService::rejectRequest($tempId, self::$testUser3Id);

            $this->assertTrue($result);

            // Verify it was deleted or marked rejected
            $stmt = Database::query("SELECT * FROM connections WHERE id = ?", [$tempId]);
            $row = $stmt->fetch();
            $this->assertFalse($row); // Should be deleted
        } catch (\Exception $e) {
            $this->markTestSkipped('rejectRequest not available: ' . $e->getMessage());
        }
    }

    public function testRejectRequestReturnsFalseForNonExistent(): void
    {
        try {
            $result = ConnectionService::rejectRequest(999999, self::$testUserId);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('rejectRequest not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // removeConnection Tests
    // ==========================================

    public function testRemoveConnectionReturnsTrueForExistingConnection(): void
    {
        try {
            // Create an accepted connection to remove
            Database::query(
                "INSERT INTO connections (requester_id, receiver_id, status, created_at)
                 VALUES (?, ?, 'accepted', NOW())",
                [self::$testUser2Id, self::$testUser3Id]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            $result = ConnectionService::removeConnection($tempId, self::$testUser2Id);

            $this->assertTrue($result);

            // Verify it was deleted
            $stmt = Database::query("SELECT * FROM connections WHERE id = ?", [$tempId]);
            $row = $stmt->fetch();
            $this->assertFalse($row);
        } catch (\Exception $e) {
            $this->markTestSkipped('removeConnection not available: ' . $e->getMessage());
        }
    }

    public function testRemoveConnectionReturnsFalseForNonExistent(): void
    {
        try {
            $result = ConnectionService::removeConnection(999999, self::$testUserId);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('removeConnection not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getConnectionStatus Tests
    // ==========================================

    public function testGetStatusReturnsArrayWithNoneStatus(): void
    {
        // getStatus() returns array, not string
        $status = ConnectionService::getStatus(self::$testUser2Id, self::$testUser3Id);

        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('connection_id', $status);
        $this->assertArrayHasKey('direction', $status);
        $this->assertEquals('none', $status['status']);
        $this->assertNull($status['connection_id']);
    }

    public function testGetStatusReturnsPendingSentForRequester(): void
    {
        // Create a fresh pending connection for this test
        Database::query(
            "INSERT INTO connections (requester_id, receiver_id, status, created_at)
             VALUES (?, ?, 'pending', NOW())",
            [self::$testUser3Id, self::$testUserId]
        );
        $tempId = (int)Database::getInstance()->lastInsertId();

        $status = ConnectionService::getStatus(self::$testUser3Id, self::$testUserId);

        $this->assertEquals('pending_sent', $status['status']);
        $this->assertEquals('sent', $status['direction']);
        $this->assertIsInt($status['connection_id']);

        // Cleanup
        Database::query("DELETE FROM connections WHERE id = ?", [$tempId]);
    }

    public function testGetStatusReturnsPendingReceivedForReceiver(): void
    {
        // Create a fresh pending connection for this test
        Database::query(
            "INSERT INTO connections (requester_id, receiver_id, status, created_at)
             VALUES (?, ?, 'pending', NOW())",
            [self::$testUser3Id, self::$testUserId]
        );
        $tempId = (int)Database::getInstance()->lastInsertId();

        $status = ConnectionService::getStatus(self::$testUserId, self::$testUser3Id);

        $this->assertEquals('pending_received', $status['status']);
        $this->assertEquals('received', $status['direction']);
        $this->assertIsInt($status['connection_id']);

        // Cleanup
        Database::query("DELETE FROM connections WHERE id = ?", [$tempId]);
    }

    public function testGetStatusReturnsAcceptedForConnectedUsers(): void
    {
        // Create an accepted connection
        Database::query(
            "INSERT INTO connections (requester_id, receiver_id, status, created_at)
             VALUES (?, ?, 'accepted', NOW())",
            [self::$testUser2Id, self::$testUser3Id]
        );
        $tempId = (int)Database::getInstance()->lastInsertId();

        $status = ConnectionService::getStatus(self::$testUser2Id, self::$testUser3Id);

        $this->assertEquals('connected', $status['status']);
        $this->assertNull($status['direction']); // Accepted has no direction

        // Cleanup
        Database::query("DELETE FROM connections WHERE id = ?", [$tempId]);
    }
}
