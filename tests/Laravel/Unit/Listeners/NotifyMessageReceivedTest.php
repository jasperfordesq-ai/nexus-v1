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

    public function test_handle_dispatches_notification_to_recipient(): void
    {
        // Messages are now 1-to-1 direct messages: the recipient is the
        // Message->receiver_id, not a list of conversation_participants. The
        // listener resolves the recipient locale, builds the HTML email, then
        // dispatches a single notification (link points at the SENDER's thread).
        $sender = new User();
        $sender->id = 10;
        $sender->first_name = 'Bob';

        $message = new Message();
        $message->receiver_id = 20;
        $message->body = 'Hello there';
        $message->is_voice = false;

        // conversationId arg is retained on the event but the recipient is the receiver_id.
        $event = new MessageSent($message, $sender, 42, $this->testTenantId);

        // The HTML email is built via the real EmailTemplateBuilder against the
        // test tenant context; only the dispatcher call is asserted. Link is the
        // sender's thread: /messages/{senderId}. Content renders via emails.message.in_app_content.
        Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                20,
                'global',
                null,
                'new_message',
                'Bob sent you a message',
                '/messages/10',
                Mockery::type('string')
            )
            ->andReturn(true);

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
