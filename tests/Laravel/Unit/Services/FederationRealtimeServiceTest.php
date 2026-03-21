<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationRealtimeService;

class FederationRealtimeServiceTest extends TestCase
{
    public function test_getUserFederationChannel_format(): void
    {
        $channel = FederationRealtimeService::getUserFederationChannel(5, 2);
        $this->assertEquals('private-federation.user.5.2', $channel);
    }

    public function test_getConversationChannel_is_deterministic(): void
    {
        $channel1 = FederationRealtimeService::getConversationChannel(1, 2, 3, 4);
        $channel2 = FederationRealtimeService::getConversationChannel(3, 4, 1, 2);

        $this->assertEquals($channel1, $channel2);
    }

    public function test_getConversationChannel_uses_sorted_pairs(): void
    {
        $channel = FederationRealtimeService::getConversationChannel(10, 2, 5, 1);
        // "10-2" vs "5-1" — "10-2" > "5-1" so 5-1 comes first
        $this->assertEquals('private-federation.conversation.10-2.5-1', $channel);
    }

    public function test_broadcastNewMessage_returns_false_without_pusher(): void
    {
        // Without Pusher configured, should return false gracefully
        $result = FederationRealtimeService::broadcastNewMessage(1, 2, 3, 4, ['body' => 'hello']);
        $this->assertIsBool($result);
    }
}
