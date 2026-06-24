<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console\Verein;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for verein:mark-overdue (MarkOverdueDues).
 *
 * Unique tenant id: 99729
 *
 * Logic under test (VereinDuesService::markOverdueDues):
 *   - Iterates ALL active verein_membership_fees (cross-tenant).
 *   - For each config computes: cutoff = today - grace_period_days.
 *   - Flips verein_member_dues status pending→overdue where due_date < cutoff.
 *   - Rows that are paid / waived / already overdue stay unchanged.
 *   - Rows whose due_date is in the future or within the grace window are untouched.
 */
class MarkOverdueDuesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99729;

    private int $orgId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'       => 'Verein MarkOverdue Test Tenant 99729',
                'slug'       => 'verein-overdue-99729',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein Overdue User 99729',
            'email'      => 'verein-overdue-99729@example-test.invalid',
            'status'     => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'user_id'   => $this->userId,
            'name'      => 'Verein Overdue Club 99729',
            'org_type'  => 'club',
            'status'    => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fee config with 30-day grace period.
        DB::table('verein_membership_fees')->updateOrInsert(
            ['organization_id' => $this->orgId],
            [
                'tenant_id'        => self::TENANT_ID,
                'fee_amount_cents' => 10000,
                'currency'         => 'CHF',
                'billing_cycle'    => 'annual',
                'grace_period_days' => 30,
                'is_active'        => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );

        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function seedDues(string $dueDate, string $status = 'pending', ?int $userId = null): int
    {
        static $yearCounter = 2070;

        return DB::table('verein_member_dues')->insertGetId([
            'organization_id' => $this->orgId,
            'tenant_id'       => self::TENANT_ID,
            'user_id'         => $userId ?? $this->userId,
            'membership_year' => $yearCounter++, // unique to avoid UNIQUE KEY clash
            'amount_cents'    => 10000,
            'currency'        => 'CHF',
            'status'          => $status,
            'due_date'        => $dueDate,
            'reminder_count'  => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Happy path: pending row past grace period → flipped to overdue
    // ─────────────────────────────────────────────────────────────────────────

    public function test_flips_pending_to_overdue_past_grace_period(): void
    {
        // grace_period_days=30 → cutoff = today - 30 days
        // due_date = 60 days ago → well past cutoff → should become overdue
        $dueDate = now()->subDays(60)->toDateString();
        $id = $this->seedDues($dueDate, 'pending');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('overdue', $row->status, 'Pending row past grace period must be flipped to overdue');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Boundary: row exactly AT the grace cutoff — must NOT be flipped
    //   due_date = today - grace_period_days  →  NOT < cutoff (equal), so keep pending
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_flip_row_at_exact_grace_cutoff(): void
    {
        // cutoff = today - 30 days; due_date exactly equals cutoff → not strictly less
        $dueDate = now()->subDays(30)->toDateString();
        $id = $this->seedDues($dueDate, 'pending');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status, 'Row at exact cutoff boundary must remain pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Row within grace window (due date is recent) → stays pending
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_flip_row_within_grace_window(): void
    {
        // due_date = today - 10 days → inside 30-day grace window
        $dueDate = now()->subDays(10)->toDateString();
        $id = $this->seedDues($dueDate, 'pending');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status, 'Row within grace window must remain pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Row with future due date → stays pending
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_flip_future_due_date_row(): void
    {
        $dueDate = now()->addDays(60)->toDateString();
        $id = $this->seedDues($dueDate, 'pending');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status, 'Future-dated row must remain pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Paid rows are never flipped to overdue
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_flip_paid_rows(): void
    {
        $dueDate = now()->subDays(60)->toDateString();
        $id = $this->seedDues($dueDate, 'paid');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('paid', $row->status, 'Paid row must not be flipped to overdue');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Waived rows are never flipped to overdue
    // ─────────────────────────────────────────────────────────────────────────

    public function test_does_not_flip_waived_rows(): void
    {
        $dueDate = now()->subDays(60)->toDateString();
        $id = $this->seedDues($dueDate, 'waived');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('waived', $row->status, 'Waived row must not be flipped to overdue');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Already-overdue rows are untouched
    // ─────────────────────────────────────────────────────────────────────────

    public function test_already_overdue_rows_are_untouched(): void
    {
        $dueDate = now()->subDays(60)->toDateString();
        $id = $this->seedDues($dueDate, 'overdue');

        $updatedBefore = DB::table('verein_member_dues')->where('id', $id)->value('updated_at');

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $row = DB::table('verein_member_dues')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('overdue', $row->status, 'Already-overdue row status must stay overdue');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mixed: only the past-grace row is flipped; recent one stays pending
    // ─────────────────────────────────────────────────────────────────────────

    public function test_flips_only_past_grace_rows_in_mixed_set(): void
    {
        $pastDueDate   = now()->subDays(60)->toDateString(); // past grace
        $recentDueDate = now()->subDays(5)->toDateString();  // within grace

        // Need two distinct users to avoid UNIQUE KEY clash on (organization_id, user_id, membership_year)
        $user2Id = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Verein Overdue User2 99729',
            'email'      => 'verein-overdue2-99729@example-test.invalid',
            'status'     => 'active',
            'preferred_language' => 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldId    = $this->seedDues($pastDueDate, 'pending', $this->userId);
        $recentId = $this->seedDues($recentDueDate, 'pending', $user2Id);

        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        $oldRow    = DB::table('verein_member_dues')->where('id', $oldId)->first();
        $recentRow = DB::table('verein_member_dues')->where('id', $recentId)->first();

        $this->assertSame('overdue', $oldRow->status, 'Past-grace row must be overdue');
        $this->assertSame('pending', $recentRow->status, 'Within-grace row must remain pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Command always exits 0 even when no rows are affected
    // ─────────────────────────────────────────────────────────────────────────

    public function test_exits_success_when_no_rows_are_affected(): void
    {
        // No dues rows seeded — command should still succeed.
        $this->artisan('verein:mark-overdue')->assertExitCode(0);

        // At least one assertion required.
        $this->assertSame(0, DB::table('verein_member_dues')
            ->where('tenant_id', self::TENANT_ID)
            ->where('status', 'overdue')
            ->count(), 'No rows should have been flipped');
    }
}
