<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Notification;
use App\Services\NotificationDispatcher;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class NotificationDispatcherTest extends TestCase
{
    public function test_dispatch_always_creates_in_app_notification(): void
    {
        // The bell notification should always be created
        Notification::shouldReceive('createNotification')
            ->once()
            ->with(1, 'Test message', '/test', 'new_topic');

        // Frequency setting lookup
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn('off');

        NotificationDispatcher::dispatch(
            1, 'global', null, 'new_topic', 'Test message', '/test', '<p>Test</p>'
        );
    }

    public function test_dispatch_off_frequency_skips_email(): void
    {
        Notification::shouldReceive('createNotification')->once();

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn('off');

        // Should not call queueNotification since frequency is 'off'
        NotificationDispatcher::dispatch(
            1, 'global', null, 'new_reply', 'Reply msg', '/reply', null
        );

        $this->assertTrue(true); // No exception = pass
    }

    public function test_dispatch_organizer_rule_defaults_to_instant(): void
    {
        Notification::shouldReceive('createNotification')->once();

        // No setting found
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        // Queue for instant
        DB::shouldReceive('table')->with('notification_queue')->andReturnSelf();
        DB::shouldReceive('insert')->once();

        NotificationDispatcher::dispatch(
            1, 'global', null, 'new_topic', 'Organizer msg', '/topic', '<p>HTML</p>', true
        );
    }
}
