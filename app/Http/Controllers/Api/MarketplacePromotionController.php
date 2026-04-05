<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Services\MarketplaceConfigurationService;
use App\Services\MarketplacePromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MarketplacePromotionController — Paid promotion endpoints.
 *
 * All endpoints require authentication (auth:sanctum).
 */
class MarketplacePromotionController extends BaseApiController
{
    protected bool $isV2Api = true;

    // =====================================================================
    //  Feature gate
    // =====================================================================

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'The marketplace feature is not enabled for this community.', null, 403)
            );
        }
    }

    private function ensurePromotionsEnabled(): void
    {
        $enabled = MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_PROMOTIONS_ENABLED,
            false
        );

        if (!$enabled) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Promotions are not enabled for this marketplace.', null, 403)
            );
        }
    }

    // =====================================================================
    //  Endpoints
    // =====================================================================

    /**
     * GET /v2/marketplace/promotions/products
     *
     * Returns available promotion types with pricing.
     */
    public function products(Request $request): JsonResponse
    {
        $this->ensureFeature();
        $this->ensurePromotionsEnabled();

        return $this->respondWithData(array_values(MarketplacePromotionService::getProducts()));
    }

    /**
     * POST /v2/marketplace/listings/{id}/promote
     *
     * Create a promotion for a listing.
     */
    public function promote(Request $request, int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->ensurePromotionsEnabled();

        $userId = $request->user()->id;

        $listing = MarketplaceListing::find($id);
        if (!$listing) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Listing not found.', null, 404);
        }

        if ((int) $listing->user_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You can only promote your own listings.', null, 403);
        }

        $request->validate([
            'promotion_type' => 'required|string|in:bump,featured,top_of_category,homepage_carousel',
        ]);

        try {
            $promotion = MarketplacePromotionService::createPromotion(
                $userId,
                $id,
                $request->input('promotion_type')
            );

            return $this->respondWithData([
                'id' => $promotion->id,
                'promotion_type' => $promotion->promotion_type,
                'amount_paid' => $promotion->amount_paid,
                'currency' => $promotion->currency,
                'started_at' => $promotion->started_at?->toISOString(),
                'expires_at' => $promotion->expires_at?->toISOString(),
                'is_active' => $promotion->is_active,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_INPUT', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /v2/marketplace/listings/{id}/promotion
     *
     * Get active promotion for a listing.
     */
    public function showPromotion(Request $request, int $id): JsonResponse
    {
        $this->ensureFeature();

        $promotion = MarketplacePromotionService::getActivePromotionForListing($id);

        if (!$promotion) {
            return $this->respondWithData(null);
        }

        return $this->respondWithData([
            'id' => $promotion->id,
            'promotion_type' => $promotion->promotion_type,
            'amount_paid' => $promotion->amount_paid,
            'currency' => $promotion->currency,
            'started_at' => $promotion->started_at?->toISOString(),
            'expires_at' => $promotion->expires_at?->toISOString(),
            'is_active' => $promotion->is_active,
            'impressions' => $promotion->impressions,
            'clicks' => $promotion->clicks,
        ]);
    }

    /**
     * GET /v2/marketplace/promotions/mine
     *
     * Get all active promotions for the authenticated user.
     */
    public function myPromotions(Request $request): JsonResponse
    {
        $this->ensureFeature();
        $userId = $request->user()->id;

        $promotions = MarketplacePromotionService::getActivePromotions($userId);

        return $this->respondWithData(
            array_map(fn ($p) => [
                'id' => $p->id,
                'promotion_type' => $p->promotion_type,
                'amount_paid' => $p->amount_paid,
                'currency' => $p->currency,
                'started_at' => $p->started_at?->toISOString(),
                'expires_at' => $p->expires_at?->toISOString(),
                'is_active' => $p->is_active,
                'impressions' => $p->impressions,
                'clicks' => $p->clicks,
                'listing' => $p->listing ? [
                    'id' => $p->listing->id,
                    'title' => $p->listing->title,
                    'status' => $p->listing->status,
                ] : null,
            ], $promotions)
        );
    }
}
