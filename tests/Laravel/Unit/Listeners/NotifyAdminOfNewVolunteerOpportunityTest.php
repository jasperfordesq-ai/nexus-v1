<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\VolunteerOpportunityCreated;
use App\Listeners\NotifyAdminOfNewVolunteerOpportunity;
use App\Models\VolOpportunity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for NotifyAdminOfNewVolunteerOpportunity listener.
 *
 * Lock-safe pattern: no @runTestsInSeparateProcesses, no Mockery alias: mocks.
 * EmailDispatchService::sendRaw() and NotificationDispatcher::fanOutPush() are
 * allowed to run — under the test env (MAIL_MAILER=array, no Pusher/FCM) they
 * no-op or are caught by the listener's try/catch.
 *
 * The durable side-effect asserted is the `notifications` table row written by
 * Notification::createNotification() for each tenant admin. All inserts roll back
 * inside DatabaseTransactions, so there are no lingering InnoDB row locks between
 * test methods.
 *
 * Tenant 99655 is used so no row collides with the shared tenant-2, tenant-999,
 * tenant-998, or tenant-997 fixtures used by sibling test files.
 */
class NotifyAdminOfNewVolunteerOpportunityTest extends TestCase
{
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /** Unique tenant for this file — avoids all cross-file row collisions. */
    protected int $testTenantId = 99655;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue so any dispatched jobs do not actually hit the queue worker.
        Queue::fake();

        // Flush the array cache so no prior done/claim keys short-circuit handle().
        Cache::flush();

        // Ensure our test tenant row exists (DatabaseTransactions rolls it back after
        // each test, but setUpTenantContext() only seeds 2 and 999; we need 99655).
        DB::table('tenants')->updateOrInsert(
            ['id' => $this->testTenantId],
            [
                'name'               => 'Test Vol Opp Tenant',
                'slug'               => 'test-vol-opp-99655',
                'domain'             => null,
                'is_active'          => true,
                'depth'              => 0,
                'allows_subtenants'  => false,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]
        );

        TenantContext::setById($this->testTenantId);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    public function test_implements_should_queue(): void
    {
        $this->assertTrue(
            in_array(ShouldQueue::class, class_implements(NotifyAdminOfNewVolunteerOpportunity::class), true),
            'NotifyAdminOfNewVolunteerOpportunity must implement ShouldQueue'
        );
    }

    public function test_tries_is_one_and_timeout_is_sixty(): void
    {
        $listener = new NotifyAdminOfNewVolunteerOpportunity();
        $this->assertSame(1, $listener->tries);
        $this->assertSame(60, $listener->timeout);
    }

    // -------------------------------------------------------------------------
    // Happy path — single admin receives a bell notification row
    // -------------------------------------------------------------------------

    public function test_handle_creates_notification_row_for_admin(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp    = $this->seedOpportunity($poster->id, 'Teach Coding');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $row = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('type', 'new_vol_opp_created')
            ->first();

        $this->assertNotNull($row, 'A notification row must exist for the admin');
        $this->assertStringContainsString((string) $opp->id, $row->link ?? '',
            'Notification link must contain the opportunity id');
    }

    // -------------------------------------------------------------------------
    // Link format: /volunteering/opportunities/{id}
    // -------------------------------------------------------------------------

    public function test_notification_link_contains_opportunity_path(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp    = $this->seedOpportunity($poster->id, 'Garden Helpers');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $link = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('type', 'new_vol_opp_created')
            ->value('link');

        $this->assertNotNull($link);
        $this->assertStringContainsString('/volunteering/opportunities/' . $opp->id, $link);
    }

    // -------------------------------------------------------------------------
    // Bell content contains the opportunity title
    // -------------------------------------------------------------------------

