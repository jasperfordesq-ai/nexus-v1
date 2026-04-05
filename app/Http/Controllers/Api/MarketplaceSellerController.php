<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\MarketplaceSellerService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplaceSellerController — Seller profiles and dashboard for the marketplace module.
 *
 * Endpoints (v2):
 *   GET  /v2/marketplace/sellers/{id}           show()          — public seller profile
 *   GET  /v2/marketplace/sellers/{id}/listings   listings()      — seller's public listings
 *   POST /v2/marketplace/seller/profile          updateProfile() — create/update own profile (auth)
 *   GET  /v2/marketplace/seller/dashboard        dashboard()     — seller dashboard stats (auth)
 *   GET  /v2/marketplace/seller/onboard/status   onboardStatus() — Stripe onboarding status (auth)
 */
class MarketplaceSellerController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Ensure the marketplace feature is enabled for the current tenant.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'The marketplace feature is not enabled for this community.', null, 403)
            );
        }
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/sellers/{id}
    // -----------------------------------------------------------------

    /**
     * Get a public seller profile.
     */
    public function show(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_seller_view', 60, 60);

        $profile = MarketplaceSellerService::getPublicProfile($id);

        if ($profile === null) {
            return $this->respondWithError('NOT_FOUND', 'Seller profile not found.', null, 404);
        }

        return $this->respondWithData($profile);
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/sellers/{id}/listings
    // -----------------------------------------------------------------

    /**
     * Get a seller's public listings.
     */
    public function listings(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_seller_listings', 60, 60);

        // Resolve the seller profile to get the user_id
        $profile = MarketplaceSellerService::getById($id);
        if (!$profile) {
            return $this->respondWithError('NOT_FOUND', 'Seller profile not found.', null, 404);
        }

        $limit = $this->queryInt('per_page', 20, 1, 100);
        $cursor = $this->query('cursor');

        $result = MarketplaceSellerService::getSellerListings($profile->user_id, $limit, $cursor);

        return $this->respondWithCollection(
            $result['items'] ?? [],
            $result['cursor'] ?? null,
            $limit,
            $result['has_more'] ?? false
        );
    }

    // -----------------------------------------------------------------
    //  POST /v2/marketplace/seller/profile
    // -----------------------------------------------------------------

    /**
     * Create or update the authenticated user's seller profile.
     */
    public function updateProfile(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_seller_update', 30, 60);

        $userId = $this->requireAuth();

        $validated = request()->validate([
            'display_name' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'seller_type' => 'nullable|string|in:private,business',
            'business_name' => 'nullable|string|max:200|required_if:seller_type,business',
        ]);

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $profile = MarketplaceSellerService::update($profile, $validated);

        return $this->respondWithData($profile->toArray());
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/seller/dashboard
    // -----------------------------------------------------------------

    /**
     * Get seller dashboard stats for the authenticated user.
     */
    public function dashboard(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_seller_dashboard', 60, 60);

        $userId = $this->requireAuth();

        // Ensure the seller profile exists
        MarketplaceSellerService::getOrCreateProfile($userId);

        $stats = MarketplaceSellerService::getDashboardStats($userId);

        return $this->respondWithData($stats);
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/seller/onboard/status
    // -----------------------------------------------------------------

    /**
     * Get Stripe Connect onboarding status for the authenticated seller.
     */
    public function onboardStatus(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('marketplace_seller_onboard', 30, 60);

        $userId = $this->requireAuth();

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);

        return $this->respondWithData([
            'stripe_account_id' => $profile->stripe_account_id,
            'stripe_onboarding_complete' => (bool) $profile->stripe_onboarding_complete,
        ]);
    }
}
