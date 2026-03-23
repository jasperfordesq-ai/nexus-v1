<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\MessageSent;
use App\Listeners\NotifyMessageReceived;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class NotifyMessageReceivedTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyMessageReceived::class))
        );
    }

    public function test_handle_dispatches_notification_to_recipients(): void
    {
        $sender = new User();
        $sender->id = 10;
        $sender->first_name = 'Bob';

        $message = new Message();
        $conversationId = 42;

        $event = new MessageSent($message, $sender, $conversationId, 2);

        // Mock DB query for conversation participants
        DB::shouldReceive('table')
            ->with('conversation_participants')
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('conversation_id', 42)
            ->andReturnSelf();
        DB::shouldReceive('where')
            ->with('user_id', '!=', 10)
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('user_id')
            ->andReturn(collect([20, 30]));

        Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldReceive('dispatch')
            ->twice()
            ->with(
                Mockery::type('int'),
                'global',
                null,
                'new_message',
                'Bob sent you a message',
                '/messages/42',
                null
            );

        $listener = new NotifyMessageReceived();
        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $sender = new User();
        $sender->id = 10;
        $sender->first_name = 'Bob';

        $message = new Message();
        $event = new MessageSent($message, $sender, 42, 2);

        DB::shouldReceive('table')
            ->andThrow(new \RuntimeException('DB error'));

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyMessageReceived listener failed', Mockery::type('array'));

        $listener = new NotifyMessageReceived();
        $listener->handle($event);
    }
}
