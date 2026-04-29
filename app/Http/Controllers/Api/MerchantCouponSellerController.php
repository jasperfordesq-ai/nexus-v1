<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceSellerProfile;
use App\Models\MerchantCoupon;
use App\Models\MerchantCouponRedemption;
use App\Services\MerchantCouponService;
use Illuminate\Http\JsonResponse;

/**
 * MerchantCouponSellerController — seller-side CRUD for owned coupons.
 */
class MerchantCouponSellerController extends BaseApiController
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

    private function getSellerProfile(int $userId): MarketplaceSellerProfile
    {
        $tenantId = TenantContext::getId();
        $profile = MarketplaceSellerProfile::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
        if (!$profile) {
            abort(403, 'You must have an active seller profile to manage coupons.');
        }
        return $profile;
    }

    private function ownCouponOrFail(int $id, int $sellerId): MerchantCoupon
    {
        $tenantId = TenantContext::getId();
        $coupon = MerchantCoupon::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $sellerId)
            ->first();
        if (!$coupon) {
            abort(404, 'Coupon not found.');
        }
        return $coupon;
    }

    /**
     * GET /v2/marketplace/seller/coupons — list this seller's coupons.
     */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $profile = $this->getSellerProfile($userId);

        $coupons = MerchantCouponService::listForMerchant($profile->id);
        return $this->respondWithData([
            'items' => array_map([MerchantCouponService::class, 'format'], $coupons),
        ]);
    }

    /**
     * POST /v2/marketplace/seller/coupons
     */
    public function store(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $profile = $this->getSellerProfile($userId);
        $this->rateLimit('coupon_seller_create', 20, 60);

        $data = request()->validate([
            'code' => 'nullable|string|max:64',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'discount_type' => 'required|in:percent,fixed,bogo',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_cents' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_member' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'status' => 'nullable|in:draft,active,paused,expired',
            'applies_to' => 'nullable|in:all_listings,listing_ids,category_ids',
            'applies_to_ids' => 'nullable|array',
        ]);

        try {
            $coupon = MerchantCouponService::issueCoupon($profile->id, $data);
            return $this->respondWithData(MerchantCouponService::format($coupon), null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * PUT /v2/marketplace/seller/coupons/{id}
     */
    public function update(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $profile = $this->getSellerProfile($userId);
        $coupon = $this->ownCouponOrFail($id, $profile->id);

        $data = request()->validate([
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'discount_type' => 'nullable|in:percent,fixed,bogo',
            'discount_value' => 'nullable|numeric|min:0',
            'min_order_cents' => 'nullable|integer|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_member' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date',
            'status' => 'nullable|in:draft,active,paused,expired',
            'applies_to' => 'nullable|in:all_listings,listing_ids,category_ids',
            'applies_to_ids' => 'nullable|array',
        ]);

        $coupon = MerchantCouponService::updateCoupon($coupon, $data);
        return $this->respondWithData(MerchantCouponService::format($coupon));
    }

    /**
     * DELETE /v2/marketplace/seller/coupons/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $profile = $this->getSellerProfile($userId);
        $coupon = $this->ownCouponOrFail($id, $profile->id);

        $coupon->delete();
        return $this->respondWithData(['deleted' => true]);
    }

    /**
     * GET /v2/marketplace/seller/coupons/{id}/redemptions
     */
    public function redemptions(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $profile = $this->getSellerProfile($userId);
        $coupon = $this->ownCouponOrFail($id, $profile->id);

        $redemptions = MerchantCouponService::listRedemptions($coupon->id);
        $items = array_map(function (MerchantCouponRedemption $r) {
            return [
                'id' => $r->id,
                'coupon_id' => $r->coupon_id,
                'user_id' => $r->user_id,
                'order_id' => $r->order_id,
                'discount_applied_cents' => $r->discount_applied_cents,
                'redeemed_at' => $r->redeemed_at?->toIso8601String(),
                'redemption_method' => $r->redemption_method,
            ];
        }, $redemptions);

        return $this->respondWithData(['items' => $items]);
    }
}
