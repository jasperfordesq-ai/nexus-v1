<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Integration;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Message Journey Integration Test
 *
 * Tests complete messaging workflows:
 * - Send message → receive notification
 * - Read message → mark as read
 * - Reply to message → conversation thread
 * - Archive conversation
 */
class MessageJourneyTest extends DatabaseTestCase
{
    private static int $testTenantId = 2;
    private int $userA_Id;
    private int $userB_Id;
    private array $createdMessageIds = [];
    private array $createdConversationIds = [];
    private array $createdNotificationIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create User A (sender)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 50)",
            [
                self::$testTenantId,
                "sender_{$timestamp}@example.com",
                "sender_{$timestamp}",
                'Alice',
                'Sender',
                'Alice Sender',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->userA_Id = (int)Database::lastInsertId();

        // Create User B (receiver)
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 50)",
            [
                self::$testTenantId,
                "receiver_{$timestamp}@example.com",
                "receiver_{$timestamp}",
                'Bob',
                'Receiver',
                'Bob Receiver',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->userB_Id = (int)Database::lastInsertId();
    }

    protected function tearDown(): void
    {
        // Clean up in reverse order
        foreach ($this->createdNotificationIds as $notificationId) {
            try {
                Database::query("DELETE FROM notifications WHERE id = ?", [$notificationId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdMessageIds as $messageId) {
            try {
                Database::query("DELETE FROM messages WHERE id = ?", [$messageId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdConversationIds as $conversationId) {
            try {
                Database::query("DELETE FROM conversations WHERE id = ?", [$conversationId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            Database::query("DELETE FROM users WHERE id IN (?, ?)", [$this->userA_Id, $this->userB_Id]);
        } catch (\Exception $e) {
            // Ignore
        }

        parent::tearDown();
    }

    /**
     * Test: Send message and create notification
     */
    public function testSendMessageAndNotification(): void
    {
        // Step 1: Create a conversation
        Database::query(
            "INSERT INTO conversations (tenant_id, created_at) VALUES (?, NOW())",
            [self::$testTenantId]
        );
        $conversationId = (int)Database::lastInsertId();
        $this->createdConversationIds[] = $conversationId;

        // Add participants
        Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, NOW()), (?, ?, ?, NOW())",
            [
                $conversationId,
                $this->userA_Id,
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                self::$testTenantId
            ]
        );

        // Step 2: User A sends a message to User B
        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userA_Id,
                $this->userB_Id,
                'Hello Bob! How are you doing?'
            ]
        );
        $messageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $messageId;

        $this->assertGreaterThan(0, $messageId, 'Message should be created');

        // Step 3: Create notification for User B
        Database::query(
            "INSERT INTO notifications (tenant_id, user_id, type, message, reference_id, is_read, created_at)
             VALUES (?, ?, 'new_message', ?, ?, 0, NOW())",
            [
                self::$testTenantId,
                $this->userB_Id,
                'You have a new message from Alice Sender',
                $messageId
            ]
        );
        $notificationId = (int)Database::lastInsertId();
        $this->createdNotificationIds[] = $notificationId;

        // Step 4: Verify notification exists and is unread
        $stmt = Database::query(
            "SELECT * FROM notifications WHERE id = ? AND tenant_id = ?",
            [$notificationId, self::$testTenantId]
        );
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification, 'Notification should exist');
        $this->assertEquals($this->userB_Id, $notification['user_id']);
        $this->assertEquals('new_message', $notification['type']);
        $this->assertEquals(0, $notification['is_read']);

        // Step 5: Verify message content
        $stmt = Database::query("SELECT * FROM messages WHERE id = ?", [$messageId]);
        $message = $stmt->fetch();

        $this->assertEquals($this->userA_Id, $message['sender_id']);
        $this->assertEquals($this->userB_Id, $message['receiver_id']);
        $this->assertStringContainsString('Hello Bob', $message['message']);
    }

    /**
     * Test: Read message and mark notification as read
     */
    public function testReadMessageFlow(): void
    {
        // Create conversation and message
        Database::query(
            "INSERT INTO conversations (tenant_id, created_at) VALUES (?, NOW())",
            [self::$testTenantId]
        );
        $conversationId = (int)Database::lastInsertId();
        $this->createdConversationIds[] = $conversationId;

        Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, NOW()), (?, ?, ?, NOW())",
            [
                $conversationId,
                $this->userA_Id,
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                self::$testTenantId
            ]
        );

        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, is_read, created_at)
             VALUES (?, ?, ?, ?, 'Unread message', 0, NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userA_Id,
                $this->userB_Id
            ]
        );
        $messageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $messageId;

        // Step 1: Verify message is unread
        $stmt = Database::query("SELECT is_read FROM messages WHERE id = ?", [$messageId]);
        $message = $stmt->fetch();
        $this->assertEquals(0, $message['is_read']);

        // Step 2: User B reads the message
        Database::query(
            "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$messageId, self::$testTenantId]
        );

        // Step 3: Verify message is now marked as read
        $stmt = Database::query("SELECT is_read, read_at FROM messages WHERE id = ?", [$messageId]);
        $readMessage = $stmt->fetch();

        $this->assertEquals(1, $readMessage['is_read']);
        $this->assertNotNull($readMessage['read_at']);
    }

    /**
     * Test: Reply to message creates conversation thread
     */
    public function testReplyToMessageFlow(): void
    {
        // Create conversation
        Database::query(
            "INSERT INTO conversations (tenant_id, created_at) VALUES (?, NOW())",
            [self::$testTenantId]
        );
        $conversationId = (int)Database::lastInsertId();
        $this->createdConversationIds[] = $conversationId;

        Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, NOW()), (?, ?, ?, NOW())",
            [
                $conversationId,
                $this->userA_Id,
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                self::$testTenantId
            ]
        );

        // Step 1: User A sends initial message
        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (?, ?, ?, ?, 'Initial message', NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userA_Id,
                $this->userB_Id
            ]
        );
        $firstMessageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $firstMessageId;

        // Step 2: User B replies to the message
        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (?, ?, ?, ?, 'Reply message', NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                $this->userA_Id
            ]
        );
        $replyMessageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $replyMessageId;

        // Step 3: User A sends another reply
        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (?, ?, ?, ?, 'Follow-up message', NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userA_Id,
                $this->userB_Id
            ]
        );
        $followUpMessageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $followUpMessageId;

        // Step 4: Verify all messages are in the same conversation
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND tenant_id = ?",
            [$conversationId, self::$testTenantId]
        );
        $this->assertEquals(3, $stmt->fetch()['count'], 'Should have 3 messages in thread');

        // Step 5: Verify message order
        $stmt = Database::query(
            "SELECT id, sender_id, message FROM messages WHERE conversation_id = ? ORDER BY created_at ASC",
            [$conversationId]
        );
        $messages = $stmt->fetchAll();

        $this->assertCount(3, $messages);
        $this->assertEquals($this->userA_Id, $messages[0]['sender_id']);
        $this->assertEquals($this->userB_Id, $messages[1]['sender_id']);
        $this->assertEquals($this->userA_Id, $messages[2]['sender_id']);
        $this->assertStringContainsString('Initial', $messages[0]['message']);
        $this->assertStringContainsString('Reply', $messages[1]['message']);
        $this->assertStringContainsString('Follow-up', $messages[2]['message']);
    }

    /**
     * Test: Archive conversation
     */
    public function testArchiveConversationFlow(): void
    {
        // Create conversation with messages
        Database::query(
            "INSERT INTO conversations (tenant_id, created_at) VALUES (?, NOW())",
            [self::$testTenantId]
        );
        $conversationId = (int)Database::lastInsertId();
        $this->createdConversationIds[] = $conversationId;

        Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, NOW()), (?, ?, ?, NOW())",
            [
                $conversationId,
                $this->userA_Id,
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                self::$testTenantId
            ]
        );

        Database::query(
            "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, created_at)
             VALUES (?, ?, ?, ?, 'Message to archive', NOW())",
            [
                self::$testTenantId,
                $conversationId,
                $this->userA_Id,
                $this->userB_Id
            ]
        );
        $messageId = (int)Database::lastInsertId();
        $this->createdMessageIds[] = $messageId;

        // Step 1: Verify conversation is not archived
        $stmt = Database::query(
            "SELECT * FROM conversation_participants WHERE conversation_id = ? AND user_id = ?",
            [$conversationId, $this->userA_Id]
        );
        $participant = $stmt->fetch();
        $this->assertEquals(0, $participant['is_archived'] ?? 0);

        // Step 2: User A archives the conversation
        Database::query(
            "UPDATE conversation_participants
             SET is_archived = 1, archived_at = NOW()
             WHERE conversation_id = ? AND user_id = ? AND tenant_id = ?",
            [$conversationId, $this->userA_Id, self::$testTenantId]
        );

        // Step 3: Verify conversation is archived for User A only
        $stmt = Database::query(
            "SELECT is_archived, archived_at FROM conversation_participants
             WHERE conversation_id = ? AND user_id = ?",
            [$conversationId, $this->userA_Id]
        );
        $archivedParticipant = $stmt->fetch();

        $this->assertEquals(1, $archivedParticipant['is_archived']);
        $this->assertNotNull($archivedParticipant['archived_at']);

        // Step 4: Verify conversation is NOT archived for User B
        $stmt = Database::query(
            "SELECT is_archived FROM conversation_participants
             WHERE conversation_id = ? AND user_id = ?",
            [$conversationId, $this->userB_Id]
        );
        $userBParticipant = $stmt->fetch();

        $this->assertEquals(0, $userBParticipant['is_archived'] ?? 0, 'User B should still see conversation');
    }

    /**
     * Test: Count unread messages
     */
    public function testCountUnreadMessages(): void
    {
        // Create conversation
        Database::query(
            "INSERT INTO conversations (tenant_id, created_at) VALUES (?, NOW())",
            [self::$testTenantId]
        );
        $conversationId = (int)Database::lastInsertId();
        $this->createdConversationIds[] = $conversationId;

        Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, tenant_id, created_at)
             VALUES (?, ?, ?, NOW()), (?, ?, ?, NOW())",
            [
                $conversationId,
                $this->userA_Id,
                self::$testTenantId,
                $conversationId,
                $this->userB_Id,
                self::$testTenantId
            ]
        );

        // Step 1: Create 3 unread messages and 2 read messages
        for ($i = 0; $i < 3; $i++) {
            Database::query(
                "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())",
                [
                    self::$testTenantId,
                    $conversationId,
                    $this->userA_Id,
                    $this->userB_Id,
                    "Unread message {$i}"
                ]
            );
            $this->createdMessageIds[] = (int)Database::lastInsertId();
        }

        for ($i = 0; $i < 2; $i++) {
            Database::query(
                "INSERT INTO messages (tenant_id, conversation_id, sender_id, receiver_id, message, is_read, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())",
                [
                    self::$testTenantId,
                    $conversationId,
                    $this->userA_Id,
                    $this->userB_Id,
                    "Read message {$i}"
                ]
            );
            $this->createdMessageIds[] = (int)Database::lastInsertId();
        }

        // Step 2: Count unread messages for User B
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM messages
             WHERE receiver_id = ? AND tenant_id = ? AND is_read = 0",
            [$this->userB_Id, self::$testTenantId]
        );
        $unreadCount = $stmt->fetch()['count'];

        $this->assertEquals(3, $unreadCount, 'User B should have 3 unread messages');
    }
}
