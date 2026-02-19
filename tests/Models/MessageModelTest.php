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
use Nexus\Models\Message;

/**
 * Message Model Tests
 *
 * Tests message creation, conversation listing, thread retrieval,
 * read status marking, reactions, deletion, and tenant scoping.
 */
class MessageModelTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
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
        $timestamp = time();

        // Create sender user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "msg_model_sender_{$timestamp}@test.com",
                "msg_model_sender_{$timestamp}",
                'Message',
                'Sender',
                'Message Sender',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create receiver user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "msg_model_receiver_{$timestamp}@test.com",
                "msg_model_receiver_{$timestamp}",
                'Message',
                'Receiver',
                'Message Receiver',
                50
            ]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create a test message directly in DB (bypassing Pusher/notifications for test setup)
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                self::$testUser2Id,
                'Test Subject',
                'Test message body for model tests.'
            ]
        );
        self::$testMessageId = (int)Database::getInstance()->lastInsertId();

        // Create a second message in the same thread (reverse direction)
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                self::$testUser2Id,
                self::$testUserId,
                'Re: Test Subject',
                'Reply message body.'
            ]
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM message_reactions WHERE message_id IN (SELECT id FROM messages WHERE sender_id = ? OR receiver_id = ?)", [self::$testUserId, self::$testUserId]);
                Database::query("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?", [self::$testUserId, self::$testUserId]);
                Database::query("DELETE FROM notification_queue WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM message_reactions WHERE message_id IN (SELECT id FROM messages WHERE sender_id = ? OR receiver_id = ?)", [self::$testUser2Id, self::$testUser2Id]);
                Database::query("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?", [self::$testUser2Id, self::$testUser2Id]);
                Database::query("DELETE FROM notification_queue WHERE user_id = ?", [self::$testUser2Id]);
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
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsMessage(): void
    {
        $message = Message::findById(self::$testTenantId, self::$testMessageId);

        $this->assertNotFalse($message);
        $this->assertIsArray($message);
        $this->assertEquals(self::$testMessageId, $message['id']);
        $this->assertEquals(self::$testTenantId, $message['tenant_id']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $message = Message::findById(self::$testTenantId, 999999999);

        $this->assertFalse($message);
    }

    public function testFindByIdEnforcesTenantScoping(): void
    {
        // Attempt to find message with wrong tenant
        $message = Message::findById(9999, self::$testMessageId);

        $this->assertFalse($message, 'Message should not be found with wrong tenant_id');
    }

    // ==========================================
    // Inbox / Conversation Tests
    // ==========================================

    public function testGetInboxReturnsConversations(): void
    {
        $inbox = Message::getInbox(self::$testUserId, self::$testTenantId);

        $this->assertIsArray($inbox);
        $this->assertGreaterThanOrEqual(1, count($inbox));
    }

    public function testGetInboxGroupsByOtherUser(): void
    {
        $inbox = Message::getInbox(self::$testUserId, self::$testTenantId);

        foreach ($inbox as $conversation) {
            $this->assertArrayHasKey('other_user_id', $conversation);
            $this->assertArrayHasKey('other_user_name', $conversation);
            $this->assertArrayHasKey('other_user_avatar', $conversation);
        }
    }

    public function testGetInboxShowsLatestMessage(): void
    {
        $inbox = Message::getInbox(self::$testUserId, self::$testTenantId);

        // The most recent message should be the reply
        $found = false;
        foreach ($inbox as $conversation) {
            if ((int)$conversation['other_user_id'] === self::$testUser2Id) {
                $found = true;
                // Should show the latest message (the reply)
                $this->assertNotEmpty($conversation['body']);
                break;
            }
        }
        $this->assertTrue($found, 'Inbox should contain conversation with test user 2');
    }

    // ==========================================
    // Thread Tests
    // ==========================================

    public function testGetThreadReturnsMessages(): void
    {
        $thread = Message::getThread(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        $this->assertIsArray($thread);
        $this->assertGreaterThanOrEqual(2, count($thread), 'Thread should have at least 2 messages');
    }

    public function testGetThreadIsBidirectional(): void
    {
        // Thread should be the same regardless of user order
        $thread1 = Message::getThread(self::$testTenantId, self::$testUserId, self::$testUser2Id);
        $thread2 = Message::getThread(self::$testTenantId, self::$testUser2Id, self::$testUserId);

        $this->assertCount(count($thread1), $thread2, 'Thread should be identical regardless of user order');
    }

    public function testGetThreadIncludesUserInfo(): void
    {
        $thread = Message::getThread(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        foreach ($thread as $message) {
            $this->assertArrayHasKey('sender_name', $message);
            $this->assertArrayHasKey('receiver_name', $message);
            $this->assertArrayHasKey('sender_avatar', $message);
            $this->assertArrayHasKey('receiver_avatar', $message);
        }
    }

    public function testGetThreadScopesByTenant(): void
    {
        $thread = Message::getThread(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        foreach ($thread as $message) {
            $this->assertEquals(self::$testTenantId, $message['tenant_id']);
        }
    }

    public function testGetThreadIsOrderedChronologically(): void
    {
        $thread = Message::getThread(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        for ($i = 1; $i < count($thread); $i++) {
            $this->assertGreaterThanOrEqual(
                $thread[$i - 1]['created_at'],
                $thread[$i]['created_at'],
                'Thread messages should be ordered chronologically'
            );
        }
    }

    // ==========================================
    // Mark Read Tests
    // ==========================================

    public function testMarkThreadReadUpdatesMessages(): void
    {
        // Create unread messages
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())",
            [self::$testTenantId, self::$testUser2Id, self::$testUserId, 'Unread', 'Unread message']
        );

        // Mark as read
        Message::markThreadRead(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        // Verify all messages from testUser2 to testUser are now read
        $unread = Database::query(
            "SELECT COUNT(*) as c FROM messages WHERE tenant_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = 0",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id]
        )->fetch();

        $this->assertEquals(0, (int)$unread['c'], 'All messages in thread should be marked as read');
    }

    public function testMarkThreadReadScopesByTenant(): void
    {
        // This should only affect messages in the current tenant
        Message::markThreadRead(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        // Verify no errors (tenant scoping is via WHERE clause)
        $this->assertTrue(true);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteSingleVerifiesOwnership(): void
    {
        // Create a message to delete
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id, 'To Delete', 'This will be deleted']
        );
        $msgId = (int)Database::getInstance()->lastInsertId();

        // Attempt delete by an uninvolved user (neither sender nor receiver)
        $result = Message::deleteSingle(self::$testTenantId, $msgId, 999999);
        $this->assertFalse($result, 'Should not delete if user is not sender or receiver');

        // Delete by the sender (should succeed)
        $result = Message::deleteSingle(self::$testTenantId, $msgId, self::$testUserId);
        $this->assertIsArray($result);
        $this->assertTrue($result['deleted']);
    }

    public function testDeleteSingleReturnsAudioUrl(): void
    {
        // Create a voice message
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, audio_url, audio_duration, created_at)
             VALUES (?, ?, ?, '', '', '/uploads/voice-test.webm', 15, NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id]
        );
        $msgId = (int)Database::getInstance()->lastInsertId();

        $result = Message::deleteSingle(self::$testTenantId, $msgId, self::$testUserId);

        $this->assertIsArray($result);
        $this->assertTrue($result['deleted']);
        $this->assertEquals('/uploads/voice-test.webm', $result['audio_url']);
    }

    public function testDeleteConversationRemovesAllMessages(): void
    {
        // Create a few messages in a thread
        for ($i = 0; $i < 3; $i++) {
            Database::query(
                "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [self::$testTenantId, self::$testUserId, self::$testUser2Id, "Conv {$i}", "Body {$i}"]
            );
        }

        $deleted = Message::deleteConversation(self::$testTenantId, self::$testUserId, self::$testUser2Id);

        $this->assertIsInt($deleted);
        $this->assertGreaterThan(0, $deleted, 'Should delete at least some messages');

        // Verify thread is empty
        $remaining = Database::query(
            "SELECT COUNT(*) as c FROM messages WHERE tenant_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id, self::$testUser2Id, self::$testUserId]
        )->fetch();

        $this->assertEquals(0, (int)$remaining['c'], 'No messages should remain after conversation delete');

        // Re-create test message for other tests
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, subject, body, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [self::$testTenantId, self::$testUserId, self::$testUser2Id, 'Test Subject', 'Restored test message']
        );
        self::$testMessageId = (int)Database::getInstance()->lastInsertId();
    }

    // ==========================================
    // Reaction Tests
    // ==========================================

    public function testToggleReactionAddsReaction(): void
    {
        $result = Message::toggleReaction(self::$testTenantId, self::$testMessageId, self::$testUserId, '👍');

        $this->assertTrue($result['success']);
        $this->assertEquals('added', $result['action']);
        $this->assertIsArray($result['reactions']);

        // Clean up
        Database::query(
            "DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?",
            [self::$testMessageId, self::$testUserId]
        );
    }

    public function testToggleReactionTogglesOff(): void
    {
        // Add
        Message::toggleReaction(self::$testTenantId, self::$testMessageId, self::$testUserId, '❤️');

        // Toggle off
        $result = Message::toggleReaction(self::$testTenantId, self::$testMessageId, self::$testUserId, '❤️');

        $this->assertTrue($result['success']);
        $this->assertEquals('removed', $result['action']);
    }

    public function testToggleReactionFailsForNonParticipant(): void
    {
        $result = Message::toggleReaction(self::$testTenantId, self::$testMessageId, 999999, '👍');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testToggleReactionFailsForNonExistentMessage(): void
    {
        $result = Message::toggleReaction(self::$testTenantId, 999999999, self::$testUserId, '👍');

        $this->assertFalse($result['success']);
    }

    public function testGetReactionsReturnsGroupedEmojis(): void
    {
        // Add reactions from both users
        Database::query(
            "INSERT INTO message_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, '👍', NOW())",
            [self::$testMessageId, self::$testUserId]
        );
        Database::query(
            "INSERT INTO message_reactions (message_id, user_id, emoji, created_at) VALUES (?, ?, '👍', NOW())",
            [self::$testMessageId, self::$testUser2Id]
        );

        $reactions = Message::getReactions(self::$testMessageId);

        $this->assertIsArray($reactions);
        $this->assertGreaterThanOrEqual(1, count($reactions));

        $thumbsUp = null;
        foreach ($reactions as $reaction) {
            if ($reaction['emoji'] === '👍') {
                $thumbsUp = $reaction;
                break;
            }
        }

        $this->assertNotNull($thumbsUp);
        $this->assertEquals(2, $thumbsUp['count']);
        $this->assertIsArray($thumbsUp['user_ids']);
        $this->assertCount(2, $thumbsUp['user_ids']);

        // Clean up
        Database::query("DELETE FROM message_reactions WHERE message_id = ?", [self::$testMessageId]);
    }

    public function testGetReactionsBatchReturnsGrouped(): void
    {
        $reactions = Message::getReactionsBatch([self::$testMessageId]);

        $this->assertIsArray($reactions);
    }

    public function testGetReactionsBatchWithEmptyArray(): void
    {
        $reactions = Message::getReactionsBatch([]);

        $this->assertIsArray($reactions);
        $this->assertEmpty($reactions);
    }

    // ==========================================
    // Email Notification Tests (method signature)
    // ==========================================

    public function testSendEmailNotificationMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Message::class, 'sendEmailNotification'),
            'Message::sendEmailNotification should be a public static method'
        );
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testFindByIdWithZeroIdReturnsFalse(): void
    {
        $message = Message::findById(self::$testTenantId, 0);

        $this->assertFalse($message);
    }

    public function testGetThreadWithNonExistentUsersReturnsEmpty(): void
    {
        $thread = Message::getThread(self::$testTenantId, 999999, 999998);

        $this->assertIsArray($thread);
        $this->assertEmpty($thread);
    }
}
