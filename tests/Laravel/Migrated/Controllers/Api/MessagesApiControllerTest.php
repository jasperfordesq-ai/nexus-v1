<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Core\TenantContext;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;

/**
 * Test Cases for Messages API Controller (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\MessagesApiControllerTest
 * Original base: ApiTestCase -> now LegacyBridgeTestCase
 *
 * Tests the V2 Messages API endpoints:
 * - POST /api/v2/messages (send message)
 * - GET /api/v2/messages (list conversations)
 * - GET /api/v2/messages/{id} (get conversation messages)
 */
class MessagesApiControllerTest extends LegacyBridgeTestCase
{
    protected ?int $recipientId = null;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setById(static::$testTenantId);

        // Create a recipient user for message tests
        $recipient = $this->createUser([
            'first_name' => 'Recipient',
            'last_name'  => 'User',
        ]);
        $this->recipientId = $recipient['id'];
    }

    protected function tearDown(): void
    {
        if ($this->recipientId) {
            try {
                DB::table('messages')
                    ->where('sender_id', $this->recipientId)
                    ->orWhere('receiver_id', $this->recipientId)
                    ->delete();
                DB::table('notifications')->where('user_id', $this->recipientId)->delete();
            } catch (\Exception $e) {
                // Ignore — tables may not exist during early migration
            }
            $this->cleanupUser($this->recipientId);
        }

        // Clean up test user messages
        if (static::$testUserId) {
            try {
                DB::table('messages')
                    ->where('sender_id', static::$testUserId)
                    ->orWhere('receiver_id', static::$testUserId)
                    ->delete();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        parent::tearDown();
    }

    /**
     * Test: MessageService::send returns correct response shape
     */
    public function testSendMessageReturnsCorrectShape(): void
    {
        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
            'body' => 'Test message for response shape validation',
        ]);

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

        $this->assertEquals(static::$testUserId, $result['sender_id'], 'sender_id should match the sending user');
    }

    /**
     * Test: MessageService::send includes sender object
     */
    public function testSendMessageIncludesSenderObject(): void
    {
        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
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
        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
            'body' => 'Test message with recipient',
        ]);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('recipient_id', $result, 'Response must have recipient_id');
        $this->assertEquals($this->recipientId, $result['recipient_id'], 'recipient_id should match');
    }

    /**
     * Test: MessageService validation - missing recipient
     */
    public function testSendMessageValidationMissingRecipient(): void
    {
        $result = MessageService::send(static::$testUserId, [
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
        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
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
        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => static::$testUserId,
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
        // Clear existing notifications for recipient
        DB::table('notifications')->where('user_id', $this->recipientId)->delete();

        $result = MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
            'body' => 'Test message for notification creation',
        ]);

        $this->assertNotNull($result);

        // Check notification was created
        $notification = DB::table('notifications')
            ->where('user_id', $this->recipientId)
            ->where('type', 'message')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($notification, 'Notification should be created for recipient');
        $this->assertStringContainsString('/messages/', $notification->link, 'Notification link should point to messages');
    }

    /**
     * Test: MessageService::getMessages returns correct shape
     */
    public function testGetMessagesReturnsCorrectShape(): void
    {
        // Send a test message first
        MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
            'body' => 'Test message for getMessages shape test',
        ]);

        $result = MessageService::getMessages($this->recipientId, static::$testUserId);

        $this->assertArrayHasKey('items', $result, 'Response must have items array');
        $this->assertArrayHasKey('cursor', $result, 'Response must have cursor');
        $this->assertArrayHasKey('has_more', $result, 'Response must have has_more flag');

        $this->assertIsArray($result['items'], 'items should be an array');
        $this->assertIsBool($result['has_more'], 'has_more should be a boolean');

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
        // Send a test message to create a conversation
        MessageService::send(static::$testUserId, [
            'recipient_id' => $this->recipientId,
            'body' => 'Test message for conversation list',
        ]);

        $result = MessageService::getConversations(static::$testUserId);

        $this->assertArrayHasKey('items', $result, 'Response must have items array');
        $this->assertArrayHasKey('cursor', $result, 'Response must have cursor');
        $this->assertArrayHasKey('has_more', $result, 'Response must have has_more flag');

        if (!empty($result['items'])) {
            $conversation = $result['items'][0];
            $this->assertArrayHasKey('id', $conversation);
            $this->assertArrayHasKey('other_user', $conversation);
            $this->assertArrayHasKey('last_message', $conversation);
            $this->assertArrayHasKey('unread_count', $conversation);

            $this->assertArrayHasKey('id', $conversation['other_user']);
            $this->assertArrayHasKey('name', $conversation['other_user']);

            $this->assertArrayHasKey('id', $conversation['last_message']);
            $this->assertArrayHasKey('body', $conversation['last_message']);
            $this->assertArrayHasKey('is_own', $conversation['last_message']);
        }
    }
}
