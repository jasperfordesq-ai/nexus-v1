<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Gdpr;

use App\Events\GdprActionOccurred;
use App\Listeners\NotifyAdminOfGdprAction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the member-initiated GDPR → admin-notification wiring.
 *
 * Before this, a member submitting a data-rights request / deleting their
 * account / exporting their data / changing consent was SILENT to admins — the
 * request became a `pending` gdpr_requests row that only surfaced via a
 * dashboard count nobody had to look at, or the overdue-request cron once it was
 * already ~25 days old (near the Art.12(3) deadline).
 *
 * These tests lock two things:
 *   1. The real member endpoints dispatch {@see GdprActionOccurred}.
 *   2. {@see NotifyAdminOfGdprAction} turns that event into a bell for every
 *      admin (and only admins).
 */
class GdprAdminNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // The factory + GDPR erasure flow drift TenantContext; re-pin per test.
        \App\Core\TenantContext::setById($this->testTenantId);
    }

    private function makeMember(string $password = 'OldPassword123!'): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'role'          => 'member',
            'status'        => 'active',
            'is_approved'   => true,
            'password_hash' => Hash::make($password),
        ]);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function makeAdmin(string $role = 'admin'): User
    {
        return User::factory()->forTenant($this->testTenantId)->create([
            'role'   => $role,
            'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------- HTTP dispatch

    public function test_data_subject_request_dispatches_admin_notification_event(): void
    {
        Event::fake([GdprActionOccurred::class]);
        $member = $this->makeMember();

        $response = $this->apiPost('/v2/users/me/gdpr-request', ['type' => 'rectification']);

        $this->assertSame(201, $response->getStatusCode());
        Event::assertDispatched(
            GdprActionOccurred::class,
            fn (GdprActionOccurred $e): bool =>
                $e->action === GdprActionOccurred::ACTION_REQUEST
                && (int) $e->userId === (int) $member->id
                && $e->detail === 'rectification'
        );
    }

    public function test_account_deletion_dispatches_admin_notification_event(): void
    {
        Event::fake([GdprActionOccurred::class]);
        $member = $this->makeMember('OldPassword123!');

        $response = $this->apiDelete('/v2/users/me', ['password' => 'OldPassword123!']);

        $this->assertSame(200, $response->getStatusCode());
        Event::assertDispatched(
            GdprActionOccurred::class,
            fn (GdprActionOccurred $e): bool =>
                $e->action === GdprActionOccurred::ACTION_ACCOUNT_DELETION
                && (int) $e->userId === (int) $member->id
                // The display name must be carried on the event — by the time a
                // queued listener runs, the users row is already anonymised.
                && !empty($e->subjectName)
        );
    }

    public function test_data_export_dispatches_admin_notification_event(): void
    {
        Event::fake([GdprActionOccurred::class]);
        $member = $this->makeMember();

        $response = $this->apiPost('/v2/me/data-export', ['format' => 'json']);

        $this->assertSame(200, $response->getStatusCode());
        Event::assertDispatched(
            GdprActionOccurred::class,
            fn (GdprActionOccurred $e): bool =>
                $e->action === GdprActionOccurred::ACTION_DATA_EXPORT
                && (int) $e->userId === (int) $member->id
                && $e->detail === 'json'
        );
    }

    // --------------------------------------------------------- listener fan-out

    public function test_listener_writes_a_bell_to_every_admin_and_not_to_the_member(): void
    {
        $member = $this->makeMember();
        $adminA = $this->makeAdmin('admin');
        $adminB = $this->makeAdmin('tenant_admin');

        // An erasure REQUEST (still pending) — the compliance-critical case.
        $event = new GdprActionOccurred(
            (int) $member->id,
            $this->testTenantId,
            GdprActionOccurred::ACTION_REQUEST,
            'erasure',
            null,
            999999,
        );

        (new NotifyAdminOfGdprAction())->handle($event);

        foreach ([$adminA, $adminB] as $admin) {
            $bell = DB::table('notifications')
                ->where('user_id', $admin->id)
                ->where('tenant_id', $this->testTenantId)
                ->where('type', 'gdpr_request')
                ->first();

            $this->assertNotNull($bell, "admin {$admin->id} must receive a GDPR bell notification");
            $this->assertSame('/admin/enterprise/gdpr', $bell->link, 'bell must link to the admin GDPR queue');
        }

        // The member who made the request is not an admin — they get no alert.
        $this->assertSame(
            0,
            DB::table('notifications')->where('user_id', $member->id)->where('type', 'gdpr_request')->count(),
            'the requesting member must not receive an admin GDPR alert'
        );
    }
}
