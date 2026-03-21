<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Events;

use App\Events\ConnectionRequested;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\Laravel\TestCase;

class ConnectionRequestedTest extends TestCase
{
    public function test_instantiation_stores_properties(): void
    {
        $connection = new Connection();
        $requester = new User();
        $requester->id = 10;
        $target = new User();
        $target->id = 20;
        $tenantId = 2;

        $event = new ConnectionRequested($connection, $requester, $target, $tenantId);

        $this->assertSame($connection, $event->connection);
        $this->assertSame($requester, $event->requester);
        $this->assertSame($target, $event->target);
        $this->assertSame(2, $event->tenantId);
    }

    public function test_implements_should_broadcast(): void
    {
        $this->assertTrue(
            is_subclass_of(ConnectionRequested::class, ShouldBroadcast::class)
            || in_array(ShouldBroadcast::class, class_implements(ConnectionRequested::class))
        );
    }

    public function test_broadcast_on_returns_private_channel_for_target_user(): void
    {
        $connection = new Connection();
        $requester = new User();
        $requester->id = 10;
        $target = new User();
        $target->id = 20;

        $event = new ConnectionRequested($connection, $requester, $target, 5);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('tenant.5.user.20', $channels[0]->name);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $connection = new Connection();
        $requester = new User();
        $requester->id = 1;
        $target = new User();
        $target->id = 2;

        $event = new ConnectionRequested($connection, $requester, $target, 1);

        $this->assertEquals('connection.requested', $event->broadcastAs());
    }
}
