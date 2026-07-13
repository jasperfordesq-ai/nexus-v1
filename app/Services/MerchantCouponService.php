<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceSellerProfile;
use App\Models\MarketplaceShippingOption;
use App\Models\MerchantCoupon;
use App\Models\MerchantCouponRedemption;
use App\Support\StripeCurrency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MerchantCouponService — AG63 merchant discount/coupon management.
 *
 * Handles issuance, validation, atomic redemption, and QR token flow for
 * merchant-issued discount coupons (percent / fixed / BOGO).
 */
class MerchantCouponService
{
    public const QR_TTL_SECONDS = 300; // 5 minutes

    // -----------------------------------------------------------------
    //  Issuance & CRUD
    // -----------------------------------------------------------------

    public static function issueCoupon(int $sellerId, array $data): MerchantCoupon
    {
        $tenantId = TenantContext::getId();

        $code = strtoupper(trim($data['code'] ?? self::generateCode()));
        if ($code === '') {
            throw new \InvalidArgumentException(__('api_controllers_2.merchant_coupon.code_required'));
        }

        // Enforce unique-per-tenant
        $exists = MerchantCoupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException(__('api_controllers_2.merchant_coupon.code_in_use'));
        }

        $appliesTo = (string) ($data['applies_to'] ?? 'all_listings');
        $appliesToIds = self::normalizeAppliesToIds(
            $sellerId,
            $appliesTo,
            $data['applies_to_ids'] ?? null,
        );

        $coupon = new MerchantCoupon();
        $coupon->tenant_id = $tenantId;
        $coupon->seller_id = $sellerId;
        $coupon->code = $code;
        $coupon->title = (string) ($data['title'] ?? '');
        $coupon->description = $data['description'] ?? null;
        $coupon->discount_type = (string) ($data['discount_type'] ?? 'percent');
        $coupon->discount_value = (float) ($data['discount_value'] ?? 0);
        $coupon->min_order_cents = isset($data['min_order_cents']) ? (int) $data['min_order_cents'] : null;
        $coupon->max_uses = isset($data['max_uses']) ? (int) $data['max_uses'] : null;
        $coupon->max_uses_per_member = (int) ($data['max_uses_per_member'] ?? 1);
        $coupon->valid_from = !empty($data['valid_from']) ? Carbon::parse($data['valid_from']) : null;
        $coupon->valid_until = !empty($data['valid_until']) ? Carbon::parse($data['valid_until']) : null;
        $coupon->status = (string) ($data['status'] ?? 'draft');
        $coupon->applies_to = $appliesTo;
        $coupon->applies_to_ids = $appliesToIds;
        $coupon->usage_count = 0;
        $coupon->save();

