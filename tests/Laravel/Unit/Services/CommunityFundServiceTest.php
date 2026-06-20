<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\CommunityFundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-DB tests for CommunityFundService (the per-tenant community credit fund).
 *
 * Previously three of six methods were markTestIncomplete. They are now real
 * assertions — the community fund is a money path (admin deposit/withdraw/grant)
 * and must be guarded.
 *
 * Whole-hour amounts only: nexus_test stores balance as INT (prod is decimal).
 */
class CommunityFundServiceTest extends TestCase
{
    use DatabaseTransactions;

    // --- Pure validation guards (return before any DB access) ---

    public function test_adminDeposit_returns_error_when_amount_zero_or_negative(): void
    {
        $result = CommunityFundService::adminDeposit(1, 0);
        $this->assertFalse($result['success']);
        $this->assertSame('Amount must be greater than 0', $result['error']);

        $result2 = CommunityFundService::adminDeposit(1, -5);
        $this->assertFalse($result2['success']);
    }

    public function test_adminWithdraw_returns_error_when_amount_zero_or_negative(): void
    {
        $result = CommunityFundService::adminWithdraw(1, 2, 0);
        $this->assertFalse($result['success']);
    }

    public function test_receiveDonation_returns_error_when_amount_zero(): void
    {
        $result = CommunityFundService::receiveDonation(1, 0);
        $this->assertFalse($result['success']);
    }

    // --- Real-DB behaviour (converted from markTestIncomplete) ---

    public function test_getBalance_returns_expected_keys(): void
    {
        TenantContext::setById($this->testTenantId);
        $balance = CommunityFundService::getBalance();

        foreach (['id', 'balance', 'total_deposited', 'total_withdrawn', 'total_donated', 'description'] as $key) {
            $this->assertArrayHasKey($key, $balance);
        }
        $this->assertIsNumeric($balance['balance']);
    }

    public function test_getOrCreateFund_is_idempotent(): void
    {
        TenantContext::setById($this->testTenantId);
        $f1 = CommunityFundService::getOrCreateFund();
        TenantContext::setById($this->testTenantId);
        $f2 = CommunityFundService::getOrCreateFund();

        $this->assertArrayHasKey('id', $f1);
        $this->assertArrayHasKey('balance', $f1);
        // get-or-create must return the SAME fund, never create a second for the tenant.
        $this->assertSame((int) $f1['id'], (int) $f2['id']);
    }

    public function test_adminWithdraw_returns_error_for_insufficient_balance(): void
    {
        TenantContext::setById($this->testTenantId);
        $fund = CommunityFundService::getOrCreateFund();
        // Force a known-low fund balance so the assertion is deterministic.
        DB::table('community_fund_accounts')->where('id', $fund['id'])->update(['balance' => 2]);

        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);

        TenantContext::setById($this->testTenantId);
        $result = CommunityFundService::adminWithdraw(1, (int) $recipient->id, 5);

        $this->assertFalse($result['success']);
        // The recipient must not have been credited from an insufficient fund.
        $this->assertEqualsWithDelta(0.0, (float) $recipient->fresh()->balance, 0.001);
    }

    public function test_adminDeposit_then_withdraw_moves_credits(): void
    {
        TenantContext::setById($this->testTenantId);
        $fund = CommunityFundService::getOrCreateFund();
        DB::table('community_fund_accounts')->where('id', $fund['id'])
            ->update(['balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0]);

        $admin = User::factory()->forTenant($this->testTenantId)->create();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['balance' => 5]);

        // adminDeposit/adminWithdraw write community_fund_transactions rows whose
        // user_id/admin_id are FKs to users — use a real admin id, not a literal.
        TenantContext::setById($this->testTenantId);
        $deposit = CommunityFundService::adminDeposit((int) $admin->id, 10);
        $this->assertTrue($deposit['success']);

        TenantContext::setById($this->testTenantId);
        $withdraw = CommunityFundService::adminWithdraw((int) $admin->id, (int) $recipient->id, 4);
        $this->assertTrue($withdraw['success']);

        // Recipient credited exactly 4; fund nets 10 deposited − 4 granted = 6.
        $this->assertEqualsWithDelta(9.0, (float) $recipient->fresh()->balance, 0.001);
        $fundBalance = (float) DB::table('community_fund_accounts')->where('id', $fund['id'])->value('balance');
        $this->assertEqualsWithDelta(6.0, $fundBalance, 0.001);
    }
}
