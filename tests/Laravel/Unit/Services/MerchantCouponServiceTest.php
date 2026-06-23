<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\MerchantCouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * MerchantCouponServiceTest
 *
 * Tests issuance, validation guards, discount math, atomic redemption,
 * and the listing helpers. The QR flow is tested for token generation and
 * the cache-miss expiry guard; the staff-scan leg is skipped because it
 * requires a MarketplaceSellerProfile FK chain that is complex to wire up
 * (noted below).
 */
class MerchantCouponServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Insert a bare-minimum user row and return its id. */
    private function insertUser(): int
    {
        $uid = uniqid('mct', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'CouponUser ' . $uid,
            'first_name' => 'Coupon',
            'last_name'  => 'User',
            'email'      => 'couponuser.' . $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Insert a minimal seller profile and return its id. */
    private function insertSellerProfile(int $userId): int
    {
        return DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id'             => self::TENANT_ID,
            'user_id'               => $userId,
            'seller_type'           => 'business',
            'joined_marketplace_at' => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Insert a coupon row directly and return its id.
     * Defaults to status=active, 10% percent discount, no limits.
     */
    private function insertCoupon(int $sellerId, array $overrides = []): int
    {
        $uid = strtoupper(uniqid('C', true));
        return DB::table('merchant_coupons')->insertGetId(array_merge([
            'tenant_id'          => self::TENANT_ID,
            'seller_id'          => $sellerId,
            'code'               => 'TEST' . substr($uid, 0, 6),
            'title'              => 'Test Coupon',
            'discount_type'      => 'percent',
            'discount_value'     => '10.00',
            'min_order_cents'    => null,
            'max_uses'           => null,
            'max_uses_per_member' => 1,
            'valid_from'         => null,
            'valid_until'        => null,
            'status'             => 'active',
            'applies_to'         => 'all_listings',
            'applies_to_ids'     => null,
            'usage_count'        => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  issueCoupon
    // ─────────────────────────────────────────────────────────────────────────

    public function test_issue_coupon_persists_row(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);

        $coupon = MerchantCouponService::issueCoupon($sellerId, [
            'code'           => 'WELCOME20',
            'title'          => 'Welcome 20%',
            'discount_type'  => 'percent',
            'discount_value' => 20,
            'status'         => 'active',
        ]);

        $this->assertSame(self::TENANT_ID, (int) $coupon->tenant_id);
        $this->assertSame('WELCOME20', $coupon->code);
        $this->assertSame('percent', $coupon->discount_type);
        $this->assertSame(20.0, (float) $coupon->discount_value);
        $this->assertSame(0, (int) $coupon->usage_count);

        $row = DB::table('merchant_coupons')->where('id', $coupon->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('WELCOME20', $row->code);
    }

    public function test_issue_coupon_throws_on_duplicate_code(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);

        MerchantCouponService::issueCoupon($sellerId, [
            'code'           => 'DUPCODE',
            'title'          => 'First',
            'discount_type'  => 'percent',
            'discount_value' => 5,
            'status'         => 'active',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        MerchantCouponService::issueCoupon($sellerId, [
            'code'           => 'dupcode', // lower-case → normalised to DUPCODE
            'title'          => 'Second',
            'discount_type'  => 'fixed',
            'discount_value' => 100,
            'status'         => 'active',
        ]);
    }

    public function test_issue_coupon_auto_generates_code_when_omitted(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);

        $coupon = MerchantCouponService::issueCoupon($sellerId, [
            'title'          => 'Auto Code',
            'discount_type'  => 'fixed',
            'discount_value' => 50,
            'status'         => 'draft',
        ]);

        $this->assertNotEmpty($coupon->code);
        $this->assertSame(strtoupper($coupon->code), $coupon->code, 'Code should be uppercase');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  validateCoupon guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_validate_coupon_throws_for_inactive_coupon(): void
    {
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $this->insertCoupon($sellerId, ['code' => 'PAUSED1', 'status' => 'paused']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon is not active.');
        MerchantCouponService::validateCoupon('PAUSED1', $buyerId, 1000);
    }

    public function test_validate_coupon_throws_when_expired(): void
    {
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $this->insertCoupon($sellerId, [
            'code'        => 'EXPD01',
            'valid_until' => now()->subDay()->toDateTimeString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon has expired.');
        MerchantCouponService::validateCoupon('EXPD01', $buyerId, 1000);
    }

    public function test_validate_coupon_throws_when_not_yet_valid(): void
    {
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $this->insertCoupon($sellerId, [
            'code'       => 'FUTURE1',
            'valid_from' => now()->addDay()->toDateTimeString(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon is not yet valid.');
        MerchantCouponService::validateCoupon('FUTURE1', $buyerId, 1000);
    }

    public function test_validate_coupon_throws_when_min_order_not_met(): void
    {
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $this->insertCoupon($sellerId, ['code' => 'MINORD1', 'min_order_cents' => 5000]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order does not meet the minimum value');
        MerchantCouponService::validateCoupon('MINORD1', $buyerId, 4999);
    }

    public function test_validate_coupon_throws_when_usage_limit_reached(): void
    {
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'code'        => 'MAXUSE1',
            'max_uses'    => 1,
            'usage_count' => 1, // already at limit
        ]);
        $code = DB::table('merchant_coupons')->where('id', $couponId)->value('code');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon has reached its usage limit.');
        MerchantCouponService::validateCoupon($code, $buyerId, 1000);
    }

    public function test_validate_coupon_throws_when_wrong_tenant(): void
    {
        // Force a different TenantContext, then look up a coupon from tenant 2 — should not be found.
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $this->insertCoupon($sellerId, ['code' => 'WRONGT1']);

        TenantContext::setById(999); // non-existent tenant

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon not found.');
        MerchantCouponService::validateCoupon('WRONGT1', $userId, 1000);
    }

    public function test_validate_coupon_returns_model_when_valid(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, ['code' => 'VALID01']);
        $code     = DB::table('merchant_coupons')->where('id', $couponId)->value('code');

        $result = MerchantCouponService::validateCoupon($code, $buyerId, 1000);

        $this->assertSame($couponId, $result->id);
        $this->assertSame($code, $result->code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  calculateDiscountCents
    // ─────────────────────────────────────────────────────────────────────────

    public function test_calculate_discount_cents_percent(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'discount_type'  => 'percent',
            'discount_value' => '25.00',
        ]);
        /** @var \App\Models\MerchantCoupon $coupon */
        $coupon = \App\Models\MerchantCoupon::find($couponId);

        $discount = MerchantCouponService::calculateDiscountCents($coupon, 10000);

        $this->assertSame(2500, $discount); // 25% of 100.00
    }

    public function test_calculate_discount_cents_fixed(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'discount_type'  => 'fixed',
            'discount_value' => '500.00', // stored as cents-value in the service
        ]);
        /** @var \App\Models\MerchantCoupon $coupon */
        $coupon = \App\Models\MerchantCoupon::find($couponId);

        // Fixed discount is min(orderTotal, discountValue)
        $discount = MerchantCouponService::calculateDiscountCents($coupon, 1000);
        $this->assertSame(500, $discount);

        // Fixed discount cannot exceed order total
        $discountCapped = MerchantCouponService::calculateDiscountCents($coupon, 300);
        $this->assertSame(300, $discountCapped);
    }

    public function test_calculate_discount_cents_bogo(): void
    {
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'discount_type'  => 'bogo',
            'discount_value' => '0.00',
        ]);
        /** @var \App\Models\MerchantCoupon $coupon */
        $coupon = \App\Models\MerchantCoupon::find($couponId);

        $discount = MerchantCouponService::calculateDiscountCents($coupon, 4000);

        $this->assertSame(2000, $discount); // 50% of 4000
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  redeemForOrder (atomic)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_redeem_for_order_creates_redemption_and_increments_count(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, ['code' => 'REDEEM1']);

        $redemption = MerchantCouponService::redeemForOrder($couponId, null, $buyerId, 'online');

        $this->assertSame($couponId, (int) $redemption->coupon_id);
        $this->assertSame($buyerId, (int) $redemption->user_id);
        $this->assertSame('online', $redemption->redemption_method);

        // usage_count should be 1 now
        $usageCount = DB::table('merchant_coupons')->where('id', $couponId)->value('usage_count');
        $this->assertSame(1, (int) $usageCount);

        // redemption row in DB
        $row = DB::table('merchant_coupon_redemptions')
            ->where('coupon_id', $couponId)
            ->where('user_id', $buyerId)
            ->first();
        $this->assertNotNull($row);
    }

    public function test_redeem_for_order_blocks_second_redemption_by_same_user(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'code'                => 'NOREUSE1',
            'max_uses_per_member' => 1,
        ]);

        MerchantCouponService::redeemForOrder($couponId, null, $buyerId, 'online');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You have already used this coupon');
        MerchantCouponService::redeemForOrder($couponId, null, $buyerId, 'online');
    }

    public function test_redeem_for_order_blocks_inactive_coupon(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, ['code' => 'DRAFT01', 'status' => 'draft']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon is not active.');
        MerchantCouponService::redeemForOrder($couponId, null, $buyerId, 'online');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  generateQrToken — token shape + cache miss guard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_generate_qr_token_returns_expected_keys(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId  = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, ['code' => 'QRCODE1']);

        $result = MerchantCouponService::generateQrToken($couponId, $buyerId);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('coupon_code', $result);
        $this->assertSame('QRCODE1', $result['coupon_code']);
        $this->assertSame(16, strlen($result['token']));
    }

    public function test_redeem_qr_token_throws_on_invalid_token(): void
    {
        TenantContext::setById(self::TENANT_ID);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('QR token is invalid or has expired.');
        MerchantCouponService::redeemQrToken('NOTREAL000000000', 1);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  listForMerchant / listAllForAdmin
    // ─────────────────────────────────────────────────────────────────────────

    public function test_list_for_merchant_returns_only_seller_coupons(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId1  = $this->insertUser();
        $userId2  = $this->insertUser();
        $seller1  = $this->insertSellerProfile($userId1);
        $seller2  = $this->insertSellerProfile($userId2);

        $this->insertCoupon($seller1, ['code' => 'S1CPN01']);
        $this->insertCoupon($seller1, ['code' => 'S1CPN02']);
        $this->insertCoupon($seller2, ['code' => 'S2CPN01']);

        $list = MerchantCouponService::listForMerchant($seller1);

        // listForMerchant returns MerchantCoupon model instances
        $codes = array_map(fn($c) => $c->code, $list);
        $this->assertContains('S1CPN01', $codes);
        $this->assertContains('S1CPN02', $codes);
        $this->assertNotContains('S2CPN01', $codes);
    }

    public function test_list_redemptions_returns_correct_redemptions(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $buyerId1 = $this->insertUser();
        $buyerId2 = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $couponId = $this->insertCoupon($sellerId, [
            'code'                => 'REDLST01',
            'max_uses_per_member' => 2,
        ]);

        MerchantCouponService::redeemForOrder($couponId, null, $buyerId1, 'online');
        MerchantCouponService::redeemForOrder($couponId, null, $buyerId2, 'online');

        $redemptions = MerchantCouponService::listRedemptions($couponId);
        $this->assertCount(2, $redemptions);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  format()
    // ─────────────────────────────────────────────────────────────────────────

    public function test_format_returns_expected_keys(): void
    {
        TenantContext::setById(self::TENANT_ID);
        $userId   = $this->insertUser();
        $sellerId = $this->insertSellerProfile($userId);
        $coupon   = MerchantCouponService::issueCoupon($sellerId, [
            'code'           => 'FMTTEST1',
            'title'          => 'Format Test',
            'discount_type'  => 'percent',
            'discount_value' => 15,
            'status'         => 'active',
        ]);

        $formatted = MerchantCouponService::format($coupon);

        $expected = ['id', 'seller_id', 'code', 'title', 'description', 'discount_type',
            'discount_value', 'min_order_cents', 'max_uses', 'max_uses_per_member',
            'valid_from', 'valid_until', 'status', 'applies_to', 'applies_to_ids',
            'usage_count', 'created_at'];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $formatted, "Missing key: {$key}");
        }
        $this->assertSame('FMTTEST1', $formatted['code']);
        $this->assertSame(15.0, $formatted['discount_value']);
    }
}
