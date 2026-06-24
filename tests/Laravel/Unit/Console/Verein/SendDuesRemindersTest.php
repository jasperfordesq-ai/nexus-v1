<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console\Verein;

use App\Core\TenantContext;
use App\Services\EmailDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for verein:send-dues-reminders (SendDuesReminders).
 *
 * Unique tenant id: 99728
 *
 * Logic under test (VereinDuesService::sendDueReminders):
 *   - Selects verein_member_dues where:
 *       status = 'overdue'
 *       AND reminder_count < 3
 *       AND (last_reminder_at IS NULL OR last_reminder_at < now() - 7 days)
 *   - For each row, sends an email via EmailDispatchService::sendRaw.
 *   - On success: increments reminder_count and stamps last_reminder_at.
 *   - On failure: stamps reminder_email_failed_at / reminder_email_last_error.
 *   - Pending / paid / waived rows are never reminded.
 *
 * EmailDispatchService is bound to a stub so no real SMTP calls are made.
 */
class SendDuesRemindersTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99728;

    private int $orgId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Verein SendReminders Test Tenant 99728',
                'slug'       => 'verein-reminder-99728',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein Reminder User 99728',
            'first_name' => 'Verein',
            'last_name'  => 'ReminderUser',
            'email'      => 'verein-reminder-99728@example-test.invalid',
            'status'     => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id'   => $this->userId,
            'name'      => 'Verein Reminder Club 99728',
            'org_type'  => 'club',
            'status'    => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    /** Returns a stub that makes EmailDispatchService::send() return $returnValue. */
    private function bindEmailStub(bool $returnValue): void
    {
        $stub = $this->createMock(EmailDispatchService::class);
        $stub->method('send')->willReturn($returnValue);
        $this->app->instance(EmailDispatchService::class, $stub);
    }

    /** Insert a dues row and return its id. */
    private function seedDues(
        string $status,
        int $reminderCount = 0,
        ?string $lastReminderAt = null,
        ?int $userIdOverride = null
    ): int {
        static $yearCounter = 2080;

        return DB::table('verein_member_dues')->insertGetId([
            'organization_id' => $this->orgId,
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userIdOverride ?? $this->userId,
            'membership_year' => $yearCounter++,
            'amount_cents'    => 8000,
            'currency'        => 'CHF',
            'status'          => $status,
            'due_date'        => now()->subDays(60)->toDateString(),
            'reminder_count'  => $reminderCount,
            'last_reminder_at' => $lastReminderAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Happy path: overdue row with no prior reminder → reminder sent
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sends_reminder_to_overdue_row_with_no_prior_reminder(): void
    {
        $this->bindEmailStub(true);

        $id = $this->seedDues('overdue', reminderCount: 0, lastReminderAt: null);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->reminder_count, 'reminder_count must be incremented to 1');
        $this->assertNotNull($row->last_reminder_at, 'last_reminder_at must be stamped');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Second reminder after 7 days
    // ─────────────────────────────────────────────────────────────────────────

    public function test_sends_second_reminder_after_seven_day_cadence(): void
    {
        $this->bindEmailStub(true);

        // last_reminder_at = 8 days ago → eligible for another reminder
        $lastReminder = now()->subDays(8)->toDateTimeString();
        $id = $this->seedDues('overdue', reminderCount: 1, lastReminderAt: $lastReminder);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row->reminder_count, 'reminder_count must be incremented to 2');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Row sent reminder within 7 days → skipped (cadence not yet elapsed)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_skips_reminder_within_seven_day_cadence(): void
    {
        $this->bindEmailStub(true);

        // last_reminder_at = 3 days ago → not yet 7 days → must be skipped
        $lastReminder = now()->subDays(3)->toDateTimeString();
        $id = $this->seedDues('overdue', reminderCount: 1, lastReminderAt: $lastReminder);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->reminder_count, 'reminder_count must NOT be incremented within 7-day window');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Max 3 reminders enforced (reminder_count = 3 → skipped)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_skips_row_that_already_has_three_reminders(): void
    {
        $this->bindEmailStub(true);

        $lastReminder = now()->subDays(10)->toDateTimeString(); // would be eligible by time
        $id = $this->seedDues('overdue', reminderCount: 3, lastReminderAt: $lastReminder);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(3, (int) $row->reminder_count, 'Max-3-reminder row must not be incremented further');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Paid rows are never reminded
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_remind_paid_rows(): void
    {
        $this->bindEmailStub(true);

        $id = $this->seedDues('paid', reminderCount: 0, lastReminderAt: null);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->reminder_count, 'Paid row must not receive a reminder');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pending rows are never reminded (only 'overdue' rows qualify)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_remind_pending_rows(): void
    {
        $this->bindEmailStub(true);

        $id = $this->seedDues('pending', reminderCount: 0, lastReminderAt: null);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->reminder_count, 'Pending row must not receive a reminder');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // When email send returns false → reminder_email_failed_at is stamped,
    //   reminder_count is NOT incremented.
    // ─────────────────────────────────────────────────────────────────────────

    public function test_stamps_failed_at_when_email_send_returns_false(): void
    {
        // Bind a stub that returns false to simulate send failure.
        $this->bindEmailStub(false);

        $id = $this->seedDues('overdue', reminderCount: 0, lastReminderAt: null);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->reminder_count, 'reminder_count must NOT increment on failed send');
        $this->assertNotNull($row->reminder_email_failed_at, 'reminder_email_failed_at must be stamped on failure');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Successful send clears any previous failure stamp
    // ─────────────────────────────────────────────────────────────────────────

    public function test_clears_failure_stamp_on_successful_resend(): void
    {
        $this->bindEmailStub(true);

        // Seed a row with a pre-existing failure stamp and no reminders yet.
        $id = DB::table('verein_member_dues')->insertGetId([
            'organization_id'         => $this->orgId,
            'tenant_id'               => self::TENANT_ID,
            'user_id'                 => $this->userId,
            'membership_year'         => 2095,
            'amount_cents'            => 8000,
            'currency'                => 'CHF',
            'status'                  => 'overdue',
            'due_date'                => now()->subDays(60)->toDateString(),
            'reminder_count'          => 0,
            'last_reminder_at'        => null,
            'reminder_email_failed_at' => now()->subDays(1)->toDateTimeString(),
            'reminder_email_last_error' => 'Previous transient failure',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->reminder_count, 'reminder_count must be incremented after successful resend');
        $this->assertNull($row->reminder_email_failed_at, 'reminder_email_failed_at must be cleared on success');
        $this->assertNull($row->reminder_email_last_error, 'reminder_email_last_error must be cleared on success');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Command exits 0 when there are no overdue rows at all
    // ─────────────────────────────────────────────────────────────────────────

    public function test_exits_success_when_no_overdue_rows(): void
    {
        $this->bindEmailStub(true);

        // No dues rows seeded — command must still return 0.
        $this->artisan('verein:send-dues-reminders')->assertExitCode(0);

        // Ensure no rows were created or modified for our tenant.
        $this->assertSame(0, DB::table('verein_member_dues')
            ->where('tenant_id', self::TENANT_ID)
            ->where('status', 'overdue')
            ->count(), 'No overdue rows should exist for this tenant');
    }
}
