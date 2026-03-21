<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Events;

use App\Events\TransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\Laravel\TestCase;

class TransactionCompletedTest extends TestCase
{
    public function test_instantiation_stores_properties(): void
    {
        $transaction = new Transaction();
        $sender = new User();
        $sender->id = 10;
        $receiver = new User();
        $receiver->id = 20;
        $tenantId = 2;

        $event = new TransactionCompleted($transaction, $sender, $receiver, $tenantId);

        $this->assertSame($transaction, $event->transaction);
        $this->assertSame($sender, $event->sender);
        $this->assertSame($receiver, $event->receiver);
        $this->assertSame(2, $event->tenantId);
    }

    public function test_implements_should_broadcast(): void
    {
        $this->assertTrue(
            in_array(ShouldBroadcast::class, class_implements(TransactionCompleted::class))
        );
    }

    public function test_broadcast_on_returns_channels_for_both_users(): void
    {
        $transaction = new Transaction();
        $sender = new User();
        $sender->id = 10;
        $receiver = new User();
        $receiver->id = 20;

        $event = new TransactionCompleted($transaction, $sender, $receiver, 3);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertEquals('tenant.3.user.10', $channels[0]->name);
        $this->assertEquals('tenant.3.user.20', $channels[1]->name);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $transaction = new Transaction();
        $sender = new User();
        $sender->id = 1;
        $receiver = new User();
        $receiver->id = 2;

        $event = new TransactionCompleted($transaction, $sender, $receiver, 1);

        $this->assertEquals('transaction.completed', $event->broadcastAs());
    }
}
