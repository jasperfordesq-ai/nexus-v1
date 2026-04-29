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
use App\Models\MerchantCoupon;
use App\Models\MerchantCouponRedemption;
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
            throw new \InvalidArgumentException('Coupon code is required.');
        }

        // Enforce unique-per-tenant
        $exists = MerchantCoupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException('Coupon code already in use for this tenant.');
        }

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
        $coupon->applies_to = (string) ($data['applies_to'] ?? 'all_listings');
        $coupon->applies_to_ids = $data['applies_to_ids'] ?? null;
        $coupon->usage_count = 0;
        $coupon->save();

        return $coupon;
    }

    public static function updateCoupon(MerchantCoupon $coupon, array $data): MerchantCoupon
    {
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
    public static function validateCoupon(string $code, int $userId, int $orderTotalCents, ?int $listingId = null, ?int $categoryId = null): MerchantCoupon
    {
        $tenantId = TenantContext::getId();
        $code = strtoupper(trim($code));

        $coupon = MerchantCoupon::where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if (!$coupon) {
            throw new \InvalidArgumentException('Coupon not found.');
        }

        if ($coupon->status !== 'active') {
            throw new \InvalidArgumentException('Coupon is not active.');
        }

        $now = Carbon::now();
        if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
            throw new \InvalidArgumentException('Coupon is not yet valid.');
        }
        if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
            throw new \InvalidArgumentException('Coupon has expired.');
        }

        if ($coupon->min_order_cents !== null && $orderTotalCents < $coupon->min_order_cents) {
            throw new \InvalidArgumentException('Order does not meet the minimum value for this coupon.');
        }

        if ($coupon->max_uses !== null && $coupon->usage_count >= $coupon->max_uses) {
            throw new \InvalidArgumentException('Coupon has reached its usage limit.');
        }

        $userUses = MerchantCouponRedemption::where('tenant_id', $tenantId)
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->count();
        if ($userUses >= (int) $coupon->max_uses_per_member) {
            throw new \InvalidArgumentException('You have already used this coupon the maximum number of times.');
        }

        // Scope check
        if ($coupon->applies_to === 'listing_ids' && $listingId) {
            $ids = (array) ($coupon->applies_to_ids ?? []);
            if (!in_array($listingId, array_map('intval', $ids), true)) {
                throw new \InvalidArgumentException('Coupon does not apply to this listing.');
            }
        }
        if ($coupon->applies_to === 'category_ids' && $categoryId) {
            $ids = (array) ($coupon->applies_to_ids ?? []);
            if (!in_array($categoryId, array_map('intval', $ids), true)) {
                throw new \InvalidArgumentException('Coupon does not apply to this category.');
            }
        }

        return $coupon;
    }

    /**
     * Compute discount in cents for a given order total.
     */
    public static function calculateDiscountCents(MerchantCoupon $coupon, int $orderTotalCents): int
    {
        switch ($coupon->discount_type) {
            case 'percent':
                $pct = max(0.0, min(100.0, (float) $coupon->discount_value));
                return (int) round(($orderTotalCents * $pct) / 100.0);

            case 'fixed':
                return min($orderTotalCents, max(0, (int) $coupon->discount_value));

            case 'bogo':
                // Buy-one-get-one — caller must adjust line items; default to 50% off total.
                return (int) round($orderTotalCents / 2);
        }
        return 0;
    }

    // -----------------------------------------------------------------
    //  Redemption (atomic)
    // -----------------------------------------------------------------

    /**
     * Redeem a coupon for an order. Atomic: locks coupon row, checks limits,
     * inserts redemption, increments usage_count.
     */
    public static function redeemForOrder(int $couponId, ?int $orderId, int $userId, string $method = 'online'): MerchantCouponRedemption
    {
        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($couponId, $orderId, $userId, $method, $tenantId) {
            $coupon = MerchantCoupon::where('id', $couponId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$coupon) {
                throw new \InvalidArgumentException('Coupon not found.');
            }
            if ($coupon->status !== 'active') {
                throw new \InvalidArgumentException('Coupon is not active.');
            }
            $now = Carbon::now();
            if ($coupon->valid_from && $now->lt($coupon->valid_from)) {
                throw new \InvalidArgumentException('Coupon is not yet valid.');
            }
            if ($coupon->valid_until && $now->gt($coupon->valid_until)) {
                throw new \InvalidArgumentException('Coupon has expired.');
            }
            if ($coupon->max_uses !== null && $coupon->usage_count >= $coupon->max_uses) {
                throw new \InvalidArgumentException('Coupon has reached its usage limit.');
            }

            $userUses = MerchantCouponRedemption::where('tenant_id', $tenantId)
                ->where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            if ($userUses >= (int) $coupon->max_uses_per_member) {
                throw new \InvalidArgumentException('You have already used this coupon the maximum number of times.');
            }

            $orderTotalCents = 0;
            if ($orderId) {
                $order = MarketplaceOrder::where('id', $orderId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($order) {
                    $orderTotalCents = (int) round(((float) $order->total_price) * 100);
                }
            }
            $discountCents = self::calculateDiscountCents($coupon, $orderTotalCents);

            $red = new MerchantCouponRedemption();
            $red->coupon_id = $coupon->id;
            $red->tenant_id = $tenantId;
            $red->user_id = $userId;
            $red->order_id = $orderId;
            $red->discount_applied_cents = $discountCents;
            $red->redeemed_at = $now;
            $red->redemption_method = $method;
            $red->save();

            $coupon->increment('usage_count');

            return $red;
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
    public static function redeemQrToken(string $token): MerchantCouponRedemption
    {
        $tenantId = TenantContext::getId();
        $key = "merchant_coupon_qr:{$tenantId}:" . strtoupper(trim($token));
        $payload = \Illuminate\Support\Facades\Cache::get($key);

        if (!$payload || !is_array($payload)) {
            throw new \InvalidArgumentException('QR token is invalid or has expired.');
        }

        $couponId = (int) ($payload['coupon_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($couponId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('QR token payload is malformed.');
        }

        $redemption = self::redeemForOrder($couponId, null, $userId, 'qr_scan');
        $redemption->qr_token = strtoupper(trim($token));
        $redemption->save();

        try {
            \Illuminate\Support\Facades\Cache::forget($key);
        } catch (\Throwable $e) {
            Log::warning('[MerchantCouponService] QR cache forget failed: ' . $e->getMessage());
        }

        return $redemption;
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
