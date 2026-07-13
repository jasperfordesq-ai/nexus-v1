<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Events\SafeguardingFlaggedEvent;
use App\Listeners\NotifySafeguardingStaff;
use App\Models\Notification;
use App\Models\User;
use App\Services\EmailDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifySafeguardingStaff listener.
 *
 * Uses an isolated tenant (999) that is pre-seeded by TestCase::setUpTenantContext()
 * so no pre-existing production/staging rows from tenant 2 bleed into assertion counts.
 * All tests roll back inside DatabaseTransactions.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NotifySafeguardingStaffTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /**
     * Use the isolated tenant-999 for all tests so pre-existing tenant-2 admin rows
     * cannot inflate Notification::create / EmailDispatchService::sendRaw call counts.
     */
    protected int $testTenantId = 999;

    private $notificationAlias;
    private $emailAlias;

    protected function setUp(): void
    {
        // Alias mocks MUST be created before parent::setUp() — the classes may
        // already be autoloaded during app boot. shouldIgnoreMissing() silences
        // unexpected static calls from boot; per-test expectations are layered on.
        $this->notificationAlias = Mockery::mock('alias:' . Notification::class)->shouldIgnoreMissing();
        $this->emailAlias        = Mockery::mock('alias:' . EmailDispatchService::class)->shouldIgnoreMissing();

        parent::setUp();

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
            in_array(ShouldQueue::class, class_implements(NotifySafeguardingStaff::class)),
            'NotifySafeguardingStaff must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifySafeguardingStaff();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — single admin user
    // -------------------------------------------------------------------------

    public function test_handle_creates_notification_for_each_admin(): void
    {
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        $this->notificationAlias
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($admin, $member) {
                return (int) $data['user_id']   === $admin->id
                    && (int) $data['tenant_id'] === $this->testTenantId
                    && $data['type']            === 'safeguarding_flag'
                    && str_contains((string) $data['link'], (string) $member->id);
            }));

        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->with(
                $admin->email,
                Mockery::type('string'),
                Mockery::type('string'),
                null, null, null,
                'safeguarding',
                Mockery::type('array')
            )
            ->andReturn(true);

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    public function test_handle_omits_false_checkbox_from_staff_summary(): void
    {
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin = $this->seedUser(['role' => 'admin', 'status' => 'active']);
        $hiddenLabel = 'False safeguarding option must not reach staff summary';
        $optionId = DB::table('tenant_safeguarding_options')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'option_key' => 'false_staff_summary_' . uniqid(),
            'option_type' => 'checkbox',
            'label' => $hiddenLabel,
            'is_active' => 1,
            'sort_order' => 1,
            'triggers' => json_encode(['notify_admin_on_selection' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_safeguarding_preferences')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'option_id' => $optionId,
            'selected_value' => '0',
            'consent_given_at' => now(),
            'created_at' => now(),
        ]);

        $this->notificationAlias->shouldReceive('create')->once();
        $this->emailAlias
            ->shouldReceive('sendRaw')
            ->once()
            ->with(
                $admin->email,
                Mockery::type('string'),
                Mockery::on(static fn (string $html): bool => ! str_contains($html, $hiddenLabel)),
                null,
                null,
                null,
                'safeguarding',
                Mockery::type('array'),
            )
            ->andReturn(true);

        (new NotifySafeguardingStaff())->handle(new SafeguardingFlaggedEvent(
            $member->id,
            $this->testTenantId,
            ['notify_admin_on_selection' => true],
        ));

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Fan-out to multiple staff
    // -------------------------------------------------------------------------

    public function test_handle_notifies_all_admin_and_broker_roles(): void
    {
        $member  = $this->seedUser(['role' => 'member',       'status' => 'active']);
        $admin   = $this->seedUser(['role' => 'admin',        'status' => 'active']);
        $broker  = $this->seedUser(['role' => 'broker',       'status' => 'active']);
        $tadmin  = $this->seedUser(['role' => 'tenant_admin', 'status' => 'active']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        // Expect one notification and one email per eligible staff (3 eligible users).
        $this->notificationAlias->shouldReceive('create')->times(3);
        $this->emailAlias->shouldReceive('sendRaw')->times(3)->andReturn(true);

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Inactive staff are excluded
    // -------------------------------------------------------------------------

    public function test_handle_skips_inactive_staff(): void
    {
        $member        = $this->seedUser(['role' => 'member', 'status' => 'active']);
        // Only an inactive admin — should not be found by the listener.
        $this->seedUser(['role' => 'admin', 'status' => 'inactive']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        $this->notificationAlias->shouldReceive('create')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('warning')
            ->once()
            ->with('NotifySafeguardingStaff: no admin/broker users found for tenant', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // No staff at all for this tenant
    // -------------------------------------------------------------------------

    public function test_handle_logs_warning_when_no_staff_found(): void
    {
        // Only seed a plain member — no admins/brokers.
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        $this->notificationAlias->shouldReceive('create')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('warning')
            ->once()
            ->with('NotifySafeguardingStaff: no admin/broker users found for tenant', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery suppressed
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery(): void
    {
        $member       = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $triggers     = ['notify_admin'];
        $triggersHash = md5(json_encode($triggers));
        $handledKey   = 'notify_safeguarding_staff:done:' . $this->testTenantId . ':' . $member->id . ':' . $triggersHash;

        Cache::put($handledKey, 1, now()->addHour());

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, $triggers);

        $this->notificationAlias->shouldReceive('create')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifySafeguardingStaff: duplicate delivery suppressed', Mockery::type('array'));
        Log::shouldReceive('warning')->never();

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(Cache::has($handledKey));
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery suppressed
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery(): void
    {
        $member       = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $triggers     = ['notify_admin'];
        $triggersHash = md5(json_encode($triggers));
        $claimKey     = 'notify_safeguarding_staff:claim:' . $this->testTenantId . ':' . $member->id . ':' . $triggersHash;

        // Simulate another worker holding the claim.
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, $triggers);

        $this->notificationAlias->shouldReceive('create')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('info')
            ->once()
            ->with('NotifySafeguardingStaff: concurrent delivery suppressed', Mockery::type('array'));

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Done-cache written after successful fanout
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_fanout(): void
    {
        $member       = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin        = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $triggers     = ['notify_admin'];
        $triggersHash = md5(json_encode($triggers));
        $handledKey   = 'notify_safeguarding_staff:done:' . $this->testTenantId . ':' . $member->id . ':' . $triggersHash;

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, $triggers);

        $this->notificationAlias->shouldReceive('create')->once()->andReturnNull();
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must exist after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // Notification link contains the flagged user id
    // -------------------------------------------------------------------------

    public function test_notification_link_points_to_safeguarding_admin_panel(): void
    {
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        $capturedData = null;
        $this->notificationAlias
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use (&$capturedData) {
                $capturedData = $data;
                return true;
            }));

        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(true);

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertNotNull($capturedData);
        $this->assertStringContainsString('/broker/safeguarding', $capturedData['link']);
        $this->assertStringContainsString((string) $member->id, $capturedData['link']);
    }

    // -------------------------------------------------------------------------
    // Email failure re-throws (safeguarding is legally critical)
    // -------------------------------------------------------------------------

    public function test_handle_rethrows_when_email_send_returns_false(): void
    {
        $member = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        $this->notificationAlias->shouldReceive('create')->once()->andReturnNull();
        // sendRaw returns false → sendEmail() raises RuntimeException → listener re-throws.
        $this->emailAlias->shouldReceive('sendRaw')->once()->andReturn(false);

        // The listener logs critical (inside sendEmail), then logs error (in catch),
        // then re-throws so the job is marked failed.
        Log::shouldReceive('critical')->atLeast()->once();
        Log::shouldReceive('error')
            ->once()
            ->with('NotifySafeguardingStaff: failed to notify staff', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\RuntimeException::class);

        (new NotifySafeguardingStaff())->handle($event);
    }

    // -------------------------------------------------------------------------
    // failed() logs critical
    // -------------------------------------------------------------------------

    public function test_failed_logs_critical(): void
    {
        $event     = new SafeguardingFlaggedEvent(99, $this->testTenantId, ['notify_admin']);
        $exception = new \RuntimeException('Queue failure');

        Log::shouldReceive('critical')
            ->once()
            ->with('NotifySafeguardingStaff: PERMANENTLY FAILED', Mockery::type('array'));

        (new NotifySafeguardingStaff())->failed($event, $exception);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Staff from OTHER tenant are NOT notified
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_staff_from_other_tenant(): void
    {
        // Member belongs to tenant 999 (this test's tenant).
        $member     = $this->seedUser(['role' => 'member', 'status' => 'active']);
        // Admin belongs to tenant 2 — must not receive notification from tenant 999 event.
        $this->seedUser(['role' => 'admin', 'status' => 'active'], 2);

        $event = new SafeguardingFlaggedEvent($member->id, $this->testTenantId, ['notify_admin']);

        // No staff for tenant 999 → warning logged, nothing sent.
        $this->notificationAlias->shouldReceive('create')->never();
        $this->emailAlias->shouldReceive('sendRaw')->never();

        Log::shouldReceive('warning')
            ->once()
            ->with('NotifySafeguardingStaff: no admin/broker users found for tenant', Mockery::type('array'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        (new NotifySafeguardingStaff())->handle($event);

        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a minimal user row directly and return a plain object matching the
     * stdClass rows the listener reads from its raw DB::select().
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
}
