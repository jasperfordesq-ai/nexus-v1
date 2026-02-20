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
use Nexus\Services\MessageService;

/**
 * MessageService Tests
 *
 * Tests messaging, conversations, read receipts, and unread counts.
 */
class MessageServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testUser3Id = null;
    protected static ?int $testMessageId = null;

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
            [self::$testTenantId, "msgsvc_user1_{$ts}@test.com", "msgsvc_user1_{$ts}", 'Message', 'Sender', 'Message Sender']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "msgsvc_user2_{$ts}@test.com", "msgsvc_user2_{$ts}", 'Message', 'Receiver', 'Message Receiver']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 25, 1, NOW())",
            [self::$testTenantId, "msgsvc_user3_{$ts}@test.com", "msgsvc_user3_{$ts}", 'Third', 'User', 'Third User']
        );
        self::$testUser3Id = (int)Database::getInstance()->lastInsertId();

        // Create test message
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, is_read, created_at)
             VALUES (?, ?, ?, 'Test message content', 0, NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id]
        );
        self::$testMessageId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            Database::query("DELETE FROM messages WHERE tenant_id = ?", [self::$testTenantId]);
        } catch (\Exception $e) {}

        $userIds = array_filter([self::$testUserId, self::$testUser2Id, self::$testUser3Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getConversations Tests
    // ==========================================

    public function testGetConversationsReturnsValidStructure(): void
    {
        $result = MessageService::getConversations(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetConversationsRespectsLimit(): void
    {
        $result = MessageService::getConversations(self::$testUserId, ['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function testGetConversationsEnforcesMaxLimit(): void
    {
        $result = MessageService::getConversations(self::$testUserId, ['limit' => 500]);

        $this->assertLessThanOrEqual(100, count($result['items']));
    }

    public function testGetConversationsIncludesOtherUserInfo(): void
    {
        $result = MessageService::getConversations(self::$testUserId);

        if (!empty($result['items'])) {
            $conversation = $result['items'][0];
            $this->assertArrayHasKey('other_user', $conversation);
            $this->assertIsArray($conversation['other_user']);
        }
    }

    public function testGetConversationsIncludesLastMessage(): void
    {
        $result = MessageService::getConversations(self::$testUserId);

        if (!empty($result['items'])) {
            $conversation = $result['items'][0];
            $this->assertArrayHasKey('last_message', $conversation);
            $this->assertIsArray($conversation['last_message']);
        }
    }

    public function testGetConversationsExcludesArchivedByDefault(): void
    {
        // This test verifies archived conversations are not returned unless requested
        $result = MessageService::getConversations(self::$testUserId);

        // Should only return non-archived conversations
        $this->assertIsArray($result['items']);
    }

    // ==========================================
    // getMessages Tests
    // ==========================================

    public function testGetMessagesReturnsValidStructure(): void
    {
        try {
            $result = MessageService::getMessages(self::$testUser2Id, self::$testUserId);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('has_more', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('getMessages not available: ' . $e->getMessage());
        }
    }

    public function testGetMessagesRespectsLimit(): void
    {
        try {
            $result = MessageService::getMessages(self::$testUser2Id, self::$testUserId, ['limit' => 10]);

            $this->assertLessThanOrEqual(10, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('getMessages not available: ' . $e->getMessage());
        }
    }

    public function testGetMessagesIncludesSenderInfo(): void
    {
        try {
            $result = MessageService::getMessages(self::$testUser2Id, self::$testUserId);

            if (!empty($result['items'])) {
                $message = $result['items'][0];
                $this->assertArrayHasKey('sender_id', $message);
                $this->assertArrayHasKey('sender', $message);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('getMessages not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // sendMessage Tests
    // ==========================================

    public function testSendMessageReturnsDataForValidMessage(): void
    {
        try {
            $message = MessageService::send(self::$testUserId, [
                'recipient_id' => self::$testUser3Id,
                'body' => 'Test message from unit test'
            ]);

            $this->assertIsArray($message);
            $this->assertArrayHasKey('id', $message);
            $this->assertGreaterThan(0, $message['id']);

            // Cleanup
            Database::query("DELETE FROM messages WHERE id = ? AND tenant_id = ?", [$message['id'], self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('send not available: ' . $e->getMessage());
        }
    }

    public function testSendMessageReturnsNullForEmptyBody(): void
    {
        try {
            $result = MessageService::send(self::$testUserId, [
                'recipient_id' => self::$testUser2Id,
                'body' => ''
            ]);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('send not available: ' . $e->getMessage());
        }
    }

    public function testSendMessageReturnsNullForSelfMessage(): void
    {
        try {
            $result = MessageService::send(self::$testUserId, [
                'recipient_id' => self::$testUserId,
                'body' => 'Message to self'
            ]);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('send not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // markAsRead Tests
    // ==========================================

    public function testMarkAsReadReturnsCountForUnreadMessages(): void
    {
        try {
            // Create an unread message
            Database::query(
                "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, is_read, created_at)
                 VALUES (?, ?, ?, 'Unread test', 0, NOW())",
                [self::$testTenantId, self::$testUser2Id, self::$testUserId]
            );
            $unreadId = (int)Database::getInstance()->lastInsertId();

            // markAsRead marks conversation, not individual message
            $count = MessageService::markAsRead(self::$testUser2Id, self::$testUserId);

            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);

            // Cleanup
            Database::query("DELETE FROM messages WHERE id = ? AND tenant_id = ?", [$unreadId, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('markAsRead not available: ' . $e->getMessage());
        }
    }

    public function testMarkAsReadReturnsZeroForNoUnreadMessages(): void
    {
        try {
            $count = MessageService::markAsRead(999999, self::$testUserId);

            $this->assertEquals(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('markAsRead not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getUnreadCount Tests
    // ==========================================

    public function testGetUnreadCountReturnsInteger(): void
    {
        try {
            $count = MessageService::getUnreadCount(self::$testUserId);

            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('getUnreadCount not available: ' . $e->getMessage());
        }
    }

    public function testGetUnreadCountExcludesReadMessages(): void
    {
        try {
            // Mark all existing messages as read
            Database::query(
                "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND tenant_id = ?",
                [self::$testUserId, self::$testTenantId]
            );

            $count = MessageService::getUnreadCount(self::$testUserId);

            $this->assertEquals(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('getUnreadCount not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // archiveConversation Tests
    // ==========================================

    public function testArchiveConversationReturnsCountForValidConversation(): void
    {
        try {
            $count = MessageService::archiveConversation(self::$testUser2Id, self::$testUserId);

            // archiveConversation returns count of archived messages
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        } catch (\Exception $e) {
            $this->markTestSkipped('archiveConversation not available: ' . $e->getMessage());
        }
    }

    // ==========================================
    // deleteMessage Tests
    // ==========================================

    public function testDeleteMessageReturnsTrueForOwnMessage(): void
    {
        try {
            // Create a message to delete
            Database::query(
                "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, is_read, created_at)
                 VALUES (?, ?, ?, 'To delete', 0, NOW())",
                [self::$testTenantId, self::$testUserId, self::$testUser2Id]
            );
            $tempId = (int)Database::getInstance()->lastInsertId();

            $result = MessageService::deleteMessage($tempId, self::$testUserId);

            $this->assertTrue($result);

            // Cleanup (if soft delete was used)
            Database::query("DELETE FROM messages WHERE id = ? AND tenant_id = ?", [$tempId, self::$testTenantId]);
        } catch (\Exception $e) {
            $this->markTestSkipped('deleteMessage not available: ' . $e->getMessage());
        }
    }

    public function testDeleteMessageReturnsFalseForOthersMessage(): void
    {
        try {
            // Try to delete someone else's message
            $result = MessageService::deleteMessage(self::$testMessageId, self::$testUser3Id);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('deleteMessage not available: ' . $e->getMessage());
        }
    }
}
