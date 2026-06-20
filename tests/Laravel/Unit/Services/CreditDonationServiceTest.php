<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\CreditDonationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for CreditDonationService (member-to-member credit donation).
 *
 * Previously five of eight methods were markTestIncomplete ("Eloquent models
 * cannot use shouldReceive()"). They are now real assertions against the test
 * DB — donation is a money path and must be guarded, not stubbed.
 *
 * Whole-hour amounts only: nexus_test stores balance as INT (prod is decimal),
 * so fractional values round and break exact assertions.
 */
class CreditDonationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CreditDonationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreditDonationService();
    }

    // --- Pure validation guards (no DB) ---

    public function test_donate_returns_false_when_amount_zero(): void
    {
        $this->assertFalse($this->service->donate($this->testTenantId, 1, 2, 0));
    }

    public function test_donate_returns_false_when_amount_negative(): void
    {
        $this->assertFalse($this->service->donate($this->testTenantId, 1, 2, -5));
    }

    public function test_donate_returns_false_when_same_user(): void
    {
        $this->assertFalse($this->service->donate($this->testTenantId, 1, 1, 10));
    }

    // --- Real-DB guards (converted from markTestIncomplete) ---

    public function test_donate_returns_false_when_donor_not_found(): void
    {
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        TenantContext::setById($this->testTenantId);

        $this->assertFalse(
            $this->service->donate($this->testTenantId, 99999999, (int) $recipient->id, 5)
        );
    }

    public function test_donate_returns_false_when_insufficient_balance(): void
    {
        $donor = User::factory()->forTenant($this->testTenantId)->create(['balance' => 1]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        TenantContext::setById($this->testTenantId);

        $this->assertFalse(
            $this->service->donate($this->testTenantId, (int) $donor->id, (int) $recipient->id, 5)
        );

        // A rejected donation must move nothing.
        $this->assertEqualsWithDelta(1.0, (float) $donor->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $recipient->fresh()->balance, 0.001);
    }

    public function test_donate_returns_false_when_recipient_not_found(): void
    {
        $donor = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        TenantContext::setById($this->testTenantId);

        $this->assertFalse(
            $this->service->donate($this->testTenantId, (int) $donor->id, 99999999, 5)
        );

        // Donor must not be debited when the recipient does not exist.
        $this->assertEqualsWithDelta(10.0, (float) $donor->fresh()->balance, 0.001);
    }

    // --- Happy path: money conservation (new high-value guard) ---

    public function test_donate_succeeds_and_moves_exact_credits(): void
    {
        $donor = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 5]);
        TenantContext::setById($this->testTenantId);

        $this->assertTrue(
            $this->service->donate($this->testTenantId, (int) $donor->id, (int) $recipient->id, 3, 'thanks')
        );

        $this->assertEqualsWithDelta(7.0, (float) $donor->fresh()->balance, 0.001, 'Donor debited exactly 3');
        $this->assertEqualsWithDelta(8.0, (float) $recipient->fresh()->balance, 0.001, 'Recipient credited exactly 3');

        // One donation transaction and one credit_donations record were written.
        $this->assertSame(1, (int) DB::table('transactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('sender_id', $donor->id)
            ->where('receiver_id', $recipient->id)
            ->where('transaction_type', 'donation')
            ->count());
        $this->assertSame(1, (int) DB::table('credit_donations')
            ->where('tenant_id', $this->testTenantId)
            ->where('donor_id', $donor->id)
            ->where('recipient_id', $recipient->id)
            ->count());
    }

    public function test_getDonations_sent_returns_the_donation(): void
    {
        $donor = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        TenantContext::setById($this->testTenantId);
        $this->service->donate($this->testTenantId, (int) $donor->id, (int) $recipient->id, 2);

        TenantContext::setById($this->testTenantId);
        $sent = $this->service->getDonations($this->testTenantId, (int) $donor->id, 'sent');

        $this->assertIsArray($sent);
        $this->assertCount(1, $sent);
        $this->assertEqualsWithDelta(2.0, (float) $sent[0]['amount'], 0.001);
    }

    public function test_getTotalDonated_sums_donations(): void
    {
        $donor = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $r1 = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        $r2 = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);

        TenantContext::setById($this->testTenantId);
        $this->service->donate($this->testTenantId, (int) $donor->id, (int) $r1->id, 2);
        TenantContext::setById($this->testTenantId);
        $this->service->donate($this->testTenantId, (int) $donor->id, (int) $r2->id, 3);

        TenantContext::setById($this->testTenantId);
        $this->assertEqualsWithDelta(
            5.0,
            $this->service->getTotalDonated($this->testTenantId, (int) $donor->id),
            0.001
        );
    }
}
