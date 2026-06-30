<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\DonationAdminNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * DonationAdminNotificationServiceTest
 *
 * Strategy mirrors SupportReportNotificationServiceTest:
 * - notifyDonationReceived is the sole public method; it writes a bell
 *   notification and emails every active admin of the donation's tenant.
 * - Real DB rows for tenant/user/notifications so adminRecipients() resolves.
 * - EmailDispatchService::sendRaw / NotificationDispatcher::fanOutPush are
 *   fire-and-forget in test env; we assert that the method does not throw and
 *   that a bell Notification row is written for an admin (the regression this
 *   feature exists to prevent: admins previously got NO donation notice).
 * - The donation argument is a plain stdClass (the service reads it as the
 *   webhook success handler passes the vol_donations row) — no donation DB row
 *   is required.
 * - Queue::fake() prevents observer-triggered jobs from resetting TenantContext.
 */
class DonationAdminNotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeDonation(array $overrides = []): object
    {
        $donation = new \stdClass();
        $donation->id          = $overrides['id']          ?? rand(900000, 999999);
        $donation->tenant_id   = $overrides['tenant_id']   ?? self::TENANT_ID;
        $donation->amount      = $overrides['amount']      ?? 5.00;
        $donation->currency    = $overrides['currency']    ?? 'EUR';
        $donation->donor_name  = $overrides['donor_name']  ?? 'Jane Donor';
        $donation->donor_email = $overrides['donor_email'] ?? 'jane.donor@example.test';
        $donation->is_anonymous = $overrides['is_anonymous'] ?? 0;
        $donation->message     = $overrides['message']     ?? 'Keep up the great work';
        $donation->fund_code   = $overrides['fund_code']   ?? 'community';

        return $donation;
    }

    private function insertAdminUser(string $role = 'admin'): User
    {
        $uid = uniqid('adm', true);
        $id  = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Admin ' . $uid,
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'email'       => $uid . '@admin.test',
            'status'      => 'active',
            'role'        => $role,
            'balance'     => 0,
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return User::withoutGlobalScopes()->find($id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // notifyDonationReceived — does not throw
    // ─────────────────────────────────────────────────────────────────────

    public function test_notifyDonationReceived_does_not_throw(): void
    {
        DonationAdminNotificationService::notifyDonationReceived($this->makeDonation());
        $this->addToAssertionCount(1);
    }

    public function test_notifyDonationReceived_does_not_throw_for_anonymous(): void
    {
        DonationAdminNotificationService::notifyDonationReceived(
            $this->makeDonation(['is_anonymous' => 1])
        );
        $this->addToAssertionCount(1);
    }

    public function test_notifyDonationReceived_ignores_missing_tenant(): void
    {
        // tenant_id 0 must be a no-op, not an exception.
        DonationAdminNotificationService::notifyDonationReceived(
            $this->makeDonation(['tenant_id' => 0])
        );
        $this->addToAssertionCount(1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // notifyDonationReceived — bell notification IS written for admins
    // (the regression guard: admins must now be notified of donations)
    // ─────────────────────────────────────────────────────────────────────

    public function test_notifyDonationReceived_creates_bell_notification_for_admin(): void
    {
        $admin = $this->insertAdminUser('admin');

        $before = DB::table('notifications')->where('user_id', $admin->id)->count();

        DonationAdminNotificationService::notifyDonationReceived($this->makeDonation());

        $after = DB::table('notifications')->where('user_id', $admin->id)->count();

        $this->assertGreaterThan(
            $before,
            $after,
            'An admin should receive a bell notification when a donation is received'
        );
    }

    public function test_notifyDonationReceived_creates_bell_notification_for_is_admin_user(): void
    {
        // role='member' but is_admin=1 should still be treated as an admin.
        $uid = uniqid('isa', true);
        $id  = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'IsAdmin ' . $uid,
            'first_name'  => 'IsAdmin',
            'last_name'   => 'User',
            'email'       => $uid . '@isadmin.test',
            'status'      => 'active',
            'role'        => 'member',
            'is_admin'    => 1,
            'balance'     => 0,
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $before = DB::table('notifications')->where('user_id', $id)->count();

        DonationAdminNotificationService::notifyDonationReceived($this->makeDonation());

        $after = DB::table('notifications')->where('user_id', $id)->count();

        $this->assertGreaterThan($before, $after);
    }

    public function test_notifyDonationReceived_does_not_throw_when_tenant_has_no_admins(): void
    {
        $tempTenantId = 97011;
        DB::table('tenants')->insertOrIgnore([
            'id'                => $tempTenantId,
            'name'              => 'NoAdminDonationTenant',
            'slug'              => 'no-admin-don-' . $tempTenantId,
            'is_active'         => 1,
            'depth'             => 1,
            'parent_id'         => 1,
            'path'              => '/1/' . $tempTenantId . '/',
            'allows_subtenants' => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        DonationAdminNotificationService::notifyDonationReceived(
            $this->makeDonation(['tenant_id' => $tempTenantId])
        );

        $this->addToAssertionCount(1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // donorDisplay — private, tested via reflection
    // ─────────────────────────────────────────────────────────────────────

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionClass(DonationAdminNotificationService::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    public function test_donorDisplay_returns_name_for_named_donor(): void
    {
        $result = $this->invokePrivate('donorDisplay', $this->makeDonation(['donor_name' => 'Pat Murphy', 'is_anonymous' => 0]));
        $this->assertSame('Pat Murphy', $result);
    }

    public function test_donorDisplay_appends_note_for_anonymous_named_donor(): void
    {
        $result = $this->invokePrivate('donorDisplay', $this->makeDonation(['donor_name' => 'Pat Murphy', 'is_anonymous' => 1]));
        $this->assertStringContainsString('Pat Murphy', $result);
        // The anonymous note is appended (translated, so just assert it grew).
        $this->assertNotSame('Pat Murphy', $result);
    }

    public function test_donorDisplay_falls_back_when_no_name(): void
    {
        $result = $this->invokePrivate('donorDisplay', $this->makeDonation(['donor_name' => '', 'is_anonymous' => 1]));
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ─────────────────────────────────────────────────────────────────────
    // fundLabel — private, tested via reflection
    // ─────────────────────────────────────────────────────────────────────

    public function test_fundLabel_returns_null_for_general(): void
    {
        $this->assertNull($this->invokePrivate('fundLabel', $this->makeDonation(['fund_code' => 'general'])));
    }

    public function test_fundLabel_returns_null_for_empty(): void
    {
        $this->assertNull($this->invokePrivate('fundLabel', $this->makeDonation(['fund_code' => ''])));
    }

    public function test_fundLabel_capitalises_custom_fund(): void
    {
        $this->assertSame('Hardship', $this->invokePrivate('fundLabel', $this->makeDonation(['fund_code' => 'hardship'])));
    }

    // ─────────────────────────────────────────────────────────────────────
    // formatAmount — private, tested via reflection
    // ─────────────────────────────────────────────────────────────────────

    public function test_formatAmount_formats_with_currency(): void
    {
        $result = $this->invokePrivate('formatAmount', $this->makeDonation(['amount' => 5, 'currency' => 'eur']));
        $this->assertSame('5.00 EUR', $result);
    }
}