    public function test_notification_message_contains_opportunity_title(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin  = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp    = $this->seedOpportunity($poster->id, 'Unique Title XYZ789');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $message = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('type', 'new_vol_opp_created')
            ->value('message');

        $this->assertNotNull($message);
        $this->assertStringContainsString('Unique Title XYZ789', $message);
    }

    // -------------------------------------------------------------------------
    // Fan-out — all eligible admin roles each get a notification row
    // -------------------------------------------------------------------------

    public function test_handle_fans_out_to_all_eligible_roles(): void
    {
        $poster      = $this->seedUser(['role' => 'member',      'status' => 'active']);
        $admin       = $this->seedUser(['role' => 'admin',       'status' => 'active']);
        $broker      = $this->seedUser(['role' => 'broker',      'status' => 'active']);
        $coordinator = $this->seedUser(['role' => 'coordinator', 'status' => 'active']);
        $tenantAdmin = $this->seedUser(['role' => 'tenant_admin','status' => 'active']);
        $opp         = $this->seedOpportunity($poster->id, 'Fix Community Bus');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $count = DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('type', 'new_vol_opp_created')
            ->where('link', 'like', '%/volunteering/opportunities/' . $opp->id . '%')
            ->count();

        // admin + broker + coordinator + tenant_admin = 4 eligible roles
        $this->assertSame(4, $count, '4 notification rows expected for 4 eligible admin-tier roles');
    }

    // -------------------------------------------------------------------------
    // Inactive admins are excluded — no notification row created
    // -------------------------------------------------------------------------

    public function test_handle_skips_inactive_admins(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $this->seedUser(['role' => 'admin', 'status' => 'inactive']);
        $opp    = $this->seedOpportunity($poster->id, 'Help With Painting');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $count = DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('type', 'new_vol_opp_created')
            ->count();

        $this->assertSame(0, $count, 'Inactive admins must not receive a notification row');
    }

    // -------------------------------------------------------------------------
    // Admins from other tenants are NOT notified
    // -------------------------------------------------------------------------

    public function test_handle_does_not_notify_admins_from_other_tenant(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        // Admin seeded into tenant 2, not our tenant-99655.
        $this->seedUser(['role' => 'admin', 'status' => 'active'], 2);
        $opp    = $this->seedOpportunity($poster->id, 'Cross-Tenant Guard');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        // No notification rows for tenant 99655 (other-tenant admin must not be notified).
        $count = DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('type', 'new_vol_opp_created')
            ->count();

        $this->assertSame(0, $count, 'Admins from another tenant must not receive a notification row');
    }

    // -------------------------------------------------------------------------
    // Idempotency — duplicate delivery (done key) suppressed, no extra rows
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_duplicate_delivery_via_done_key(): void
    {
        $poster     = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin      = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp        = $this->seedOpportunity($poster->id, 'Duplicate Guard Test');
        $handledKey = 'notify_admin_new_vol_opp:done:' . $this->testTenantId . ':' . $opp->id;

        // Simulate that the done key is already set (first delivery already ran).
        Cache::put($handledKey, 1, now()->addHour());

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        // The second call must not insert any notification rows.
        $count = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('type', 'new_vol_opp_created')
            ->count();

        $this->assertSame(0, $count, 'Duplicate delivery must not create additional notification rows');
        $this->assertTrue(Cache::has($handledKey), 'Done key must still be present after early return');
    }

    // -------------------------------------------------------------------------
    // Idempotency — concurrent delivery (claim key) suppressed, no extra rows
    // -------------------------------------------------------------------------

    public function test_handle_suppresses_concurrent_delivery_via_claim_key(): void
    {
        $poster   = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin    = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp      = $this->seedOpportunity($poster->id, 'Concurrent Guard Test');
        $claimKey = 'notify_admin_new_vol_opp:claim:' . $this->testTenantId . ':' . $opp->id;

        // Simulate another worker holding the claim.
        Cache::put($claimKey, 1, now()->addMinutes(5));

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $count = DB::table('notifications')
            ->where('user_id', $admin->id)
            ->where('type', 'new_vol_opp_created')
            ->count();

        $this->assertSame(0, $count, 'Concurrent delivery must not create notification rows');
    }

