<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Events;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\Laravel\TestCase;

class MessageSentTest extends TestCase
{
    public function test_instantiation_stores_properties(): void
    {
        $message = new Message();
        $sender = new User();
        $sender->id = 10;
        $conversationId = 42;
        $tenantId = 2;

        $event = new MessageSent($message, $sender, $conversationId, $tenantId);

        $this->assertSame($message, $event->message);
        $this->assertSame($sender, $event->sender);
        $this->assertSame(42, $event->conversationId);
        $this->assertSame(2, $event->tenantId);
    }

    public function test_implements_should_broadcast(): void
    {
        $this->assertTrue(
            in_array(ShouldBroadcast::class, class_implements(MessageSent::class))
        );
    }

    public function test_broadcast_on_returns_private_conversation_channel(): void
    {
        $message = new Message();
        $sender = new User();
        $sender->id = 1;

        $event = new MessageSent($message, $sender, 99, 4);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('tenant.4.conversation.99', $channels[0]->name);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $message = new Message();
        $sender = new User();
        $sender->id = 1;

        $event = new MessageSent($message, $sender, 1, 1);

        $this->assertEquals('message.sent', $event->broadcastAs());
    }
}
