<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\MessageService;

/**
 * Test Cases for Messages API Controller
 *
 * Tests the V2 Messages API endpoints:
 * - POST /api/v2/messages (send message)
 * - GET /api/v2/messages (list conversations)
 * - GET /api/v2/messages/{id} (get conversation messages)
 *
 * Focus areas:
 * - Response shape validation
 * - Email notification triggering
 * - Error handling
 */
class MessagesApiControllerTest extends ApiTestCase
{
    protected static ?int $recipientId = null;

    /**
     * Set up test fixtures
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create a recipient user for message tests
        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "recipient_{$timestamp}@test.com",
                "recipient_{$timestamp}",
                'Recipient',
                'User',
                'Recipient User',
                100
            ]
        );
        self::$recipientId = (int)Database::getInstance()->lastInsertId();
    }

    /**
     * Clean up test fixtures
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$recipientId) {
            // Clean up messages
            Database::query("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?", [self::$recipientId, self::$recipientId]);
            Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$recipientId]);
            Database::query("DELETE FROM notification_queue WHERE user_id = ?", [self::$recipientId]);
            Database::query("DELETE FROM users WHERE id = ?", [self::$recipientId]);
        }

        // Clean up test user messages
        if (self::$testUserId) {
            Database::query("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?", [self::$testUserId, self::$testUserId]);
            Database::query("DELETE FROM notification_queue WHERE user_id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Test: MessageService::send returns correct response shape
     */
    public function testSendMessageReturnsCorrectShape(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message for response shape validation',
        ]);

        // Should not be null (success)
        $this->assertNotNull($result, 'MessageService::send should return a result');

        // Required fields for frontend Message type
        $this->assertArrayHasKey('id', $result, 'Response must have id');
        $this->assertArrayHasKey('body', $result, 'Response must have body');
        $this->assertArrayHasKey('sender_id', $result, 'Response must have sender_id for frontend compatibility');
        $this->assertArrayHasKey('is_own', $result, 'Response must have is_own flag');
        $this->assertArrayHasKey('created_at', $result, 'Response must have created_at timestamp');

        // Type validation
        $this->assertIsInt($result['id'], 'id should be an integer');
        $this->assertIsString($result['body'], 'body should be a string');
        $this->assertIsInt($result['sender_id'], 'sender_id should be an integer');
        $this->assertIsBool($result['is_own'], 'is_own should be a boolean');
        $this->assertTrue($result['is_own'], 'is_own should be true for sender');

        // Sender ID should match
        $this->assertEquals(self::$testUserId, $result['sender_id'], 'sender_id should match the sending user');
    }

    /**
     * Test: MessageService::send includes sender object
     */
    public function testSendMessageIncludesSenderObject(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message with sender object',
        ]);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('sender', $result, 'Response must have sender object');
        $this->assertIsArray($result['sender'], 'sender should be an array');
        $this->assertArrayHasKey('id', $result['sender'], 'sender must have id');
        $this->assertArrayHasKey('name', $result['sender'], 'sender must have name');
    }

    /**
     * Test: MessageService::send includes recipient_id
     */
    public function testSendMessageIncludesRecipientId(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message with recipient',
        ]);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('recipient_id', $result, 'Response must have recipient_id');
        $this->assertEquals(self::$recipientId, $result['recipient_id'], 'recipient_id should match');
    }

    /**
     * Test: MessageService validation - missing recipient
     */
    public function testSendMessageValidationMissingRecipient(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'body' => 'Test message without recipient',
        ]);

        $this->assertNull($result, 'Should return null for invalid input');

        $errors = MessageService::getErrors();
        $this->assertNotEmpty($errors, 'Should have validation errors');
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    /**
     * Test: MessageService validation - empty body
     */
    public function testSendMessageValidationEmptyBody(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => '',
        ]);

        $this->assertNull($result, 'Should return null for empty body');

        $errors = MessageService::getErrors();
        $this->assertNotEmpty($errors, 'Should have validation errors');
    }

    /**
     * Test: MessageService validation - cannot message self
     */
    public function testSendMessageCannotMessageSelf(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$testUserId,
            'body' => 'Test message to self',
        ]);

        $this->assertNull($result, 'Should return null when messaging self');

        $errors = MessageService::getErrors();
        $this->assertNotEmpty($errors, 'Should have validation error');
        $this->assertStringContainsString('yourself', strtolower($errors[0]['message']));
    }

    /**
     * Test: Sending a message creates a notification
     */
    public function testSendMessageCreatesNotification(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Clear existing notifications for recipient
        Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$recipientId]);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message for notification creation',
        ]);

        $this->assertNotNull($result);

        // Check notification was created
        $stmt = Database::query(
            "SELECT * FROM notifications WHERE user_id = ? AND type = 'message' ORDER BY id DESC LIMIT 1",
            [self::$recipientId]
        );
        $notification = $stmt->fetch();

        $this->assertNotFalse($notification, 'Notification should be created for recipient');
        $this->assertStringContainsString('/messages/', $notification['link'], 'Notification link should point to messages');
    }

    /**
     * Test: MessageService::getMessages returns correct shape
     */
    public function testGetMessagesReturnsCorrectShape(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Send a test message first
        MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message for getMessages shape test',
        ]);

        // Get messages
        $result = MessageService::getMessages(self::$recipientId, self::$testUserId);

        // Response structure
        $this->assertArrayHasKey('items', $result, 'Response must have items array');
        $this->assertArrayHasKey('cursor', $result, 'Response must have cursor');
        $this->assertArrayHasKey('has_more', $result, 'Response must have has_more flag');

        $this->assertIsArray($result['items'], 'items should be an array');
        $this->assertIsBool($result['has_more'], 'has_more should be a boolean');

        // Check message items shape
        if (!empty($result['items'])) {
            $message = $result['items'][0];
            $this->assertArrayHasKey('id', $message);
            $this->assertArrayHasKey('body', $message);
            $this->assertArrayHasKey('is_own', $message);
            $this->assertArrayHasKey('created_at', $message);
            $this->assertArrayHasKey('sender', $message);
        }
    }

    /**
     * Test: MessageService::getConversations returns correct shape
     */
    public function testGetConversationsReturnsCorrectShape(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Send a test message to create a conversation
        MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => 'Test message for conversation list',
        ]);

        // Get conversations
        $result = MessageService::getConversations(self::$testUserId);

        // Response structure
        $this->assertArrayHasKey('items', $result, 'Response must have items array');
        $this->assertArrayHasKey('cursor', $result, 'Response must have cursor');
        $this->assertArrayHasKey('has_more', $result, 'Response must have has_more flag');

        // Check conversation items shape
        if (!empty($result['items'])) {
            $conversation = $result['items'][0];
            $this->assertArrayHasKey('id', $conversation);
            $this->assertArrayHasKey('other_user', $conversation);
            $this->assertArrayHasKey('last_message', $conversation);
            $this->assertArrayHasKey('unread_count', $conversation);

            // other_user shape
            $this->assertArrayHasKey('id', $conversation['other_user']);
            $this->assertArrayHasKey('name', $conversation['other_user']);

            // last_message shape
            $this->assertArrayHasKey('id', $conversation['last_message']);
            $this->assertArrayHasKey('body', $conversation['last_message']);
            $this->assertArrayHasKey('is_own', $conversation['last_message']);
        }
    }

    /**
     * Test: Voice message response includes audio fields
     */
    public function testVoiceMessageResponseShape(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = MessageService::send(self::$testUserId, [
            'recipient_id' => self::$recipientId,
            'body' => '',
            'voice_url' => 'https://example.com/audio.webm',
            'voice_duration' => 15,
        ]);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('is_voice', $result, 'Response must have is_voice flag');
        $this->assertArrayHasKey('audio_url', $result, 'Response must have audio_url');
        $this->assertArrayHasKey('audio_duration', $result, 'Response must have audio_duration');

        $this->assertTrue($result['is_voice'], 'is_voice should be true for voice messages');
        $this->assertEquals('https://example.com/audio.webm', $result['audio_url']);
        $this->assertEquals(15, $result['audio_duration']);
    }
}
