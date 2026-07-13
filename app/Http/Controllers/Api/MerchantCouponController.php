<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MerchantCoupon;
use App\Services\MerchantCouponService;
use Illuminate\Http\JsonResponse;

/**
 * MerchantCouponController — member-facing coupon browsing & redemption.
 *
 * AG63 — discount/coupon system distinct from AG18 closed-loop redemption.
 */
class MerchantCouponController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            abort(403, __('api.marketplace_feature_disabled'));
        }
        if (!TenantContext::hasFeature('merchant_coupons')) {
            abort(403, __('api.marketplace_merchant_coupons_disabled'));
        }
    }

    /**
     * GET /v2/coupons — list active coupons for member browsing.
     */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAuth();
        $this->rateLimit('coupon_index', 30, 60);

        $sellerId = request()->query('seller_id');
        $sellerId = $sellerId !== null ? (int) $sellerId : null;

        $coupons = MerchantCouponService::listForMember($sellerId);
        return $this->respondWithData([
            'items' => array_map([MerchantCouponService::class, 'format'], $coupons),
        ]);
    }

    /**
     * GET /v2/coupons/{id} — coupon detail.
     */
    public function show(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAuth();

        $tenantId = TenantContext::getId();
        $coupon = MerchantCoupon::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$coupon) {
            return $this->respondWithError('NOT_FOUND', 'Coupon not found.', null, 404);
        }
        return $this->respondWithData(MerchantCouponService::format($coupon));
    }

    /**
     * POST /v2/coupons/{id}/qr — generate one-time QR token (5-min expiry).
     */
    public function generateQr(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('coupon_qr_generate', 10, 60);

        try {
            $payload = MerchantCouponService::generateQrToken($id, $userId);
            return $this->respondWithData($payload);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * POST /v2/coupons/redeem-qr — staff scans QR and redeems.
     */
    public function redeemQr(): JsonResponse
    {
        $this->ensureFeature();
        $staffUserId = $this->requireAuth();
        $this->rateLimit('coupon_qr_redeem', 30, 60);

        $data = request()->validate([
            'token' => 'required|string|max:64',
        ]);

        try {
            $redemption = MerchantCouponService::redeemQrToken($data['token'], $staffUserId);
            return $this->respondWithData([
                'redemption_id' => $redemption->id,
                'coupon_id' => $redemption->coupon_id,
                'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * POST /v2/coupons/validate — validate a code for an order context.
     */
    public function validateCode(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('coupon_validate', 30, 60);

        $data = request()->validate([
            'code' => 'required|string|max:64',
            // Retained as an ignored compatibility field. Price authority comes
            // from the listing and seller-owned shipping option below.
            'order_total_cents' => 'nullable|integer|min:0',
            'listing_id' => 'required|integer|min:1',
            'shipping_option_id' => 'nullable|integer|min:1',
            'quantity' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $quote = MerchantCouponService::quoteForListing(
                $data['code'],
                $userId,
                (int) $data['listing_id'],
                isset($data['shipping_option_id']) ? (int) $data['shipping_option_id'] : null,
                (int) ($data['quantity'] ?? 1),
            );
            return $this->respondWithData([
                'coupon' => MerchantCouponService::format($quote['coupon']),
                // Compatibility key: this is the currency's minor unit, not
                // necessarily a hundredth for zero-decimal currencies.
                'discount_cents' => $quote['discount_minor'],
                'discount_amount' => $quote['discount_amount'],
                'currency' => $quote['currency'],
                'order_total_amount' => $quote['order_total_amount'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }
}
