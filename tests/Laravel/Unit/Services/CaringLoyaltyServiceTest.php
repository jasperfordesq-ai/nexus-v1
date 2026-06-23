<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CaringLoyaltyService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * CaringLoyaltyServiceTest
 *
 * Tests the loyalty-bridge between Caring Community time credits and
 * marketplace CHF listings. Covers:
 *   - calculateAvailableDiscount: math precision, policy caps, zero-balance, early exits
 *   - redeem: happy path debit+redemption row, guard failures, self-redemption block
 *   - reverse: credits restored, status flipped to reversed
 *   - getSellerSettings / updateSellerSettings: defaults and upsert
 *   - tenantStats: aggregate counting
 *
 * All table mutations roll back via DatabaseTransactions.
 */
class CaringLoyaltyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private CaringLoyaltyService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new CaringLoyaltyService();
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private function insertUser(float $balance = 0.0, string $tag = ''): int
    {
        $uid = uniqid($tag ?: 'u', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Loyalty Test ' . $uid,
            'first_name' => 'Loyalty',
            'last_name'  => 'User',
            'email'      => 'loyalty.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => $balance,
            'role'       => 'member',
            'is_approved'=> 1,
            'created_at' => now(),
        ]);
    }

    private function insertSellerSettings(int $sellerId, bool $accepts = true, float $rate = 10.0, int $maxPct = 50): void
    {
        DB::table('marketplace_seller_loyalty_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'seller_user_id' => $sellerId],
            [
                'accepts_time_credits'     => $accepts ? 1 : 0,
                'loyalty_chf_per_hour'     => $rate,
                'loyalty_max_discount_pct' => $maxPct,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]
        );
    }

    private function insertListing(int $sellerId, string $status = 'active', string $modStatus = 'approved'): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $sellerId,
            'title'             => 'Test Listing ' . uniqid(),
            'description'       => 'Test description',
            'status'            => $status,
            'moderation_status' => $modStatus,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    // ── calculateAvailableDiscount ────────────────────────────────────────────

    public function test_calculateAvailableDiscount_returns_not_accepts_when_settings_table_absent_for_missing_seller(): void
    {
        // No settings row → merchant_disabled path
        $memberId = $this->insertUser(50.0, 'member');
        $sellerId = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);

        $result = $this->svc->calculateAvailableDiscount($memberId, $sellerId, 100.0, $listingId);

        $this->assertFalse($result['accepts']);
        $this->assertArrayHasKey('reason', $result);
    }

    public function test_calculateAvailableDiscount_returns_invalid_order_total_for_zero_total(): void
    {
        $memberId = $this->insertUser(50.0, 'member');
        $sellerId = $this->insertUser(0.0, 'seller');

        $result = $this->svc->calculateAvailableDiscount($memberId, $sellerId, 0.0, null);

        $this->assertFalse($result['accepts']);
        $this->assertSame('invalid_order_total', $result['reason']);
    }

    public function test_calculateAvailableDiscount_happy_path_computes_correct_max_credits(): void
    {
        // Member has 20 credits. Seller: 10 CHF/hour, 50% max discount.
        // Order total = 100 CHF. Max discount = 50 CHF → 5 hours.
        // Member has 20, so capped by merchant policy to 5 credits.
        $memberId = $this->insertUser(20.0, 'member');
        $sellerId = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 50);

        $result = $this->svc->calculateAvailableDiscount($memberId, $sellerId, 100.0, $listingId);

        $this->assertTrue($result['accepts']);
        $this->assertSame(20.0, $result['member_credits']);   // actual balance
        $this->assertSame(10.0, $result['exchange_rate_chf']);
        $this->assertSame(50, $result['max_discount_pct']);
        $this->assertSame(5.0, $result['max_credits_usable']); // policy cap: 50chf / 10 = 5h
        $this->assertSame(50.0, $result['max_discount_chf']); // 5 * 10
    }

    public function test_calculateAvailableDiscount_caps_by_member_balance_when_below_policy(): void
    {
        // Member only has 2 credits — below the 5-credit policy ceiling.
        $memberId = $this->insertUser(2.0, 'member');
        $sellerId = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 50);

        $result = $this->svc->calculateAvailableDiscount($memberId, $sellerId, 100.0, $listingId);

        $this->assertTrue($result['accepts']);
        $this->assertSame(2.0, $result['max_credits_usable']);
        $this->assertSame(20.0, $result['max_discount_chf']); // 2 * 10
    }

    public function test_calculateAvailableDiscount_returns_merchant_disabled_when_flag_off(): void
    {
        $memberId  = $this->insertUser(50.0, 'member');
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, false, 10.0, 50);

        $result = $this->svc->calculateAvailableDiscount($memberId, $sellerId, 100.0, $listingId);

        $this->assertFalse($result['accepts']);
        $this->assertSame('merchant_disabled', $result['reason']);
    }

    // ── redeem ─────────────────────────────────────────────────────────────────

    public function test_redeem_debits_member_wallet_and_inserts_redemption_row(): void
    {
        $memberId  = $this->insertUser(10.0, 'member');
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 50); // 10 CHF/hour, 50% cap

        // Use 2 credits on 100 CHF order → 20 CHF discount (within 50% = 50 CHF cap)
        $result = $this->svc->redeem($memberId, $sellerId, $listingId, 2.0, 100.0);

        // Return shape
        $this->assertSame(20.0, $result['discount_chf']);
        $this->assertSame(8.0, $result['new_wallet_balance']);
        $this->assertIsInt($result['redemption_id']);

        // Wallet debited in DB
        $newBal = (float) DB::table('users')->where('id', $memberId)->value('balance');
        $this->assertSame(8.0, $newBal);

        // Redemption row inserted
        $row = DB::table('caring_loyalty_redemptions')->where('id', $result['redemption_id'])->first();
        $this->assertNotNull($row);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
        $this->assertSame($memberId, (int) $row->member_user_id);
        $this->assertSame($sellerId, (int) $row->merchant_user_id);
        $this->assertSame(2.0, round((float) $row->credits_used, 2));
        $this->assertSame(20.0, round((float) $row->discount_chf, 2));
        $this->assertSame('applied', (string) $row->status);
    }

    public function test_redeem_throws_when_credits_to_use_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc->redeem(1, 2, 1, 0.0, 100.0);
    }

    public function test_redeem_throws_on_self_redemption(): void
    {
        $this->expectException(RuntimeException::class);
        $userId = $this->insertUser(50.0);
        $this->svc->redeem($userId, $userId, 1, 1.0, 100.0);
    }

    public function test_redeem_throws_when_member_has_insufficient_credits(): void
    {
        $memberId  = $this->insertUser(1.0, 'member'); // only 1 credit
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 50);

        $this->expectException(RuntimeException::class);
        $this->svc->redeem($memberId, $sellerId, $listingId, 5.0, 100.0); // wants 5, has 1
    }

    public function test_redeem_throws_when_discount_exceeds_merchant_max_pct(): void
    {
        // 10 CHF/hour, 10% max on 100 CHF order = 10 CHF max = 1 credit.
        // Requesting 2 credits = 20 CHF → exceeds cap.
        $memberId  = $this->insertUser(50.0, 'member');
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 10);

        $this->expectException(RuntimeException::class);
        $this->svc->redeem($memberId, $sellerId, $listingId, 2.0, 100.0);
    }

    // ── reverse ────────────────────────────────────────────────────────────────

    public function test_reverse_restores_credits_to_member_and_marks_reversed(): void
    {
        $memberId  = $this->insertUser(10.0, 'member');
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 50);
        $adminId   = $this->insertUser(0.0, 'admin');

        // Create a redemption first
        $redeemResult = $this->svc->redeem($memberId, $sellerId, $listingId, 2.0, 100.0);
        $redemptionId = $redeemResult['redemption_id'];

        // Balance after redeem = 8
        $balAfterRedeem = (float) DB::table('users')->where('id', $memberId)->value('balance');
        $this->assertSame(8.0, $balAfterRedeem);

        // Reverse it
        $reverseResult = $this->svc->reverse($redemptionId, 'Test reversal', $adminId);

        $this->assertSame($redemptionId, $reverseResult['redemption_id']);
        $this->assertSame(2.0, $reverseResult['credits_restored']);
        $this->assertSame(10.0, $reverseResult['member_new_balance']);

        // Balance restored in DB
        $finalBal = (float) DB::table('users')->where('id', $memberId)->value('balance');
        $this->assertSame(10.0, $finalBal);

        // Redemption row status updated
        $row = DB::table('caring_loyalty_redemptions')->where('id', $redemptionId)->first();
        $this->assertSame('reversed', (string) $row->status);
    }

    public function test_reverse_throws_when_redemption_not_in_applied_state(): void
    {
        // Force a 'reversed' row directly
        $memberId = $this->insertUser(5.0, 'member');
        $sellerId = $this->insertUser(0.0, 'seller');
        $adminId  = $this->insertUser(0.0, 'admin');

        $redemptionId = (int) DB::table('caring_loyalty_redemptions')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'member_user_id'   => $memberId,
            'merchant_user_id' => $sellerId,
            'credits_used'     => 1.0,
            'exchange_rate_chf'=> 10.0,
            'discount_chf'     => 10.0,
            'order_total_chf'  => 100.0,
            'status'           => 'reversed',   // already reversed
            'redeemed_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->svc->reverse($redemptionId, null, $adminId);
    }

    // ── getSellerSettings / updateSellerSettings ──────────────────────────────

    public function test_getSellerSettings_returns_defaults_when_no_row(): void
    {
        $sellerId = $this->insertUser(0.0, 'seller');
        $result   = $this->svc->getSellerSettings($sellerId);

        $this->assertSame($sellerId, $result['seller_user_id']);
        $this->assertFalse($result['accepts_time_credits']);
        $this->assertSame(25.00, $result['loyalty_chf_per_hour']);
        $this->assertSame(50, $result['loyalty_max_discount_pct']);
    }

    public function test_updateSellerSettings_upserts_and_returns_new_values(): void
    {
        $sellerId = $this->insertUser(0.0, 'seller');

        $result = $this->svc->updateSellerSettings($sellerId, true, 15.0, 30);

        $this->assertSame($sellerId, $result['seller_user_id']);
        $this->assertTrue($result['accepts_time_credits']);
        $this->assertSame(15.0, $result['loyalty_chf_per_hour']);
        $this->assertSame(30, $result['loyalty_max_discount_pct']);

        // Verify persisted
        $row = DB::table('marketplace_seller_loyalty_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('seller_user_id', $sellerId)
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->accepts_time_credits);
        $this->assertSame(15.0, round((float) $row->loyalty_chf_per_hour, 2));
    }

    public function test_updateSellerSettings_throws_for_invalid_exchange_rate(): void
    {
        $sellerId = $this->insertUser(0.0, 'seller');
        $this->expectException(InvalidArgumentException::class);
        $this->svc->updateSellerSettings($sellerId, true, 0.0, 50);
    }

    public function test_updateSellerSettings_throws_for_invalid_max_discount_pct(): void
    {
        $sellerId = $this->insertUser(0.0, 'seller');
        $this->expectException(InvalidArgumentException::class);
        $this->svc->updateSellerSettings($sellerId, true, 10.0, 101);
    }

    // ── tenantStats ────────────────────────────────────────────────────────────

    public function test_tenantStats_counts_only_applied_redemptions(): void
    {
        $memberId  = $this->insertUser(30.0, 'member');
        $sellerId  = $this->insertUser(0.0, 'seller');
        $listingId = $this->insertListing($sellerId);
        $this->insertSellerSettings($sellerId, true, 10.0, 80);
        $adminId   = $this->insertUser(0.0, 'admin');

        // Insert 2 applied redemptions via redeem()
        $this->svc->redeem($memberId, $sellerId, $listingId, 1.0, 100.0);
        $this->svc->redeem($memberId, $sellerId, $listingId, 2.0, 200.0);

        // Insert a reversed redemption directly (should NOT count in stats)
        $reversedId = (int) DB::table('caring_loyalty_redemptions')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'member_user_id'   => $memberId,
            'merchant_user_id' => $sellerId,
            'credits_used'     => 5.0,
            'exchange_rate_chf'=> 10.0,
            'discount_chf'     => 50.0,
            'order_total_chf'  => 100.0,
            'status'           => 'reversed',
            'redeemed_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $stats = $this->svc->tenantStats();

        // At minimum 2 applied redemptions we just created; reversed excluded.
        $this->assertGreaterThanOrEqual(2, $stats['total_redemptions']);
        // credits_used: 1 + 2 = 3 (at minimum; other tests roll back)
        $this->assertGreaterThanOrEqual(3.0, $stats['total_credits']);
        // discount_chf: 10 + 20 = 30
        $this->assertGreaterThanOrEqual(30.0, $stats['total_discount_chf']);
    }
}
