<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\ShiftSwapService;
use App\Services\ShiftWaitlistService;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression tests for the 2026-06-11 deep-audit fixes:
 *
 * The shift waitlist was feature-dead — nothing ever transitioned entries
 * from 'waiting' to 'notified', so promoteUser() (which requires 'notified')
 * could never succeed and joined users waited forever. These tests cover the
 * full lifecycle that now exists: slot frees → next in line notified →
 * claim (capacity-rechecked) → promoted, plus offer expiry cascade and the
 * leave-while-notified handoff. Also covers the new past-shift guard on
 * swap execution.
 */
class WaitlistFlowTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{shiftId: int, oppId: int} */
    private function createShift(int $capacity = 1, bool $past = false): array
    {
        TenantContext::setById($this->testTenantId);

        $orgOwner = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);

        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $orgOwner->id,
            'name' => 'Waitlist Test Org',
        ]);

        $oppId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'title' => 'Waitlist Test Opportunity',
            'description' => 'Test',
        ]);

        $shiftId = (int) DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $oppId,
            'start_time' => $past ? now()->subDay() : now()->addDay(),
            'end_time' => $past ? now()->subDay()->addHours(2) : now()->addDay()->addHours(2),
            'capacity' => $capacity,
        ]);

        return ['shiftId' => $shiftId, 'oppId' => $oppId];
    }

    private function addApprovedSignup(int $oppId, int $shiftId, int $userId): int
    {
        return (int) DB::table('vol_applications')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $oppId,
            'shift_id' => $shiftId,
            'user_id' => $userId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addWaitlistEntry(int $shiftId, int $userId, int $position, string $status = 'waiting', ?string $notifiedAt = null): int
    {
        return (int) DB::table('vol_shift_waitlist')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'shift_id' => $shiftId,
            'user_id' => $userId,
            'position' => $position,
            'status' => $status,
            'notified_at' => $notifiedAt,
            'created_at' => now(),
        ]);
    }

    private function waitlistStatus(int $entryId): string
    {
        return (string) DB::table('vol_shift_waitlist')->where('id', $entryId)->value('status');
    }

    // ================================================================
    // notifyNext
    // ================================================================

    public function test_notify_next_offers_spot_to_first_in_line(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(2);
        $u1 = User::factory()->forTenant($this->testTenantId)->create();
        $u2 = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $e1 = $this->addWaitlistEntry($shiftId, (int) $u1->id, 1);
        $e2 = $this->addWaitlistEntry($shiftId, (int) $u2->id, 2);

        $result = ShiftWaitlistService::notifyNext($shiftId, $this->testTenantId);

        $this->assertTrue($result);
        $this->assertSame('notified', $this->waitlistStatus($e1));
        $this->assertSame('waiting', $this->waitlistStatus($e2));
        $this->assertNotNull(DB::table('vol_shift_waitlist')->where('id', $e1)->value('notified_at'));
    }

    public function test_notify_next_noop_when_shift_full(): void
    {
        ['shiftId' => $shiftId, 'oppId' => $oppId] = $this->createShift(1);
        $occupant = User::factory()->forTenant($this->testTenantId)->create();
        $waiter = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $this->addApprovedSignup($oppId, $shiftId, (int) $occupant->id);
        $entry = $this->addWaitlistEntry($shiftId, (int) $waiter->id, 1);

        $result = ShiftWaitlistService::notifyNext($shiftId, $this->testTenantId);

        $this->assertFalse($result);
        $this->assertSame('waiting', $this->waitlistStatus($entry));
    }

    public function test_notify_next_counts_outstanding_offers_against_capacity(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(1);
        $u1 = User::factory()->forTenant($this->testTenantId)->create();
        $u2 = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $this->addWaitlistEntry($shiftId, (int) $u1->id, 1, 'notified', now()->toDateTimeString());
        $e2 = $this->addWaitlistEntry($shiftId, (int) $u2->id, 2);

        $result = ShiftWaitlistService::notifyNext($shiftId, $this->testTenantId);

        // Capacity 1, one outstanding offer — no second offer may go out
        $this->assertFalse($result);
        $this->assertSame('waiting', $this->waitlistStatus($e2));
    }

    // ================================================================
    // Slot-freeing hooks
    // ================================================================

    public function test_cancelling_shift_signup_notifies_next_in_line(): void
    {
        ['shiftId' => $shiftId, 'oppId' => $oppId] = $this->createShift(1);
        $occupant = User::factory()->forTenant($this->testTenantId)->create();
        $waiter = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $this->addApprovedSignup($oppId, $shiftId, (int) $occupant->id);
        $entry = $this->addWaitlistEntry($shiftId, (int) $waiter->id, 1);

        $ok = VolunteerService::cancelShiftSignup($shiftId, (int) $occupant->id);

        $this->assertTrue($ok, json_encode(VolunteerService::getErrors()));
        $this->assertSame('notified', $this->waitlistStatus($entry));
    }

    // ================================================================
    // promoteUser (claim)
    // ================================================================

    public function test_notified_user_can_claim_spot(): void
    {
        ['shiftId' => $shiftId, 'oppId' => $oppId] = $this->createShift(1);
        $waiter = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $entry = $this->addWaitlistEntry($shiftId, (int) $waiter->id, 1, 'notified', now()->toDateTimeString());

        $ok = ShiftWaitlistService::promoteUser($entry, $this->testTenantId);

        $this->assertTrue($ok, json_encode(ShiftWaitlistService::getErrors()));
        $this->assertSame('promoted', $this->waitlistStatus($entry));

        $app = DB::table('vol_applications')
            ->where('shift_id', $shiftId)
            ->where('user_id', $waiter->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();
        $this->assertNotNull($app);
        $this->assertSame('approved', $app->status);
        $this->assertSame($oppId, (int) $app->opportunity_id);
    }

    public function test_waiting_user_cannot_claim_before_being_notified(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(1);
        $waiter = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $entry = $this->addWaitlistEntry($shiftId, (int) $waiter->id, 1);

        $ok = ShiftWaitlistService::promoteUser($entry, $this->testTenantId);

        $this->assertFalse($ok);
        $errors = ShiftWaitlistService::getErrors();
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code'] ?? null);
        $this->assertSame('waiting', $this->waitlistStatus($entry));
    }

    public function test_claim_fails_when_spot_taken_in_the_meantime(): void
    {
        ['shiftId' => $shiftId, 'oppId' => $oppId] = $this->createShift(1);
        $waiter = User::factory()->forTenant($this->testTenantId)->create();
        $sniper = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $entry = $this->addWaitlistEntry($shiftId, (int) $waiter->id, 1, 'notified', now()->toDateTimeString());

        // Someone else takes the last spot through another path
        $this->addApprovedSignup($oppId, $shiftId, (int) $sniper->id);

        $ok = ShiftWaitlistService::promoteUser($entry, $this->testTenantId);

        $this->assertFalse($ok);
        $this->assertSame('notified', $this->waitlistStatus($entry));
        $this->assertSame(0, DB::table('vol_applications')
            ->where('shift_id', $shiftId)->where('user_id', $waiter->id)->count());
    }

    // ================================================================
    // Offer expiry cascade
    // ================================================================

    public function test_stale_offers_expire_and_cascade_to_next(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(1);
        $u1 = User::factory()->forTenant($this->testTenantId)->create();
        $u2 = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $e1 = $this->addWaitlistEntry($shiftId, (int) $u1->id, 1, 'notified', now()->subHours(72)->toDateTimeString());
        $e2 = $this->addWaitlistEntry($shiftId, (int) $u2->id, 2);

        $expired = ShiftWaitlistService::expireStaleNotifications(48);

        $this->assertSame(1, $expired);
        $this->assertSame('expired', $this->waitlistStatus($e1));
        $this->assertSame('notified', $this->waitlistStatus($e2));
    }

    public function test_fresh_offers_are_not_expired(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(1);
        $u1 = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $e1 = $this->addWaitlistEntry($shiftId, (int) $u1->id, 1, 'notified', now()->subHours(2)->toDateTimeString());

        $expired = ShiftWaitlistService::expireStaleNotifications(48);

        $this->assertSame(0, $expired);
        $this->assertSame('notified', $this->waitlistStatus($e1));
    }

    // ================================================================
    // Leaving while holding an offer
    // ================================================================

    public function test_leaving_while_notified_passes_offer_to_next(): void
    {
        ['shiftId' => $shiftId] = $this->createShift(1);
        $u1 = User::factory()->forTenant($this->testTenantId)->create();
        $u2 = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $e1 = $this->addWaitlistEntry($shiftId, (int) $u1->id, 1, 'notified', now()->toDateTimeString());
        $e2 = $this->addWaitlistEntry($shiftId, (int) $u2->id, 2);

        $ok = ShiftWaitlistService::leave($shiftId, (int) $u1->id);

        $this->assertTrue($ok, json_encode(ShiftWaitlistService::getErrors()));
        $this->assertSame('cancelled', $this->waitlistStatus($e1));
        $this->assertSame('notified', $this->waitlistStatus($e2));
    }

    // ================================================================
    // Swap past-shift guard
    // ================================================================

    public function test_swap_cannot_execute_once_shift_started(): void
    {
        ['shiftId' => $pastShiftId, 'oppId' => $pastOppId] = $this->createShift(5, true);
        ['shiftId' => $futureShiftId, 'oppId' => $futureOppId] = $this->createShift(5);
        $alice = User::factory()->forTenant($this->testTenantId)->create();
        $bob = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $this->addApprovedSignup($pastOppId, $pastShiftId, (int) $alice->id);
        $this->addApprovedSignup($futureOppId, $futureShiftId, (int) $bob->id);

        $swapId = (int) DB::table('vol_shift_swap_requests')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'from_user_id' => $alice->id,
            'to_user_id' => $bob->id,
            'from_shift_id' => $pastShiftId,
            'to_shift_id' => $futureShiftId,
            'status' => 'pending',
            'requires_admin_approval' => 0,
            'created_at' => now(),
        ]);

        $ok = ShiftSwapService::respond($swapId, (int) $bob->id, 'accept');

        $this->assertFalse($ok);

        // Assignments must be untouched
        $this->assertSame($pastShiftId, (int) DB::table('vol_applications')
            ->where('user_id', $alice->id)->where('tenant_id', $this->testTenantId)->value('shift_id'));
        $this->assertSame($futureShiftId, (int) DB::table('vol_applications')
            ->where('user_id', $bob->id)->where('tenant_id', $this->testTenantId)->value('shift_id'));
    }
}
