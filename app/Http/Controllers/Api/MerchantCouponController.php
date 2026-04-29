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
            abort(403, 'Marketplace feature is not enabled for this tenant.');
        }
        if (!TenantContext::hasFeature('merchant_coupons')) {
            abort(403, 'Merchant coupons are not enabled for this tenant.');
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
        $this->requireAuth();
        $this->rateLimit('coupon_qr_redeem', 30, 60);

        $data = request()->validate([
            'token' => 'required|string|max:64',
        ]);

        try {
            $redemption = MerchantCouponService::redeemQrToken($data['token']);
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
            'order_total_cents' => 'required|integer|min:0',
            'listing_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
        ]);

        try {
            $coupon = MerchantCouponService::validateCoupon(
                $data['code'],
                $userId,
                (int) $data['order_total_cents'],
                $data['listing_id'] ?? null,
                $data['category_id'] ?? null
            );
            $discount = MerchantCouponService::calculateDiscountCents($coupon, (int) $data['order_total_cents']);
            return $this->respondWithData([
                'coupon' => MerchantCouponService::format($coupon),
                'discount_cents' => $discount,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }
}
