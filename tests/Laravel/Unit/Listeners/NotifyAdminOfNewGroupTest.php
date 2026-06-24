<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\GroupCreated;
use App\Listeners\NotifyAdminOfNewGroup;
use App\Models\Group;
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
 * Tests for NotifyAdminOfNewGroup listener.
 *
 * Uses an isolated tenant (998) so no pre-existing production/staging rows
 * from tenant 2 bleed into assertion counts.
 * All tests roll back inside DatabaseTransactions.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyAdminOfNewGroupTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * Use tenant 998 — isolated from production tenant 2 and the
     * safeguarding tests' tenant 999.
     */
    protected int $testTenantId = 998;

    private $notificationAlias;
    private $emailAlias;
    private $dispatcherAlias;

    protected function setUp(): void
    {
        // Alias mocks MUST be created before parent::setUp() — the classes may
        // already be autoloaded during app boot. shouldIgnoreMissing() silences
        // unexpected static calls (e.g. fanOutPush) from other code paths.
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)->shouldIgnoreMissing();
        $this->emailAlias        = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();
        $this->dispatcherAlias   = Mockery::mock('alias:' . NotificationDispatcher::class)->shouldIgnoreMissing();

        parent::setUp();

        // Ensure tenant 998 exists for TenantContext::setById().
        DB::table('tenants')->updateOrInsert(
            ['id' => 998],
            [
                'name'               => 'Group Test Tenant',
                'slug'               => 'test-998',
                'domain'             => null,
                'is_active'          => true,
                'depth'              => 0,
                'allows_subtenants'  => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        // Cache idempotency guard persists across methods in the same PHP process
        // (array store). Flush so no prior "done" key short-circuits handle().
        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyAdminOfNewGroup::class)),
            'NotifyAdminOfNewGroup must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifyAdminOfNewGroup();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — single admin
    // -------------------------------------------------------------------------

    public function test_handle_sends_notification_and_email_to_admin(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with(
                $admin->id,
                Mockery::type('string'),
                '/groups/' . $group->id,
                'new_group_created'
            );

        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->with(
                $admin->email,
                Mockery::type('string'),
                Mockery::type('string'),
                null, null, null,
                'admin_new_group',
                Mockery::type('array')
            )
            ->andReturn(true);

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Fan-out to multiple admin roles
    // -------------------------------------------------------------------------

    public function test_handle_notifies_all_eligible_roles(): void
    {
        $admin     = $this->seedUser(['role' => 'admin',        'status' => 'active']);
        $broker    = $this->seedUser(['role' => 'broker',       'status' => 'active']);
        $tadmin    = $this->seedUser(['role' => 'tenant_admin', 'status' => 'active']);
        $coord     = $this->seedUser(['role' => 'coordinator',  'status' => 'active']);
        $sadmin    = $this->seedUser(['role' => 'super_admin',  'status' => 'active']);
        $group     = $this->seedGroup(['owner_id' => $admin->id]);

        $event = $this->makeGroupCreatedEvent($group);

        // Five eligible users → 5 notifications + 5 emails.
        $this->notificationAlias->shouldReceive('createNotification')->times(5);
        $this->emailAlias->shouldReceive('sendRaw')->times(5)->andReturn(true);

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Inactive staff excluded
    // -------------------------------------------------------------------------

    public function test_handle_skips_inactive_admins(): void
    {
        $placeholder = $this->seedUser(['role' => 'admin', 'status' => 'inactive']);
        $group = $this->seedGroup(['owner_id' => $placeholder->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Other-tenant admins excluded
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_admins_from_other_tenants(): void
    {
        // Seed an admin in tenant 2 — must not receive notifications for tenant 998 events.
        $this->seedUser(['role' => 'admin', 'status' => 'active'], 2);
        // Group needs a valid owner FK; seed a member in tenant 998 for that purpose.
        $owner = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $owner->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Members excluded
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_plain_members(): void
    {
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $group  = $this->seedGroup(['owner_id' => $member->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery suppressed (done key present)
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $handledKey = 'notify_admin_new_group:done:' . $this->testTenantId . ':' . $group->id;
        Cache::put($handledKey, 1, now()->addHour());

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewGroup: duplicate fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(Cache::has($handledKey));
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery suppressed (claim key held by other worker)
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $claimKey = 'notify_admin_new_group:claim:' . $this->testTenantId . ':' . $group->id;
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewGroup: concurrent fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Done-cache written after successful fanout
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_fanout(): void
    {
        $admin      = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group      = $this->seedGroup(['owner_id' => $admin->id]);
        $handledKey = 'notify_admin_new_group:done:' . $this->testTenantId . ':' . $group->id;

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must exist after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // Notification bell content contains the group name
    // -------------------------------------------------------------------------

    public function test_notification_bell_links_to_group(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $capturedLink = null;
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->withArgs(function ($userId, $content, $link, $type) use ($group, &$capturedLink) {
                $capturedLink = $link;
                return true;
            });

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertNotNull($capturedLink);
        $this->assertStringContainsString('/groups/' . $group->id, $capturedLink);
    }

    // -------------------------------------------------------------------------
    // Email send failure is logged as warning (listener swallows, continues)
    // -------------------------------------------------------------------------

    public function test_email_failure_is_logged_as_warning(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $event = $this->makeGroupCreatedEvent($group);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        // sendRaw returns false → listener logs warning but does NOT re-throw.
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        Log::shouldReceive('warning')
            ->once()
            ->with('NotifyAdminOfNewGroup: email send failed', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Should complete without throwing.
        (new NotifyAdminOfNewGroup())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Exception inside the loop is caught and logged as error
    // -------------------------------------------------------------------------

    public function test_exception_is_caught_and_logged_as_error(): void
    {
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $admin->id]);

        $event = $this->makeGroupCreatedEvent($group);

        // Force an exception inside the per-admin LocaleContext block.
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->andThrow(new \RuntimeException('Notification store is down'));

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyAdminOfNewGroup listener failed', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        // Listener swallows the Throwable — must not propagate.
        (new NotifyAdminOfNewGroup())->handle($event);

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
        $adminNoEmail   = $this->seedUser(['role' => 'admin', 'status' => 'active', 'email' => '']);
        $adminWithEmail = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $group = $this->seedGroup(['owner_id' => $adminWithEmail->id]);

        $event = $this->makeGroupCreatedEvent($group);

        // Only the admin WITH an email should trigger a notification + email.
        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifyAdminOfNewGroup())->handle($event);

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
     * Seed a minimal group row and return a Group Eloquent model instance.
     * The listener accesses $event->group->id, ->owner_id, ->name.
     */
    private function seedGroup(array $overrides = []): Group
    {
        $unique = uniqid('g_', true);

        $data = array_merge([
            'tenant_id'  => $this->testTenantId,
            'owner_id'   => 0,
            'name'       => 'Test Group ' . $unique,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        $id = DB::table('groups')->insertGetId($data);

        $group = new Group();
        $group->id       = $id;
        $group->tenant_id = $data['tenant_id'];
        $group->owner_id = $data['owner_id'];
        $group->name     = $data['name'];

        return $group;
    }

    /**
     * Build a GroupCreated event for the test tenant and group.
     */
    private function makeGroupCreatedEvent(Group $group): GroupCreated
    {
        return new GroupCreated($group, $this->testTenantId);
    }
}
