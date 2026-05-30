<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\UserRegistered;
use App\Listeners\SendWelcomeNotification;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class SendWelcomeNotificationTest extends TestCase
{
    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(SendWelcomeNotification::class))
        );
    }

    public function test_handle_creates_notification_and_sends_email(): void
    {
        $user = new User();
        $user->id = 42;
        $user->first_name = 'Alice';
        $user->email = 'alice@example.com';

        $event = new UserRegistered($user, 2);

        // Mock Notification::create
        $notificationMock = Mockery::mock('alias:' . Notification::class);
        $notificationMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['tenant_id'] === 2
                    && $data['user_id'] === 42
                    && $data['type'] === 'welcome'
                    && $data['link'] === '/feed'
                    && $data['is_read'] === false;
            }));

        // Mock TenantContext::get()
        Mockery::mock('alias:' . TenantContext::class)
            ->shouldReceive('get')
            ->andReturn(['name' => 'Test Timebank']);

        // Mock EmailService
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldReceive('send')
            ->once()
            ->with(
                'alice@example.com',
                'Welcome to Test Timebank!',
                Mockery::type('string')
            );

        $this->app->instance(EmailService::class, $emailServiceMock);

        $listener = new SendWelcomeNotification();
        $listener->handle($event);
    }

    public function test_handle_skips_email_when_no_email_provided(): void
    {
        $user = new User();
        $user->id = 42;
        $user->first_name = 'Alice';
        $user->email = null;

        $event = new UserRegistered($user, 2);

        $notificationMock = Mockery::mock('alias:' . Notification::class);
        $notificationMock->shouldReceive('create')->once();

        Mockery::mock('alias:' . TenantContext::class)
            ->shouldReceive('get')
            ->andReturn(['name' => 'Test']);

        // EmailService::send should NOT be called
        $emailServiceMock = Mockery::mock(EmailService::class);
        $emailServiceMock->shouldNotReceive('send');

        $this->app->instance(EmailService::class, $emailServiceMock);

        $listener = new SendWelcomeNotification();
        $listener->handle($event);
    }

    public function test_handle_catches_exceptions_and_logs_error(): void
    {
        $user = new User();
        $user->id = 42;

        $event = new UserRegistered($user, 2);

        $notificationMock = Mockery::mock('alias:' . Notification::class);
        $notificationMock->shouldReceive('create')
            ->andThrow(new \RuntimeException('DB error'));

        Log::shouldReceive('error')
            ->once()
            ->with('SendWelcomeNotification listener failed', Mockery::type('array'));

        $listener = new SendWelcomeNotification();
        $listener->handle($event);
    }

    /**
     * Regression guard for the 2026-04-02 email-bombing class: if a redis
     * re-delivery fires the listener again for the SAME registration, the
     * idempotency guard must short-circuit BEFORE any bell/email/token work.
     */
    public function test_handle_suppresses_duplicate_delivery_when_already_handled(): void
    {
        $user = new User();
        $user->id = 91;
        $user->first_name = 'Dup';
        $user->email = 'dup@example.com';
        $event = new UserRegistered($user, 2);

        // Simulate a prior successful delivery for tenant 2 / user 91.
        Cache::put('send_welcome:done:2:91', 1, now()->addHour());

        // The guard must log the suppression and return — never reaching the
        // welcome flow (which would otherwise log an error or touch the DB here).
        Log::shouldReceive('info')
            ->once()
            ->with('SendWelcomeNotification: duplicate delivery suppressed', Mockery::type('array'));
        Log::shouldReceive('error')->never();
        Log::shouldReceive('warning')->never();

        (new SendWelcomeNotification())->handle($event);

        // Reaching here without the welcome flow running proves the short-circuit.
        $this->assertTrue(Cache::has('send_welcome:done:2:91'));
    }
}
