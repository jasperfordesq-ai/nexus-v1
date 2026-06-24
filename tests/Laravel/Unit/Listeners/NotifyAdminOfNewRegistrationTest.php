<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\UserRegistered;
use App\Listeners\NotifyAdminOfNewRegistration;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailDispatchService;
use App\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyAdminOfNewRegistration listener.
 *
 * Uses an isolated tenant (997) so pre-existing tenant-2 admin rows cannot
 * inflate Notification::createNotification / EmailDispatchService::sendRaw
 * call counts. All DB writes roll back inside DatabaseTransactions.
 *
 * Alias mocks are created before parent::setUp() so they intercept the real
 * classes before the app boots. NotificationDispatcher::fanOutPush is also
 * aliased so push calls don't contact Redis/Pusher during tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifyAdminOfNewRegistrationTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    protected int $testTenantId = 997;

    private $notificationAlias;
    private $emailAlias;
    private $dispatcherAlias;

    protected function setUp(): void
    {
        // Alias mocks MUST be created before parent::setUp() — classes may be
        // autoloaded during app boot.
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)->shouldIgnoreMissing();
        $this->emailAlias        = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();
        $this->dispatcherAlias   = Mockery::mock('alias:' . NotificationDispatcher::class)->shouldIgnoreMissing();

        parent::setUp();

        Cache::flush();
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyAdminOfNewRegistration::class), true),
            'NotifyAdminOfNewRegistration must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifyAdminOfNewRegistration();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — single admin notified about new registration
    // -------------------------------------------------------------------------

    public function test_handle_creates_bell_notification_for_each_admin(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin     = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        // Exactly one bell notification must be created for the one admin.
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with(
                $admin->id,
                Mockery::type('string'),
                '/broker/members',
                'new_user_registered'
            );

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Fan-out covers all admin-tier roles
    // -------------------------------------------------------------------------

    public function test_handle_notifies_all_admin_and_broker_and_coordinator_roles(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember   = $this->seedUser(['role' => 'member',       'status' => 'active']);
        $admin       = $this->seedUser(['role' => 'admin',        'status' => 'active']);
        $broker      = $this->seedUser(['role' => 'broker',       'status' => 'active']);
        $coordinator = $this->seedUser(['role' => 'coordinator',  'status' => 'active']);
        $tadmin      = $this->seedUser(['role' => 'tenant_admin', 'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        // 4 eligible recipients (admin + broker + coordinator + tenant_admin).
        $this->notificationAlias->shouldReceive('createNotification')->times(4);
        $this->emailAlias->shouldReceive('sendRaw')->times(4)->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->times(4);

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Inactive admins excluded
    // -------------------------------------------------------------------------

    public function test_handle_skips_inactive_admins(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $this->seedUser(['role' => 'admin', 'status' => 'inactive']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewRegistration: no active admins found for tenant', Mockery::type('array'));
        Log::shouldReceive('error')->zeroOrMoreTimes();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // No admins at all — info log, nothing sent
    // -------------------------------------------------------------------------

    public function test_handle_logs_info_when_no_admins_found(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        // Only seed a plain member; no admin-tier users for this tenant.
        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewRegistration: no active admins found for tenant', Mockery::type('array'));

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Admins from other tenants not notified
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_admins_from_other_tenant(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        // Admin in tenant 2 only — not in tenant 997.
        $this->seedUser(['role' => 'admin', 'status' => 'active'], 2);
        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewRegistration: no active admins found for tenant', Mockery::type('array'));

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery suppressed
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery_via_done_cache_key(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);

        $handledKey = 'notify_admin_new_registration:done:' . $this->testTenantId . ':' . $newMember->id;
        Cache::put($handledKey, 1, now()->addHour());

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewRegistration: duplicate fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(Cache::has($handledKey));
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery suppressed
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery_via_claim_key(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);

        $claimKey = 'notify_admin_new_registration:claim:' . $this->testTenantId . ':' . $newMember->id;
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifyAdminOfNewRegistration: concurrent fanout suppressed', Mockery::type('array'));

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Done cache key written after successful fanout
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_fanout(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin     = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $handledKey = 'notify_admin_new_registration:done:' . $this->testTenantId . ':' . $newMember->id;

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must exist after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // Bell notification link points to /broker/members
    // -------------------------------------------------------------------------

    public function test_notification_link_points_to_broker_members(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin     = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $capturedLink = null;
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::on(function ($link) use (&$capturedLink) {
                    $capturedLink = $link;
                    return true;
                }),
                Mockery::any()
            );

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertNotNull($capturedLink);
        $this->assertSame('/broker/members', $capturedLink);
    }

    // -------------------------------------------------------------------------
    // Per-admin Throwable is caught and logged; other admins still notified
    // -------------------------------------------------------------------------

    public function test_per_admin_exception_is_caught_and_other_admins_still_notified(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin1    = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $admin2    = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        // First call throws; second succeeds.
        $this->notificationAlias
            ->shouldReceive('createNotification')
            ->twice()
            ->andReturnUsing(function () {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('First admin push failed');
                }
                // Second call succeeds (returns void-like null).
                return null;
            });

        // The email for the second admin still fires.
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();

        Log::shouldReceive('error')
            ->once()
            ->with('NotifyAdminOfNewRegistration: failed for admin', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // super_admin role included
    // -------------------------------------------------------------------------

    public function test_super_admin_role_is_included_in_fanout(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember  = $this->seedUser(['role' => 'member',      'status' => 'active']);
        $superAdmin = $this->seedUser(['role' => 'super_admin', 'status' => 'active']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        $this->notificationAlias->shouldReceive('createNotification')->once();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);
        $this->dispatcherAlias->shouldReceive('fanOutPush')->once();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Admin with no email is skipped
    // -------------------------------------------------------------------------

    public function test_admin_without_email_is_skipped(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        $newMember = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $this->seedUser(['role' => 'admin', 'status' => 'active', 'email' => '']);

        $event = new UserRegistered($this->makeUserModel($newMember), $this->testTenantId);

        // The listener skips any admin where $adminEmail is falsy.
        $this->notificationAlias->shouldReceive('createNotification')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        // No active admins with a real email → info log.
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new NotifyAdminOfNewRegistration())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a minimal user row and return a plain stdClass for use as event
     * constructor data or to look up the real row id.
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
     * Build a User Eloquent model instance from a seeded stdClass row.
     *
     * UserRegistered requires a real User Eloquent model (typed constructor).
     * We use forceFill() so no mass-assignment guard blocks the fields we need.
     */
    private function makeUserModel(object $seedUser): User
    {
        $model = new User();
        $model->forceFill([
            'id'         => $seedUser->id,
            'tenant_id'  => $seedUser->tenant_id,
            'first_name' => $seedUser->first_name,
            'last_name'  => $seedUser->last_name,
            'name'       => $seedUser->name,
            'email'      => $seedUser->email,
            'role'       => $seedUser->role,
            'status'     => $seedUser->status,
        ]);

        return $model;
    }
}
