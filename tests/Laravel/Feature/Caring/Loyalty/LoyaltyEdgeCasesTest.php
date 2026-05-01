<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Caring\Loyalty;

use App\Core\TenantContext;
use App\Services\CaringLoyaltyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Laravel\TestCase;

/**
 * Edge-case audit for the time-credit ↔ marketplace loyalty bridge.
 *
 * Covers:
 *   1. Zero-balance redemption — must reject cleanly with a translated error.
 *   2. Partial redemption — credits + remainder cash is the documented behaviour.
 *   3. Refund / reversal — atomic credit restore + status flip + audit trail.
 *   4. Merchant cap exhaustion mid-checkout — preview ok, settings flip, confirm rejects.
 */
class LoyaltyEdgeCasesTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2; // hour-timebank

    private CaringLoyaltyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setCaringCommunityFeature(true);
        TenantContext::setById(self::TENANT_ID);
        $this->service = app(CaringLoyaltyService::class);

        if (! Schema::hasTable('caring_loyalty_redemptions') || ! Schema::hasTable('marketplace_seller_loyalty_settings')) {
            $this->markTestSkipped('Loyalty tables not present in this test database.');
        }
    }

    private function setCaringCommunityFeature(bool $enabled): void
    {
        $tenant = DB::table('tenants')->where('id', self::TENANT_ID)->first();
        $features = [];
        if ($tenant && !empty($tenant->features)) {
            $decoded = is_string($tenant->features) ? json_decode($tenant->features, true) : $tenant->features;
            $features = is_array($decoded) ? $decoded : [];
        }
        $features['caring_community'] = $enabled;
        DB::table('tenants')
            ->where('id', self::TENANT_ID)
            ->update(['features' => json_encode($features)]);
    }

    private function makeUser(string $emailPrefix, float $balance = 0): int
    {
        $email = $emailPrefix . '.' . uniqid() . '@example.test';
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => $email,
            'username'   => 'u_' . substr(md5($email . microtime(true)), 0, 8),
            'password'   => password_hash('password', PASSWORD_BCRYPT),
            'balance'    => $balance,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMerchantWithSettings(
        bool $accepts = true,
        float $rate = 25.00,
        int $maxPct = 50
    ): int {
        $merchantId = $this->makeUser('merchant', 0);
        DB::table('marketplace_seller_loyalty_settings')->insert([
            'tenant_id'                => self::TENANT_ID,
            'seller_user_id'           => $merchantId,
            'accepts_time_credits'     => $accepts ? 1 : 0,
            'loyalty_chf_per_hour'     => $rate,
            'loyalty_max_discount_pct' => $maxPct,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
        return $merchantId;
    }

    // ─── Scenario 1: Zero-balance redemption ──────────────────────────────────

    public function test_zero_balance_member_redeem_rejects_with_translated_error(): void
    {
        $member = $this->makeUser('zero', 0);
        $merchant = $this->makeMerchantWithSettings();

        $expected = __('caring_community.loyalty.errors.zero_balance');
        $this->assertNotSame('caring_community.loyalty.errors.zero_balance', $expected,
            'Translation key must resolve to a real string (lang/en/caring_community.json missing).');

        try {
            $this->service->redeem($member, $merchant, null, 1.0, 100.0);
            $this->fail('Expected RuntimeException for zero balance');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }

        // Wallet untouched
        $this->assertEqualsWithDelta(0.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);
        // No ledger row written
        $this->assertSame(0, DB::table('caring_loyalty_redemptions')
            ->where('tenant_id', self::TENANT_ID)
            ->where('member_user_id', $member)
            ->count());
    }

    public function test_zero_balance_quote_returns_zero_max_credits_usable(): void
    {
        $member = $this->makeUser('zero_quote', 0);
        $merchant = $this->makeMerchantWithSettings();

        $quote = $this->service->calculateAvailableDiscount($member, $merchant, 100.0);

        $this->assertTrue($quote['accepts']);
        $this->assertSame(0.0, $quote['member_credits']);
        $this->assertSame(0.0, $quote['max_credits_usable']);
        $this->assertSame(0.0, $quote['max_discount_chf']);
    }

    // ─── Scenario 2: Partial redemption ────────────────────────────────────────

    public function test_partial_redemption_allowed_credits_plus_cash_remainder(): void
    {
        // Member has 2h, merchant rate 25 CHF/h, 50% of 100 CHF order = 50 CHF max discount
        // 2h × 25 = 50 CHF discount → fits within 50% cap. Remainder 50 CHF paid in cash off-platform.
        $member   = $this->makeUser('partial', 2.0);
        $merchant = $this->makeMerchantWithSettings(true, 25.00, 50);

        $result = $this->service->redeem($member, $merchant, null, 2.0, 100.0);

        $this->assertEqualsWithDelta(50.0, $result['discount_chf'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['new_wallet_balance'], 0.01);

        $row = DB::table('caring_loyalty_redemptions')->where('id', $result['redemption_id'])->first();
        $this->assertNotNull($row);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
        $this->assertSame('applied', $row->status);
        $this->assertEqualsWithDelta(2.0, (float) $row->credits_used, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $row->discount_chf, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $row->order_total_chf, 0.01);
    }

    public function test_redemption_capped_when_credits_would_exceed_merchant_pct(): void
    {
        // Member has 10h at 25 CHF/h = 250 CHF, but merchant caps at 30% of 100 = 30 CHF
        // Quote should advertise max_credits_usable = 30/25 = 1.20h, not 10
        $member   = $this->makeUser('partial_cap', 10.0);
        $merchant = $this->makeMerchantWithSettings(true, 25.00, 30);

        $quote = $this->service->calculateAvailableDiscount($member, $merchant, 100.0);
        $this->assertEqualsWithDelta(1.20, $quote['max_credits_usable'], 0.01);
        $this->assertEqualsWithDelta(30.0, $quote['max_discount_chf'], 0.01);

        // Trying to use more than the cap allows must be rejected
        $expected = __('caring_community.loyalty.errors.exceeds_max_discount');
        try {
            $this->service->redeem($member, $merchant, null, 5.0, 100.0); // 5h × 25 = 125 CHF > 30 CHF cap
            $this->fail('Expected RuntimeException for over-cap discount');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }

        // Wallet untouched
        $this->assertEqualsWithDelta(10.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);
    }

    // ─── Scenario 3: Refund / reversal ────────────────────────────────────────

    public function test_reversal_restores_credits_and_marks_row_with_audit_trail(): void
    {
        $member   = $this->makeUser('refund', 5.0);
        $merchant = $this->makeMerchantWithSettings(true, 25.00, 50);
        $admin    = $this->makeUser('refund_admin', 0);

        $applied = $this->service->redeem($member, $merchant, null, 2.0, 100.0);
        $this->assertEqualsWithDelta(3.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);

        $reversal = $this->service->reverse($applied['redemption_id'], 'Customer requested refund', $admin);

        $this->assertSame($applied['redemption_id'], $reversal['redemption_id']);
        $this->assertEqualsWithDelta(2.0, $reversal['credits_restored'], 0.001);
        $this->assertEqualsWithDelta(5.0, $reversal['member_new_balance'], 0.001);

        // Wallet fully restored
        $this->assertEqualsWithDelta(5.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);

        // Row marked reversed with admin + reason
        $row = DB::table('caring_loyalty_redemptions')->where('id', $applied['redemption_id'])->first();
        $this->assertSame('reversed', $row->status);
        if (Schema::hasColumn('caring_loyalty_redemptions', 'reversed_by')) {
            $this->assertSame($admin, (int) $row->reversed_by);
        }
        if (Schema::hasColumn('caring_loyalty_redemptions', 'reversal_reason')) {
            $this->assertSame('Customer requested refund', $row->reversal_reason);
        }
        if (Schema::hasColumn('caring_loyalty_redemptions', 'reversed_at')) {
            $this->assertNotNull($row->reversed_at);
        }
    }

    public function test_double_reversal_is_rejected(): void
    {
        $member   = $this->makeUser('double', 5.0);
        $merchant = $this->makeMerchantWithSettings();
        $admin    = $this->makeUser('double_admin', 0);

        $applied = $this->service->redeem($member, $merchant, null, 1.0, 100.0);
        $this->service->reverse($applied['redemption_id'], 'first reversal', $admin);

        $expected = __('caring_community.loyalty.errors.redemption_not_reversible');
        try {
            $this->service->reverse($applied['redemption_id'], 'second attempt', $admin);
            $this->fail('Expected RuntimeException for double reversal');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }

        // Member balance must NOT have been credited a second time
        $this->assertEqualsWithDelta(5.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);
    }

    public function test_reversal_is_tenant_scoped(): void
    {
        $member   = $this->makeUser('tenant_scope', 5.0);
        $merchant = $this->makeMerchantWithSettings();
        $admin    = $this->makeUser('tenant_scope_admin', 0);

        $applied = $this->service->redeem($member, $merchant, null, 1.0, 100.0);

        // Find any other live tenant to switch to (tenant_id != TENANT_ID).
        // setById() refuses to switch to a non-existent tenant, so we must use a real one.
        $otherTenantId = (int) DB::table('tenants')
            ->where('id', '!=', self::TENANT_ID)
            ->value('id');

        if (!$otherTenantId) {
            $this->markTestSkipped('No second tenant available for cross-tenant scoping check.');
        }

        $switched = TenantContext::setById($otherTenantId);
        $this->assertTrue($switched, 'Could not switch tenant context for cross-tenant test.');

        $expected = __('caring_community.loyalty.errors.redemption_not_found');
        try {
            $this->service->reverse($applied['redemption_id'], 'cross-tenant', $admin);
            $this->fail('Expected RuntimeException for cross-tenant reversal');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        } finally {
            TenantContext::setById(self::TENANT_ID);
        }

        $row = DB::table('caring_loyalty_redemptions')->where('id', $applied['redemption_id'])->first();
        $this->assertSame('applied', $row->status, 'Cross-tenant reversal must NOT have flipped the row.');
    }

    // ─── Scenario 4: Merchant cap exhaustion mid-checkout ──────────────────────

    public function test_merchant_disables_between_quote_and_confirm_rejects_redemption(): void
    {
        $member   = $this->makeUser('exhaust', 5.0);
        $merchant = $this->makeMerchantWithSettings(true, 25.00, 50);

        // Step 1: quote — merchant accepts
        $quote = $this->service->calculateAvailableDiscount($member, $merchant, 100.0);
        $this->assertTrue($quote['accepts']);

        // Step 2: between preview and confirm, merchant flips off
        DB::table('marketplace_seller_loyalty_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('seller_user_id', $merchant)
            ->update(['accepts_time_credits' => 0, 'updated_at' => now()]);

        // Step 3: confirm must reject with translated merchant_disabled error
        $expected = __('caring_community.loyalty.errors.merchant_disabled');
        try {
            $this->service->redeem($member, $merchant, null, 2.0, 100.0);
            $this->fail('Expected RuntimeException for merchant disabled mid-checkout');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }

        // Wallet untouched
        $this->assertEqualsWithDelta(5.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);
        // No ledger row
        $this->assertSame(0, DB::table('caring_loyalty_redemptions')
            ->where('tenant_id', self::TENANT_ID)
            ->where('member_user_id', $member)
            ->count());
    }

    public function test_merchant_tightens_cap_between_quote_and_confirm_rejects_overflow(): void
    {
        $member   = $this->makeUser('tighten', 5.0);
        $merchant = $this->makeMerchantWithSettings(true, 25.00, 50);

        // Quote at 50% cap → max 50 CHF discount on 100 CHF = 2h usable
        $quote = $this->service->calculateAvailableDiscount($member, $merchant, 100.0);
        $this->assertEqualsWithDelta(2.0, $quote['max_credits_usable'], 0.01);

        // Merchant tightens cap to 10% of order before user clicks confirm
        DB::table('marketplace_seller_loyalty_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('seller_user_id', $merchant)
            ->update(['loyalty_max_discount_pct' => 10, 'updated_at' => now()]);

        // User confirms with the originally-quoted 2h (50 CHF) — now exceeds 10 CHF cap
        $expected = __('caring_community.loyalty.errors.exceeds_max_discount');
        try {
            $this->service->redeem($member, $merchant, null, 2.0, 100.0);
            $this->fail('Expected RuntimeException for cap tightened mid-checkout');
        } catch (RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }

        // Wallet untouched
        $this->assertEqualsWithDelta(5.0, (float) DB::table('users')->where('id', $member)->value('balance'), 0.001);
    }
}
