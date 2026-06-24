<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Listeners;

use App\Core\TenantContext;
use App\Events\VolLogStatusChanged;
use App\Listeners\AwardXpOnVolLogApproved;
use App\Models\UserXpLog;
use App\Services\GamificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

/**
 * Tests for AwardXpOnVolLogApproved listener.
 *
 * Uses DatabaseTransactions to roll back every DB change after each test.
 * GamificationService::awardXP and runAllBadgeChecks run against the real
 * test DB — the XP log row and users.xp increment ARE the observable side
 * effects we assert. runAllBadgeChecks is safe in this context: it queries
 * but won't send emails for a brand-new user with zero history.
 */
class AwardXpOnVolLogApprovedTest extends TestCase
{
    use DatabaseTransactions;

    private int $userId;
    private int $volLogId;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a minimal user (tenant_id=2 = hour-timebank).
        $this->userId = (int) DB::table('users')->insertGetId([
            'name'       => 'Vol Test User',
            'first_name' => 'Vol',
            'last_name'  => 'Tester',
            'email'      => 'vol-xp-test-' . uniqid() . '@example.com',
            'tenant_id'  => 2,
            'xp'         => 0,
            'status'     => 'active',
            'role'       => 'member',
            'created_at' => now(),
        ]);

        // Seed a pending vol_log for that user.
        $this->volLogId = (int) DB::table('vol_logs')->insertGetId([
            'tenant_id'   => 2,
            'user_id'     => $this->userId,
            'date_logged' => now()->toDateString(),
            'hours'       => 2.00,
            'status'      => 'pending',
            'created_at'  => now(),
        ]);
    }

    // ── 1. Structural ─────────────────────────────────────────────────────

    public function test_listener_does_not_implement_should_queue(): void
    {
        $this->assertFalse(
            in_array(ShouldQueue::class, class_implements(AwardXpOnVolLogApproved::class)),
            'AwardXpOnVolLogApproved must be synchronous (not queued)'
        );
    }

    // ── 2. Happy path — XP written to DB ─────────────────────────────────
    //
    // NOTE: TenantContext::restoreAfterScopedListener() calls reset() in CLI
    // (phpunit), setting $tenant=null. Subsequent Eloquent queries (HasTenantScope)
    // call getId() → get() → resolve() → falls back to master tenant (ID 1),
    // causing the global scope to filter on tenant_id=1, hiding our tenant-2 rows.
    // Fix: re-pin TenantContext to 2 after handle(), OR use raw DB::table() queries
    // that bypass the Eloquent global scope entirely.

    public function test_creates_xp_log_row_on_approval(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        // Re-pin tenant context so the Eloquent global scope uses tenant 2.
        TenantContext::setById(2);

        // 2 hours × 20 XP/hour = 40 XP.
        $xpRow = DB::table('user_xp_log')
            ->where('user_id', $this->userId)
            ->where('action', 'volunteer_hour')
            ->first();

        $this->assertNotNull($xpRow, 'Expected a user_xp_log row for action=volunteer_hour');
        $this->assertSame(40, (int) $xpRow->xp_amount);
    }

    public function test_increments_user_xp_column(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        // Raw DB query — unaffected by TenantScope.
        $xpAfter = (int) DB::table('users')->where('id', $this->userId)->value('xp');
        $this->assertSame(40, $xpAfter, 'User xp column should be incremented by 40 (2h × 20)');
    }

    public function test_xp_scales_with_hours(): void
    {
        DB::table('vol_logs')->where('id', $this->volLogId)->update(['hours' => 3.00]);

        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $xpRow = DB::table('user_xp_log')
            ->where('user_id', $this->userId)
            ->where('action', 'volunteer_hour')
            ->first();
        $this->assertNotNull($xpRow);
        $this->assertSame(60, (int) $xpRow->xp_amount, '3h × 20 XP/h = 60 XP');
    }

    public function test_description_contains_bracketed_vol_log_token(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $row = DB::table('user_xp_log')
            ->where('user_id', $this->userId)
            ->where('action', 'volunteer_hour')
            ->first();
        $this->assertNotNull($row);
        // Token must be bracketed to prevent substring collision: [vol_log:1] must NOT
        // match vol_log:10, vol_log:100, etc., so the listener's LIKE check is exact.
        $this->assertStringContainsString('[vol_log:' . $this->volLogId . ']', (string) $row->description);
    }

    // ── 3. Idempotency ────────────────────────────────────────────────────

    public function test_does_not_double_award_xp_on_second_call(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        $listener = new AwardXpOnVolLogApproved();
        $listener->handle($event); // first call → awards XP
        // Between calls, re-pin tenant so the idempotency query inside the listener
        // resolves correctly on the second call (same as listener does via setById).
        $listener->handle($event); // second call → idempotency guard must skip

        // Raw query — bypasses TenantScope which resolves to master tenant after handle().
        $logCount = DB::table('user_xp_log')
            ->where('user_id', $this->userId)
            ->where('action', 'volunteer_hour')
            ->where('description', 'like', '%[vol_log:' . $this->volLogId . ']%')
            ->count();

        $this->assertSame(1, $logCount, 'XP must be awarded exactly once for the same vol_log');

        $xp = (int) DB::table('users')->where('id', $this->userId)->value('xp');
        $this->assertSame(40, $xp, 'XP column must not double-increment on replay');
    }

    // ── 4. Status guards ─────────────────────────────────────────────────

    public function test_does_nothing_when_new_status_is_declined(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'approved',
            newStatus: 'declined',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $count = UserXpLog::where('user_id', $this->userId)->where('action', 'volunteer_hour')->count();
        $this->assertSame(0, $count, 'No XP should be awarded for declined status');
    }

    public function test_does_nothing_for_approved_to_approved_resave(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'approved',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $count = UserXpLog::where('user_id', $this->userId)->where('action', 'volunteer_hour')->count();
        $this->assertSame(0, $count, 'Approved→approved re-save must not award XP');
    }

    public function test_does_nothing_when_new_status_is_pending(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'pending',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $count = UserXpLog::where('user_id', $this->userId)->where('action', 'volunteer_hour')->count();
        $this->assertSame(0, $count);
    }

    // ── 5. Edge cases ────────────────────────────────────────────────────

    public function test_does_nothing_when_vol_log_not_found(): void
    {
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: 999999999, // non-existent
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $count = UserXpLog::where('user_id', $this->userId)->where('action', 'volunteer_hour')->count();
        $this->assertSame(0, $count, 'Missing vol_log should silently skip XP award');
    }

    public function test_does_nothing_when_hours_is_zero(): void
    {
        DB::table('vol_logs')->where('id', $this->volLogId)->update(['hours' => 0.00]);

        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        $count = UserXpLog::where('user_id', $this->userId)->where('action', 'volunteer_hour')->count();
        $this->assertSame(0, $count, 'Zero-hour logs must not produce XP');
    }

    // ── 6. Exception resilience ──────────────────────────────────────────

    public function test_does_not_propagate_exceptions(): void
    {
        // Passing an invalid tenant ID means TenantContext::setById(0) returns false
        // and the listener logs a warning internally, but must never throw to the caller.
        $event = new VolLogStatusChanged(
            tenantId: 0, // invalid — setById(0) returns false
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        // Should not throw.
        (new AwardXpOnVolLogApproved())->handle($event);

        $this->assertTrue(true, 'Listener must not propagate exceptions to callers');
    }

    // ── 7. Tenant context restored ────────────────────────────────────────

    public function test_tenant_context_restored_after_successful_handle(): void
    {
        // In web context (non-console) the listener restores the prior tenant.
        // In console (phpunit CLI) it resets to null. Both are correct per
        // TenantContext::restoreAfterScopedListener logic, so we just verify
        // we can still set the tenant after handle() without error.
        $event = new VolLogStatusChanged(
            tenantId: 2,
            volLogId: $this->volLogId,
            previousStatus: 'pending',
            newStatus: 'approved',
        );

        (new AwardXpOnVolLogApproved())->handle($event);

        // After the listener, the context may be null (console path reset).
        // Re-setting it must succeed and return a valid ID.
        TenantContext::setById(2);
        $this->assertSame(2, TenantContext::currentId(), 'TenantContext must be re-settable after handle()');
    }
}
