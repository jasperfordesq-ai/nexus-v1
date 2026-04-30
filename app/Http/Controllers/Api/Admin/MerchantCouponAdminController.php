<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\MerchantCoupon;
use App\Services\MerchantCouponService;
use Illuminate\Http\JsonResponse;

/**
 * Admin oversight for AG63 merchant coupons — view, suspend, delete.
 */
class MerchantCouponAdminController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /v2/admin/marketplace/coupons
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $coupons = MerchantCouponService::listAllForAdmin();
        return $this->respondWithData([
            'items' => array_map([MerchantCouponService::class, 'format'], $coupons),
        ]);
    }

    /**
     * POST /v2/admin/marketplace/coupons/{id}/suspend
     */
    public function suspend(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $coupon = MerchantCoupon::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$coupon) {
            return $this->respondWithError('NOT_FOUND', 'Coupon not found.', null, 404);
        }
        $coupon->status = 'paused';
        $coupon->save();
        return $this->respondWithData(MerchantCouponService::format($coupon));
    }

    /**
     * DELETE /v2/admin/marketplace/coupons/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $coupon = MerchantCoupon::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$coupon) {
            return $this->respondWithError('NOT_FOUND', 'Coupon not found.', null, 404);
        }
        $coupon->delete();
        return $this->respondWithData(['deleted' => true]);
    }
}
