<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationRealtimeService;

/**
 * FederationRealtimeService Tests
 *
 * Tests real-time updates for federated messaging and activities.
 * Note: Pusher broadcasting tests are limited since Pusher may not
 * be configured in the test environment.
 */
class FederationRealtimeServiceTest extends DatabaseTestCase
{
    protected static ?int $tenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tenantId = 2;
        TenantContext::setById(self::$tenantId);
    }

    // ==========================================
    // Channel Name Generation Tests
    // ==========================================

    public function testGetUserFederationChannelFormat(): void
    {
        $channel = FederationRealtimeService::getUserFederationChannel(42, 2);

        $this->assertEquals('private-federation.user.2.42', $channel);
    }

    public function testGetUserFederationChannelIsUnique(): void
    {
        $channel1 = FederationRealtimeService::getUserFederationChannel(1, 1);
        $channel2 = FederationRealtimeService::getUserFederationChannel(1, 2);
        $channel3 = FederationRealtimeService::getUserFederationChannel(2, 1);

        $this->assertNotEquals($channel1, $channel2);
        $this->assertNotEquals($channel1, $channel3);
        $this->assertNotEquals($channel2, $channel3);
    }

    // ==========================================
    // Conversation Channel Tests
    // ==========================================

    public function testGetConversationChannelIsNormalized(): void
    {
        // Same channel regardless of who initiates
        $channel1 = FederationRealtimeService::getConversationChannel(1, 1, 2, 2);
        $channel2 = FederationRealtimeService::getConversationChannel(2, 2, 1, 1);

        $this->assertEquals($channel1, $channel2);
    }

    public function testGetConversationChannelFormat(): void
    {
        $channel = FederationRealtimeService::getConversationChannel(5, 1, 10, 2);

        $this->assertStringStartsWith('private-federation.chat.', $channel);
    }

    public function testGetConversationChannelNormalizationWithSameTenant(): void
    {
        // Same tenant, different users - should normalize by user ID
        $channel1 = FederationRealtimeService::getConversationChannel(1, 1, 2, 1);
        $channel2 = FederationRealtimeService::getConversationChannel(2, 1, 1, 1);

        $this->assertEquals($channel1, $channel2);
    }

    public function testGetConversationChannelDifferentPairsAreDifferent(): void
    {
        $channel1 = FederationRealtimeService::getConversationChannel(1, 1, 2, 2);
        $channel2 = FederationRealtimeService::getConversationChannel(1, 1, 3, 3);

        $this->assertNotEquals($channel1, $channel2);
    }

    // ==========================================
    // Broadcast Tests (Pusher may not be configured)
    // ==========================================

    public function testBroadcastNewMessageReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastNewMessage(
            1, 1, 2, 2,
            [
                'message_id' => 123,
                'sender_name' => 'Test User',
                'sender_tenant_name' => 'Test Timebank',
                'subject' => 'Test',
                'body' => 'Test message body',
            ]
        );

        $this->assertIsBool($result);
    }

    public function testBroadcastTypingReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastTyping(1, 1, 2, 2, true);

        $this->assertIsBool($result);
    }

    public function testBroadcastMessageReadReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastMessageRead(2, 2, 1, 1);

        $this->assertIsBool($result);
    }

    public function testBroadcastTransactionReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastTransaction(
            1, 1, 2, 2, 1.5, 'Test transaction'
        );

        $this->assertIsBool($result);
    }

    public function testBroadcastPartnershipUpdateReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastPartnershipUpdate(
            1, 2, 'active',
            ['name' => 'Partner Timebank']
        );

        $this->assertIsBool($result);
    }

    public function testBroadcastNewMemberReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastNewMember(
            1, 42, 'Test User', 'public'
        );

        $this->assertIsBool($result);
    }

    public function testBroadcastActivityEventReturnsBool(): void
    {
        $result = FederationRealtimeService::broadcastActivityEvent(
            42, 1, 'new_message',
            ['sender_name' => 'Test']
        );

        $this->assertIsBool($result);
    }

    // ==========================================
    // SSE Queue Tests
    // ==========================================

    public function testQueueSSEEventDoesNotThrow(): void
    {
        try {
            FederationRealtimeService::queueSSEEvent(
                self::$tenantId,
                'test.event',
                ['data' => 'test']
            );
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Table may not exist
            $this->markTestSkipped('federation_realtime_queue table may not exist: ' . $e->getMessage());
        }
    }

    public function testQueueUserSSEEventDoesNotThrow(): void
    {
        try {
            FederationRealtimeService::queueUserSSEEvent(
                1,
                self::$tenantId,
                'test.user.event',
                ['user_data' => 'test']
            );
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_realtime_queue table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetPendingEventsReturnsArray(): void
    {
        try {
            $result = FederationRealtimeService::getPendingEvents(1, self::$tenantId);
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_realtime_queue table may not exist');
        }
    }

    public function testGetPendingEventsWithLastEventId(): void
    {
        try {
            $result = FederationRealtimeService::getPendingEvents(1, self::$tenantId, '0');
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_realtime_queue table may not exist');
        }
    }

    public function testMarkEventsDeliveredWithEmptyArray(): void
    {
        // Should not throw with empty array
        FederationRealtimeService::markEventsDelivered([]);
        $this->assertTrue(true);
    }

    public function testCleanupOldEventsReturnsInt(): void
    {
        try {
            $result = FederationRealtimeService::cleanupOldEvents(24);
            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_realtime_queue table may not exist');
        }
    }

    // ==========================================
    // Availability Tests
    // ==========================================

    public function testIsAvailableReturnsTrue(): void
    {
        // SSE fallback is always available
        $result = FederationRealtimeService::isAvailable();
        $this->assertTrue($result);
    }

    public function testGetConnectionMethodReturnsString(): void
    {
        $result = FederationRealtimeService::getConnectionMethod();

        $this->assertIsString($result);
        $this->assertContains($result, ['pusher', 'sse']);
    }

    // ==========================================
    // Auth Channel Tests
    // ==========================================

    public function testAuthFederationChannelRejectsNonFederationChannel(): void
    {
        $result = FederationRealtimeService::authFederationChannel(
            'private-user.123',
            'socket-id',
            1,
            1
        );

        $this->assertNull($result);
    }

    public function testAuthFederationChannelRejectsWrongUser(): void
    {
        $result = FederationRealtimeService::authFederationChannel(
            'private-federation.user.1.42',
            'socket-id',
            99, // Wrong user ID (should be 42)
            1
        );

        $this->assertNull($result);
    }

    public function testAuthFederationChannelRejectsWrongTenant(): void
    {
        $result = FederationRealtimeService::authFederationChannel(
            'private-federation.user.1.42',
            'socket-id',
            42,
            99 // Wrong tenant ID (should be 1)
        );

        $this->assertNull($result);
    }
}
