<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\CommunityEventCreated;
use App\Listeners\NotifyAdminOfNewCommunityEvent;
use App\Models\Event as CommunityEventModel;
use App\Models\Notification;
use App\Services\EmailDispatchService;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyAdminOfNewCommunityEvent listener.
 *
 * Uses an isolated tenant (997) so no pre-existing rows from tenant 2, 998,
 * or 999 bleed into assertion counts.
 * All tests roll back inside DatabaseTransactions.
 *
 * NOTE: The events table uses `user_id` as the organiser column, not
 * `created_by`. The listener reads `$communityEvent->created_by`, which will
 * be null/0 on all test rows, so the creator falls back to "A member". This is
 * the actual listener behaviour — tests assert around it rather than hiding it.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyAdminOfNewCommunityEventTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * Isolated tenant — no overlap with production (2), safeguarding (999),
     * group (998), or other listener tests.
     */
    protected int $testTenantId = 997;

    private $notificationAlias;
    private $emailAlias;
    private $dispatcherAlias;

    protected function setUp(): void
    {
        // Alias mocks MUST be created before parent::setUp() — the classes may
        // already be autoloaded during app boot.
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)->shouldIgnoreMissing();
        $this->emailAlias        = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();
        $this->dispatcherAlias   = Mockery::mock('alias:' . NotificationDispatcher::class)->shouldIgnoreMissing();

        parent::setUp();

        // Ensure tenant 997 exists for TenantContext::setById().
        DB::table('tenants')->updateOrInsert(
            ['id' => 997],
            [
                'name'               => 'Event Test Tenant',
                'slug'               => 'test-997',
                'domain'             => null,
                'is_active'          => true,
                'depth'              => 0,
                'allows_subtenants'  => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        // Cache idempotency guard persists in the array store between methods.
        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyAdminOfNewCommunityEvent::class)),
            'NotifyAdminOfNewCommunityEvent must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifyAdminOfNewCommunityEvent();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — single admin
    // -------------------------------------------------------------------------

    public function test_handle_sends_notification_and_email_to_admin(): void
    {
        $admin         = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with(
                $admin->id,
                Mockery::type('string'),
                '/events/' . $communityEvent->id,
                'new_event_created'
            );

        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->with(
                $admin->email,
                Mockery::type('string'),
                Mockery::type('string'),
                null, null, null,
                'admin_new_event',
                Mockery::type('array')
            )
            ->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Fan-out to all eligible roles
    // -------------------------------------------------------------------------

    public function test_handle_notifies_all_eligible_roles(): void
    {
        $admin   = $this->seedUser(['role' => 'admin',        'status' => 'active']);
        $broker  = $this->seedUser(['role' => 'broker',       'status' => 'active']);
        $tadmin  = $this->seedUser(['role' => 'tenant_admin', 'status' => 'active']);
        $coord   = $this->seedUser(['role' => 'coordinator',  'status' => 'active']);
        $sadmin  = $this->seedUser(['role' => 'super_admin',  'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        // 5 eligible users → 5 notifications + 5 emails.
        $this->notificationAlias->shouldReceive('createNotification')->times(5);
        $this->emailAlias->shouldReceive('sendRaw')->times(5)->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Inactive admins excluded
    // -------------------------------------------------------------------------

    public function test_handle_skips_inactive_admins(): void
    {
        $this->seedUser(['role' => 'admin', 'status' => 'inactive']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Other-tenant admins excluded
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_admins_from_other_tenants(): void
    {
        // Admin in tenant 2 must not be notified of tenant 997 events.
        $this->seedUser(['role' => 'admin', 'status' => 'active'], 2);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Plain members excluded
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_plain_members(): void
    {
        $this->seedUser(['role' => 'member', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery suppressed (done key present)
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $handledKey = 'notify_admin_new_event:done:' . $this->testTenantId . ':' . $communityEvent->id;
        Cache::put($handledKey, 1, now()->addHour());

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewCommunityEvent: duplicate fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(Cache::has($handledKey));
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery suppressed (claim key held by other worker)
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $claimKey = 'notify_admin_new_event:claim:' . $this->testTenantId . ':' . $communityEvent->id;
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewCommunityEvent: concurrent fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Done-cache written after successful fanout
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_fanout(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();
        $handledKey     = 'notify_admin_new_event:done:' . $this->testTenantId . ':' . $communityEvent->id;

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must exist after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // Notification bell links to the event
    // -------------------------------------------------------------------------

    public function test_notification_bell_links_to_event(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $capturedLink = null;
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->withArgs(function ($userId, $content, $link, $type) use (&$capturedLink) {
                $capturedLink = $link;
                return true;
            });

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertNotNull($capturedLink);
        $this->assertStringContainsString('/events/' . $communityEvent->id, $capturedLink);
    }

    // -------------------------------------------------------------------------
    // Email failure is logged as warning (listener swallows, continues)
    // -------------------------------------------------------------------------

    public function test_email_failure_is_logged_as_warning(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        Log::shouldReceive('warning')
            ->once()
            ->with('NotifyAdminOfNewCommunityEvent: email send failed', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Should complete without throwing.
        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception is caught and logged as error
    // -------------------------------------------------------------------------

    public function test_exception_is_caught_and_logged_as_error(): void
    {
        $admin          = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->andThrow(new \RuntimeException('Notification store is down'));

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyAdminOfNewCommunityEvent listener failed', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Listener swallows the Throwable — must not propagate.
        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Admin with no email is skipped gracefully
    // -------------------------------------------------------------------------

    public function test_admin_with_no_email_is_skipped(): void
    {
        // users.email is NOT NULL — use empty string to simulate missing email.
        // The listener guards: if (!$adminEmail) { continue; }
        // An empty string is falsy and satisfies that guard.
        $this->seedUser(['role' => 'admin', 'status' => 'active', 'email' => '']);
        $adminWithEmail = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $communityEvent = $this->seedEvent();

        $domainEvent = $this->makeCommunityEventCreated($communityEvent);

        // Only the admin WITH an email gets a notification + email.
        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewCommunityEvent())->handle($domainEvent);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a minimal user row directly.
     */
    private function seedUser(array $overrides = [], ?int $tenantId = null): object
    {
        $tenantId = $tenantId ?? $this->testTenantId;
        $unique   = uniqid('u_', true);

        $data = array_merge([
            'tenant_id'          => $tenantId,
            'name'               => 'Test User ' . $unique,
            'first_name'         => 'Test',
            'last_name'          => 'User',
            'email'              => $unique . '@example.com',
            'role'               => 'member',
            'status'             => 'active',
            'preferred_language' => 'en',
            'is_approved'        => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $overrides);

        $id = DB::table('users')->insertGetId($data);

        return (object) array_merge($data, ['id' => $id]);
    }

    /**
     * Seed a minimal community event row.
     *
     * NOTE: the `events` table uses `user_id` as the organiser — there is no
     * `created_by` column. The listener reads `$communityEvent->created_by`,
     * so it will always be null on test rows, and creator falls back to
     * "A member". Tests assert around this actual behaviour.
     */
    private function seedEvent(array $overrides = []): CommunityEventModel
    {
        $unique = uniqid('e_', true);

        $data = array_merge([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => 0,
            'title'       => 'Test Event ' . $unique,
            'description' => 'A test community event.',
            'start_time'  => now()->addDay(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides);

        $id = DB::table('events')->insertGetId($data);

        $model = new CommunityEventModel();
        $model->id        = $id;
        $model->tenant_id = $data['tenant_id'];
        $model->user_id   = $data['user_id'];
        $model->title     = $data['title'];

        return $model;
    }

    /**
     * Build a CommunityEventCreated domain event for the test tenant.
     */
    private function makeCommunityEventCreated(CommunityEventModel $communityEvent): CommunityEventCreated
    {
        return new CommunityEventCreated($communityEvent, $this->testTenantId);
    }
}