        return $coupon;
    }

    public static function updateCoupon(MerchantCoupon $coupon, array $data): MerchantCoupon
    {
        if (array_key_exists('code', $data)) {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            if ($code === '') {
                throw new \InvalidArgumentException(__('api_controllers_2.merchant_coupon.code_required'));
            }
            $exists = MerchantCoupon::where('tenant_id', $coupon->tenant_id)
                ->where('code', $code)
                ->where('id', '!=', $coupon->id)
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException(__('api_controllers_2.merchant_coupon.code_in_use'));
            }
            $coupon->code = $code;
            unset($data['code']);
        }

        $appliesTo = (string) ($data['applies_to'] ?? $coupon->applies_to ?? 'all_listings');
        $appliesToIds = array_key_exists('applies_to_ids', $data)
            ? $data['applies_to_ids']
            : $coupon->applies_to_ids;
        $data['applies_to'] = $appliesTo;
        $data['applies_to_ids'] = self::normalizeAppliesToIds(
            (int) $coupon->seller_id,
            $appliesTo,
            $appliesToIds,
        );

        $allowed = [
            'title', 'description', 'discount_type', 'discount_value',
            'min_order_cents', 'max_uses', 'max_uses_per_member',
            'valid_from', 'valid_until', 'status', 'applies_to', 'applies_to_ids',
        ];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                if ($k === 'valid_from' || $k === 'valid_until') {
                    $coupon->{$k} = !empty($data[$k]) ? Carbon::parse($data[$k]) : null;
                } else {
                    $coupon->{$k} = $data[$k];
                }
            }
        }
        $coupon->save();
        return $coupon;
    }

    /**
     * @return array<int,int>|null
     */
    private static function normalizeAppliesToIds(int $sellerId, string $appliesTo, mixed $targetIds): ?array
    {
        if ($appliesTo === 'all_listings') {
            return null;
        }
        if (! in_array($appliesTo, ['listing_ids', 'category_ids'], true) || ! is_array($targetIds)) {
            throw new \InvalidArgumentException(__('api.validation_failed'));
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $targetIds),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === [] || count($ids) > 100) {
            throw new \InvalidArgumentException(__('api.validation_failed'));
        }

        $tenantId = (int) TenantContext::getId();
        if ($appliesTo === 'listing_ids') {
            $sellerUserId = MarketplaceSellerProfile::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($sellerId)
                ->value('user_id');
            $validCount = $sellerUserId
                ? MarketplaceListing::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', (int) $sellerUserId)
                    ->whereIn('id', $ids)
                    ->count()
                : 0;
        } else {
            $validCount = DB::table('marketplace_categories')
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->where(static function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->count();
        }

        if ($validCount !== count($ids)) {
            throw new \InvalidArgumentException(__('api.validation_failed'));
        }

        return $ids;
    }

    public static function generateCode(int $length = 8): string
    {
        return strtoupper(Str::random($length));
    }

    // -----------------------------------------------------------------
    //  Validation
    // -----------------------------------------------------------------

    /**
     * Validate a coupon code against an order context.
     * Returns the coupon model if valid; throws \InvalidArgumentException otherwise.
     */
    public static function validateCoupon(
        string $code,
        int $userId,
        int $orderTotalCents,
        ?int $listingId = null,
        ?int $categoryId = null,
        ?string $currency = null,
    ): MerchantCoupon
    {
        $tenantId = TenantContext::getId();
        $code = strtoupper(trim($code));

        $coupon = MerchantCoupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            // During checkout this method runs inside the order transaction.
            // Lock the authoritative coupon snapshot so discount/scope/limits
            // cannot change between validation and redemption.
            ->lockForUpdate()
            ->first();

        if (!$coupon) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_not_found'));
        }

        // Coupon ownership is part of the price authority boundary. A coupon
        // issued by one merchant must never discount another merchant's order.
        // Resolve the listing and seller from tenant-scoped server data rather
        // than trusting the caller's category/seller context.
        if ($listingId !== null) {
            $listing = MarketplaceListing::where('tenant_id', $tenantId)
                ->whereKey($listingId)
                ->first();
            if (!$listing) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_listing_mismatch'));
            }

            $sellerProfileId = MarketplaceSellerProfile::where('tenant_id', $tenantId)
                ->where('user_id', $listing->user_id)
                ->value('id');
            if ($sellerProfileId === null || (int) $coupon->seller_id !== (int) $sellerProfileId) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_seller_mismatch'));
            }

            $categoryId = $listing->category_id !== null ? (int) $listing->category_id : null;
        }

        if ($coupon->status !== 'active') {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_inactive'));
        }

        // Existing fixed discounts and minimum thresholds are stored as cents
        // without a currency column. Reinterpreting them as JPY (or another
        // non-2-decimal minor unit) would silently change issued coupons.
        if ($currency !== null
            && StripeCurrency::exponent($currency) !== 2
            && ($coupon->discount_type === 'fixed' || $coupon->min_order_cents !== null)) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_currency_unsupported'));
        }

        $now = Carbon::now();
        if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_not_started'));
        }
        if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_expired'));
        }

        if ($coupon->min_order_cents !== null && $orderTotalCents < $coupon->min_order_cents) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_minimum_not_met'));
        }

        if ($coupon->max_uses !== null && $coupon->usage_count >= $coupon->max_uses) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_usage_limit'));
        }

        $userUses = MerchantCouponRedemption::where('tenant_id', $tenantId)
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->whereNull('reversed_at')
            ->count();
        if ($userUses >= (int) $coupon->max_uses_per_member) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_member_limit'));
        }

        // Scope check
        if ($coupon->applies_to === 'listing_ids') {
            if ($listingId === null) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_listing_required'));
            }
            $ids = (array) ($coupon->applies_to_ids ?? []);
            if (!in_array($listingId, array_map('intval', $ids), true)) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_listing_mismatch'));
            }
        }
        if ($coupon->applies_to === 'category_ids') {
            if ($categoryId === null) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_category_required'));
            }
            $ids = (array) ($coupon->applies_to_ids ?? []);
            if (!in_array($categoryId, array_map('intval', $ids), true)) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_category_mismatch'));
            }
        }

        return $coupon;
    }

    /** Compute a discount in the order currency's minor unit. */
    public static function calculateDiscountMinor(MerchantCoupon $coupon, int $orderTotalMinor): int
    {
        switch ($coupon->discount_type) {
            case 'percent':
                $pct = max(0.0, min(100.0, (float) $coupon->discount_value));
                return (int) round(($orderTotalMinor * $pct) / 100.0);

            case 'fixed':
                return min($orderTotalMinor, max(0, (int) $coupon->discount_value));

            case 'bogo':
                // Buy-one-get-one — caller must adjust line items; default to 50% off total.
                throw new \InvalidArgumentException(__('api.marketplace_coupon_bogo_quantity_required'));
        }
        return 0;
    }

    /**
     * Backward-compatible name for callers and persisted *_cents fields.
     * Values are currency minor units, which are not always hundredths.
     */
    public static function calculateDiscountCents(MerchantCoupon $coupon, int $orderTotalCents): int
    {
        return self::calculateDiscountMinor($coupon, $orderTotalCents);
    }

    /** Compute checkout discount with authoritative line-item context. */
    public static function calculateOrderDiscountMinor(
        MerchantCoupon $coupon,
        int $orderTotalMinor,
        int $unitPriceMinor,
        int $quantity,
    ): int {
        if ($coupon->discount_type !== 'bogo') {
            return self::calculateDiscountMinor($coupon, $orderTotalMinor);
        }
        if ($quantity < 2 || $unitPriceMinor <= 0) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_bogo_quantity_required'));
        }

        return min($orderTotalMinor, $unitPriceMinor * intdiv($quantity, 2));
    }

    /**
     * Build an authoritative coupon preview from the listing and selected shipping option.
     *
     * @return array{
     *     coupon:MerchantCoupon,
     *     currency:string,
     *     order_total_minor:int,
     *     order_total_amount:float,
     *     discount_minor:int,
     *     discount_amount:float
     * }
     */
    public static function quoteForListing(
        string $code,
        int $userId,
        int $listingId,
        ?int $shippingOptionId = null,
        int $quantity = 1,
    ): array {
        $tenantId = TenantContext::getId();
        $listing = MarketplaceListing::where('tenant_id', $tenantId)
            ->whereKey($listingId)
            ->first();
        if (! $listing) {
            throw new \InvalidArgumentException(__('api.marketplace_coupon_listing_mismatch'));
        }

        $currency = StripeCurrency::normalize(
            (string) ($listing->price_currency ?: TenantContext::getCurrency()),
        );
        $orderTotalAmount = (float) ($listing->price ?? 0) * max(1, $quantity);

        if ($shippingOptionId !== null) {
            $sellerProfileId = MarketplaceSellerProfile::where('tenant_id', $tenantId)
                ->where('user_id', $listing->user_id)
                ->value('id');
            $shippingOption = $sellerProfileId === null ? null : MarketplaceShippingOption::where('tenant_id', $tenantId)
                ->where('seller_id', $sellerProfileId)
                ->whereKey($shippingOptionId)
                ->where('is_active', true)
                ->first();
            if (! $shippingOption) {
                throw new \InvalidArgumentException(__('api.marketplace_shipping_option_unavailable'));
            }
            if (StripeCurrency::normalize((string) $shippingOption->currency) !== $currency) {
                throw new \InvalidArgumentException(__('api.marketplace_shipping_currency_mismatch'));
            }
            $orderTotalAmount += (float) $shippingOption->price;
        }

        $orderTotalMinor = StripeCurrency::toMinor($orderTotalAmount, $currency);
        $unitPriceMinor = StripeCurrency::toMinor((float) ($listing->price ?? 0), $currency);
        $coupon = self::validateCoupon(
            $code,
            $userId,
            $orderTotalMinor,
            (int) $listing->id,
            $listing->category_id !== null ? (int) $listing->category_id : null,
            $currency,
        );
        $discountMinor = self::calculateOrderDiscountMinor(
            $coupon,
            $orderTotalMinor,
            $unitPriceMinor,
            max(1, $quantity),
        );

        return [
            'coupon' => $coupon,
            'currency' => $currency,
            'order_total_minor' => $orderTotalMinor,
            'order_total_amount' => StripeCurrency::fromMinor($orderTotalMinor, $currency),
            'discount_minor' => $discountMinor,
            'discount_amount' => StripeCurrency::fromMinor($discountMinor, $currency),
        ];
    }

    // -----------------------------------------------------------------
    //  Redemption (atomic)
    // -----------------------------------------------------------------

    /**
     * Redeem a coupon for an order. Atomic: locks coupon row, checks limits,
     * inserts redemption, increments usage_count.
     */
    public static function redeemForOrder(
        int $couponId,
        ?int $orderId,
        int $userId,
        string $method = 'online',
        ?int $authoritativeDiscountMinor = null,
    ): MerchantCouponRedemption
    {
        $tenantId = TenantContext::getId();

        return DB::transaction(function () use (
            $couponId,
            $orderId,
            $userId,
            $method,
            $tenantId,
            $authoritativeDiscountMinor,
        ) {
            $coupon = MerchantCoupon::where('id', $couponId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$coupon) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_not_found'));
            }
            if ($coupon->status !== 'active') {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_inactive'));
            }
            $now = Carbon::now();
            if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_not_started'));
            }
            if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_expired'));
            }
            if ($coupon->max_uses !== null && $coupon->usage_count >= $coupon->max_uses) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_usage_limit'));
            }

            $userUses = MerchantCouponRedemption::where('tenant_id', $tenantId)
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->whereNull('reversed_at')
                ->count();
            if ($userUses >= (int) $coupon->max_uses_per_member) {
                throw new \InvalidArgumentException(__('api.marketplace_coupon_member_limit'));
            }

            $orderTotalMinor = 0;
            $unitPriceMinor = 0;
            $quantity = 0;
            if ($orderId && $authoritativeDiscountMinor === null) {
                $order = MarketplaceOrder::where('id', $orderId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($order) {
                    $preDiscountAmount = ((float) $order->unit_price * (int) $order->quantity)
                        + (float) ($order->shipping_cost ?? 0);
                    $orderTotalMinor = StripeCurrency::toMinor(
                        $preDiscountAmount,
                        (string) $order->currency,
                    );
                    $unitPriceMinor = StripeCurrency::toMinor(
                        (float) $order->unit_price,
                        (string) $order->currency,
                    );
                    $quantity = (int) $order->quantity;
                }
            }
            $discountMinor = $authoritativeDiscountMinor
                ?? self::calculateOrderDiscountMinor(
                    $coupon,
                    $orderTotalMinor,
                    $unitPriceMinor,
                    $quantity,
                );

            $red = new MerchantCouponRedemption();
            $red->coupon_id = $coupon->id;
            $red->tenant_id = $tenantId;
            $red->user_id = $userId;
            $red->order_id = $orderId;
            // Legacy column name; the stored value is the order currency's minor unit.
            $red->discount_applied_cents = $discountMinor;
            $red->redeemed_at = $now;
            $red->redemption_method = $method;
            $red->save();

            $coupon->increment('usage_count');

            return $red;
        });
    }

    /** Release a coupon claim when an unpaid order is cancelled or expires. */
    public static function releaseForOrder(int $orderId, string $reason): void
    {
        $tenantId = TenantContext::getId();

        DB::transaction(function () use ($tenantId, $orderId, $reason): void {
            $redemption = MerchantCouponRedemption::where('tenant_id', $tenantId)
                ->where('order_id', $orderId)
                ->whereNull('reversed_at')
                ->lockForUpdate()
                ->first();
            if (! $redemption) {
                return;
            }

            $coupon = MerchantCoupon::where('tenant_id', $tenantId)
                ->whereKey($redemption->coupon_id)
                ->lockForUpdate()
                ->first();
            $redemption->reversed_at = now();
            $redemption->reversal_reason = mb_substr($reason, 0, 100);
            $redemption->save();

            if ($coupon && (int) $coupon->usage_count > 0) {
                $coupon->usage_count = (int) $coupon->usage_count - 1;
                $coupon->save();
            }
        });
    }

    // -----------------------------------------------------------------
    //  QR token flow (in-store redemption)
    // -----------------------------------------------------------------

    /**
     * Generate a one-time QR token (5-minute expiry) for a member to show staff.
     * Creates a pending redemption row with qr_token populated; finalised when staff scans.
     */
    public static function generateQrToken(int $couponId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        $coupon = MerchantCoupon::where('id', $couponId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Cheap pre-flight check (reuses validation rules without locking).
        self::validateCoupon($coupon->code, $userId, 0);

        $token = strtoupper(Str::random(16));
        $expiresAt = Carbon::now()->addSeconds(self::QR_TTL_SECONDS);

        // Cache the token in Redis for fast lookup; row is inserted on actual redemption.
        try {
            \Illuminate\Support\Facades\Cache::put(
                "merchant_coupon_qr:{$tenantId}:{$token}",
                ['coupon_id' => $coupon->id, 'user_id' => $userId, 'expires_at' => $expiresAt->toIso8601String()],
                self::QR_TTL_SECONDS
            );
        } catch (\Throwable $e) {
            Log::warning('[MerchantCouponService] QR cache put failed: ' . $e->getMessage());
        }

        return [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'coupon_code' => $coupon->code,
        ];
    }

    /**
     * Staff-side: redeem a QR token.
     */
    public static function redeemQrToken(string $token, int $staffUserId): MerchantCouponRedemption
    {
        $tenantId = TenantContext::getId();
        $normalizedToken = strtoupper(trim($token));
        $key = "merchant_coupon_qr:{$tenantId}:{$normalizedToken}";
        try {
            return \Illuminate\Support\Facades\Cache::lock("{$key}:claim", 10)->block(
                3,
                function () use ($key, $tenantId, $staffUserId, $normalizedToken): MerchantCouponRedemption {
                    $payload = \Illuminate\Support\Facades\Cache::get($key);
                    if (! $payload || ! is_array($payload)) {
                        throw new \InvalidArgumentException(__('api.marketplace_coupon_qr_invalid'));
                    }

                    $couponId = (int) ($payload['coupon_id'] ?? 0);
                    $userId = (int) ($payload['user_id'] ?? 0);
                    if ($couponId <= 0 || $userId <= 0) {
                        throw new \InvalidArgumentException(__('api.marketplace_coupon_qr_malformed'));
                    }

                    $coupon = MerchantCoupon::where('id', $couponId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if (! $coupon) {
                        throw new \InvalidArgumentException(__('api_controllers_2.merchant_coupon.not_found'));
                    }

                    $isSellerStaff = MarketplaceSellerProfile::where('id', $coupon->seller_id)
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $staffUserId)
                        ->exists();
                    if (! $isSellerStaff) {
                        throw new \InvalidArgumentException(__('api.admin_access_required'));
                    }

                    // Consume the token before the database write. A competing
                    // scanner can no longer pass the claim boundary.
                    if (! \Illuminate\Support\Facades\Cache::forget($key)) {
                        throw new \InvalidArgumentException(__('api.marketplace_coupon_qr_invalid'));
                    }

                    $redemption = self::redeemForOrder($couponId, null, $userId, 'qr_scan');
                    $redemption->qr_token = $normalizedToken;
                    $redemption->save();

                    return $redemption;
                },
            );
        } catch (\InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable $e) {
            Log::warning('[MerchantCouponService] QR token claim failed: ' . $e->getMessage());
            throw new \InvalidArgumentException(__('api.marketplace_coupon_qr_invalid'));
        }
    }

    // -----------------------------------------------------------------
    //  Listing
    // -----------------------------------------------------------------

    public static function listForMember(?int $sellerId = null): array
    {
        $tenantId = TenantContext::getId();
        $now = Carbon::now();

        $q = MerchantCoupon::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });

        if ($sellerId) {
            $q->where('seller_id', $sellerId);
        }

        return $q->orderByDesc('created_at')->limit(200)->get()->all();
    }

    public static function listForMerchant(int $sellerId): array
    {
        $tenantId = TenantContext::getId();
        return MerchantCoupon::where('tenant_id', $tenantId)
            ->where('seller_id', $sellerId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public static function listAllForAdmin(): array
    {
        $tenantId = TenantContext::getId();
        return MerchantCoupon::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->all();
    }

    public static function listRedemptions(int $couponId): array
    {
        $tenantId = TenantContext::getId();
        return MerchantCouponRedemption::where('tenant_id', $tenantId)
            ->where('coupon_id', $couponId)
            ->orderByDesc('redeemed_at')
            ->limit(500)
            ->get()
            ->all();
    }

    public static function format(MerchantCoupon $c): array
    {
        return [
            'id' => $c->id,
            'seller_id' => $c->seller_id,
            'code' => $c->code,
            'title' => $c->title,
            'description' => $c->description,
            'discount_type' => $c->discount_type,
            'discount_value' => (float) $c->discount_value,
            'min_order_cents' => $c->min_order_cents,
            'max_uses' => $c->max_uses,
            'max_uses_per_member' => $c->max_uses_per_member,
            'valid_from' => $c->valid_from?->toIso8601String(),
            'valid_until' => $c->valid_until?->toIso8601String(),
            'status' => $c->status,
            'applies_to' => $c->applies_to,
            'applies_to_ids' => $c->applies_to_ids,
            'usage_count' => $c->usage_count,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
