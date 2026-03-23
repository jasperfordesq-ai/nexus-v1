<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\ConnectionRequested;
use App\Listeners\NotifyConnectionRequest;
use App\Models\Connection;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class NotifyConnectionRequestTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyConnectionRequest::class))
        );
    }

    public function test_handle_dispatches_notification_to_target_user(): void
    {
        $requester = new User();
        $requester->id = 10;
        $requester->first_name = 'Alice';

        $target = new User();
        $target->id = 20;

        $connection = new Connection();

        $event = new ConnectionRequested($connection, $requester, $target, 2);

        // Mock the static NotificationDispatcher::dispatch call
        Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                20,
                'global',
                null,
                'connection_request',
                'Alice sent you a connection request',
                '/connections',
                null
            );

        $listener = new NotifyConnectionRequest();
        $listener->handle($event);
    }

    public function test_handle_uses_fallback_name_when_first_name_is_null(): void
    {
        $requester = new User();
        $requester->id = 10;
        // first_name and name both null

        $target = new User();
        $target->id = 20;

        $connection = new Connection();

        $event = new ConnectionRequested($connection, $requester, $target, 2);

        Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->with(
                20,
                'global',
                null,
                'connection_request',
                'Someone sent you a connection request',
                '/connections',
                null
            );

        $listener = new NotifyConnectionRequest();
        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $requester = new User();
        $requester->id = 10;
        $requester->first_name = 'Alice';

        $target = new User();
        $target->id = 20;

        $connection = new Connection();
        $event = new ConnectionRequested($connection, $requester, $target, 2);

        Mockery::mock('alias:' . NotificationDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->andThrow(new \RuntimeException('Test failure'));

        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->with('NotifyConnectionRequest listener failed', Mockery::type('array'));

        $listener = new NotifyConnectionRequest();
        // Should not throw
        $listener->handle($event);
    }
}
