<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\FederationRealtimeService;

/**
 * FederationRealtimeService Tests
 */
class FederationRealtimeServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(FederationRealtimeService::class));
    }

    public function test_public_methods_exist(): void
    {
        $methods = [
            'getUserFederationChannel',
            'getConversationChannel',
            'broadcastNewMessage',
            'broadcastTyping',
            'broadcastMessageRead',
            'authFederationChannel',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(FederationRealtimeService::class, $method),
                "Method {$method} should exist on FederationRealtimeService"
            );
        }
    }

    public function test_all_public_methods_are_static(): void
    {
        $methods = [
            'getUserFederationChannel', 'getConversationChannel',
            'broadcastNewMessage', 'broadcastTyping',
            'broadcastMessageRead', 'authFederationChannel',
        ];

        foreach ($methods as $method) {
            $ref = new \ReflectionMethod(FederationRealtimeService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    // --- Channel naming tests ---

    public function test_get_user_federation_channel_format(): void
    {
        $channel = FederationRealtimeService::getUserFederationChannel(42, 3);
        $this->assertSame('private-federation.user.42.3', $channel);
    }

    public function test_get_user_federation_channel_with_different_ids(): void
    {
        $channel = FederationRealtimeService::getUserFederationChannel(100, 7);
        $this->assertSame('private-federation.user.100.7', $channel);
    }

    public function test_get_conversation_channel_is_deterministic(): void
    {
        // Same channel regardless of order
        $channel1 = FederationRealtimeService::getConversationChannel(1, 2, 3, 4);
        $channel2 = FederationRealtimeService::getConversationChannel(3, 4, 1, 2);

        $this->assertSame($channel1, $channel2);
    }

    public function test_get_conversation_channel_format(): void
    {
        // pair1 = "1-2", pair2 = "3-4" — "1-2" < "3-4" so order is pair1.pair2
        $channel = FederationRealtimeService::getConversationChannel(1, 2, 3, 4);
        $this->assertSame('private-federation.conversation.1-2.3-4', $channel);
    }

    public function test_get_conversation_channel_sorts_by_pair_string(): void
    {
        // pair1 = "10-1" vs pair2 = "2-5" — string comparison: "10-1" < "2-5"
        $channel = FederationRealtimeService::getConversationChannel(10, 1, 2, 5);
        $this->assertSame('private-federation.conversation.10-1.2-5', $channel);

        // Reversed order should produce the same channel
        $channelReversed = FederationRealtimeService::getConversationChannel(2, 5, 10, 1);
        $this->assertSame($channel, $channelReversed);
    }

    public function test_get_conversation_channel_same_users_different_tenants(): void
    {
        $channel1 = FederationRealtimeService::getConversationChannel(1, 1, 1, 2);
        $channel2 = FederationRealtimeService::getConversationChannel(1, 2, 1, 1);

        $this->assertSame($channel1, $channel2);
        $this->assertStringStartsWith('private-federation.conversation.', $channel1);
    }

    // --- Broadcast method signatures ---

    public function test_broadcast_new_message_signature(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'broadcastNewMessage');
        $params = $ref->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('senderUserId', $params[0]->getName());
        $this->assertEquals('senderTenantId', $params[1]->getName());
        $this->assertEquals('recipientUserId', $params[2]->getName());
        $this->assertEquals('recipientTenantId', $params[3]->getName());
        $this->assertEquals('messageData', $params[4]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_broadcast_typing_signature(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'broadcastTyping');
        $params = $ref->getParameters();

        $this->assertCount(5, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('tenantId', $params[1]->getName());
        $this->assertEquals('recipientUserId', $params[2]->getName());
        $this->assertEquals('recipientTenantId', $params[3]->getName());
        $this->assertEquals('isTyping', $params[4]->getName());
        $this->assertTrue($params[4]->isDefaultValueAvailable());
        $this->assertTrue($params[4]->getDefaultValue());
    }

    public function test_broadcast_message_read_signature(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'broadcastMessageRead');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('readerId', $params[0]->getName());
        $this->assertEquals('readerTenantId', $params[1]->getName());
        $this->assertEquals('senderUserId', $params[2]->getName());
        $this->assertEquals('senderTenantId', $params[3]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function test_auth_federation_channel_signature(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'authFederationChannel');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('channelName', $params[0]->getName());
        $this->assertEquals('socketId', $params[1]->getName());
        $this->assertEquals('userId', $params[2]->getName());
        $this->assertEquals('tenantId', $params[3]->getName());
    }

    // --- Broadcast returns false without Pusher config ---

    public function test_broadcast_new_message_returns_false_without_pusher(): void
    {
        $result = FederationRealtimeService::broadcastNewMessage(1, 2, 3, 4, ['body' => 'test']);
        $this->assertFalse($result);
    }

    public function test_broadcast_typing_returns_false_without_pusher(): void
    {
        $result = FederationRealtimeService::broadcastTyping(1, 2, 3, 4, true);
        $this->assertFalse($result);
    }

    public function test_broadcast_message_read_returns_false_without_pusher(): void
    {
        $result = FederationRealtimeService::broadcastMessageRead(1, 2, 3, 4);
        $this->assertFalse($result);
    }

    public function test_auth_federation_channel_returns_null_without_pusher(): void
    {
        $result = FederationRealtimeService::authFederationChannel(
            'private-federation.user.1.2',
            'socket123',
            1,
            2
        );
        $this->assertNull($result);
    }

    // --- Authorization logic ---

    public function test_is_user_authorized_for_own_channel(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'isUserAuthorizedForChannel');
        $ref->setAccessible(true);

        $channel = FederationRealtimeService::getUserFederationChannel(42, 3);
        $result = $ref->invoke(null, $channel, 42, 3);
        $this->assertTrue($result);
    }

    public function test_is_user_not_authorized_for_other_user_channel(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'isUserAuthorizedForChannel');
        $ref->setAccessible(true);

        $channel = FederationRealtimeService::getUserFederationChannel(42, 3);
        $result = $ref->invoke(null, $channel, 99, 3);
        $this->assertFalse($result);
    }

    public function test_is_user_authorized_for_conversation_channel(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'isUserAuthorizedForChannel');
        $ref->setAccessible(true);

        $channel = FederationRealtimeService::getConversationChannel(42, 3, 10, 5);

        // User 42 from tenant 3 should be authorized
        $this->assertTrue($ref->invoke(null, $channel, 42, 3));

        // User 10 from tenant 5 should be authorized
        $this->assertTrue($ref->invoke(null, $channel, 10, 5));

        // User 99 from tenant 1 should NOT be authorized
        $this->assertFalse($ref->invoke(null, $channel, 99, 1));
    }

    public function test_is_user_not_authorized_for_random_channel(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'isUserAuthorizedForChannel');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, 'private-some-other-channel', 42, 3);
        $this->assertFalse($result);
    }

    public function test_get_pusher_instance_is_private(): void
    {
        $ref = new \ReflectionMethod(FederationRealtimeService::class, 'getPusherInstance');
        $this->assertTrue($ref->isPrivate());
    }
}