    // -------------------------------------------------------------------------
    // Done cache key is written after a successful fanout
    // -------------------------------------------------------------------------

    public function test_handle_writes_done_cache_key_after_successful_fanout(): void
    {
        $poster     = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin      = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp        = $this->seedOpportunity($poster->id, 'Cache-Write Test');
        $handledKey = 'notify_admin_new_vol_opp:done:' . $this->testTenantId . ':' . $opp->id;

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $this->assertTrue(Cache::has($handledKey), 'Done cache key must be set after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // Claim key is released in the finally block (even when no exception)
    // -------------------------------------------------------------------------

    public function test_handle_releases_claim_key_after_fanout(): void
    {
        $poster   = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $admin    = $this->seedUser(['role' => 'admin',  'status' => 'active']);
        $opp      = $this->seedOpportunity($poster->id, 'Claim Release Test');
        $claimKey = 'notify_admin_new_vol_opp:claim:' . $this->testTenantId . ':' . $opp->id;

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        // The finally block must have deleted the claim key.
        $this->assertFalse(Cache::has($claimKey), 'Claim key must be released in the finally block after a successful fanout');
    }

    // -------------------------------------------------------------------------
    // No admins — no notification rows, listener completes silently
    // -------------------------------------------------------------------------

    public function test_handle_does_nothing_when_no_admins_exist(): void
    {
        $poster = $this->seedUser(['role' => 'member', 'status' => 'active']);
        $opp    = $this->seedOpportunity($poster->id, 'No Admin Test');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $count = DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('type', 'new_vol_opp_created')
            ->count();

        $this->assertSame(0, $count, 'No notification rows must be created when no admins exist for the tenant');
    }

    // -------------------------------------------------------------------------
    // super_admin role is included in the fanout
    // -------------------------------------------------------------------------

    public function test_super_admin_role_is_included_in_fanout(): void
    {
        $poster     = $this->seedUser(['role' => 'member',      'status' => 'active']);
        $superAdmin = $this->seedUser(['role' => 'super_admin', 'status' => 'active']);
        $opp        = $this->seedOpportunity($poster->id, 'Super Admin Test');

        $event = new VolunteerOpportunityCreated($opp, $this->testTenantId);
        (new NotifyAdminOfNewVolunteerOpportunity())->handle($event);

        $row = DB::table('notifications')
            ->where('user_id', $superAdmin->id)
            ->where('type', 'new_vol_opp_created')
            ->first();

        $this->assertNotNull($row, 'super_admin must receive a notification row');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a minimal user row and return a plain stdClass matching what the
     * listener's raw DB::table('users') query returns.
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
     * Insert a real vol_opportunities row and return a VolOpportunity model
     * instance that mirrors what the listener accesses ($opportunity->id,
     * $opportunity->title, $opportunity->user_id).
     *
     * The listener reads $opportunity->user_id for the poster-name lookup.
     * The schema column is `created_by`, so we set both on the model.
     */
    private function seedOpportunity(int $userId, string $title = 'Test Opportunity'): VolOpportunity
    {
        $id = DB::table('vol_opportunities')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'title'       => $title,
            'description' => 'Test description',
            'status'      => 'open',
            'is_active'   => 1,
            'created_by'  => $userId,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $opp             = new VolOpportunity();
        $opp->id         = $id;
        $opp->tenant_id  = $this->testTenantId;
        $opp->title      = $title;
        $opp->description = 'Test description';
        // The listener accesses $opportunity->user_id for the poster lookup;
        // the schema column is created_by, so assign it dynamically here.
        $opp->user_id    = $userId;
        $opp->created_by = $userId;
        $opp->status     = 'open';
        $opp->is_active  = true;

        return $opp;
    }
}
