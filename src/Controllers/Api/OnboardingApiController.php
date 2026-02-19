<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\OnboardingService;

/**
 * OnboardingApiController - RESTful API v2 for user onboarding wizard
 *
 * Handles the post-registration 4-step onboarding process:
 * 1. Welcome (no API call needed)
 * 2. Select interests (categories)
 * 3. Select skills (offers/needs)
 * 4. Confirm and auto-create listings
 *
 * Endpoints:
 * - GET  /api/v2/onboarding/status       - Get onboarding completion status
 * - GET  /api/v2/onboarding/categories   - Get available categories for selection
 * - PUT  /api/v2/users/me/interests      - Save user interests
 * - PUT  /api/v2/users/me/skills         - Save user skills (offers/needs)
 * - POST /api/v2/onboarding/complete     - Complete onboarding and auto-create listings
 *
 * Response Format (v2):
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 *
 * @package Nexus\Controllers\Api
 */
class OnboardingApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/onboarding/status
     *
     * Get the current user's onboarding completion status and existing interests.
     *
     * Response: 200 OK
     * {
     *   "data": {
     *     "onboarding_completed": true/false,
     *     "interests": [...]
     *   }
     * }
     */
    public function status(): void
    {
        $userId = $this->getUserId();

        $complete = OnboardingService::isOnboardingComplete($userId);
        $interests = OnboardingService::getUserInterests($userId);

        $this->respondWithData([
            'onboarding_completed' => $complete,
            'interests' => $interests,
        ]);
    }

    /**
     * GET /api/v2/onboarding/categories
     *
     * Get available top-level categories for the current tenant.
     * Used to populate the interest and skill selection grids.
     *
     * Response: 200 OK
     * {
     *   "data": [
     *     { "id": 1, "name": "Gardening", "slug": "gardening", "icon": null, "color": null }
     *   ]
     * }
     */
    public function categories(): void
    {
        $this->getUserId(); // Require authentication

        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT id, name, slug, color FROM categories WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        );
        $categories = $stmt->fetchAll();

        $this->respondWithData($categories);
    }

    /**
     * PUT /api/v2/users/me/interests
     *
     * Save the user's category interests (Step 2 of onboarding).
     * Replaces any existing interest selections.
     *
     * Request Body:
     * {
     *   "category_ids": [1, 3, 5]
     * }
     *
     * Response: 200 OK
     * { "data": { "message": "Interests saved" } }
     */
    public function saveInterests(): void
    {
        $userId = $this->getUserId();

        $categoryIds = $this->input('category_ids', []);

        if (empty($categoryIds) || !is_array($categoryIds)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'At least one category is required',
                'category_ids',
                400
            );
            return; // Safety return (respondWithError calls exit)
        }

        // Sanitize: ensure all IDs are integers
        $categoryIds = array_map('intval', $categoryIds);
        $categoryIds = array_filter($categoryIds, fn($id) => $id > 0);

        if (empty($categoryIds)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'At least one valid category ID is required',
                'category_ids',
                400
            );
            return;
        }

        OnboardingService::saveInterests($userId, $categoryIds);

        $this->respondWithData(['message' => 'Interests saved']);
    }

    /**
     * PUT /api/v2/users/me/skills
     *
     * Save the user's skill offers and needs (Step 3 of onboarding).
     * Replaces any existing skill selections.
     *
     * Request Body:
     * {
     *   "offers": [1, 3],
     *   "needs": [5, 7]
     * }
     *
     * Response: 200 OK
     * { "data": { "message": "Skills saved" } }
     */
    public function saveSkills(): void
    {
        $userId = $this->getUserId();

        $offers = $this->input('offers', []);
        $needs = $this->input('needs', []);

        // Sanitize: ensure all IDs are integers
        if (is_array($offers)) {
            $offers = array_map('intval', $offers);
            $offers = array_filter($offers, fn($id) => $id > 0);
        } else {
            $offers = [];
        }

        if (is_array($needs)) {
            $needs = array_map('intval', $needs);
            $needs = array_filter($needs, fn($id) => $id > 0);
        } else {
            $needs = [];
        }

        OnboardingService::saveSkills($userId, $offers, $needs);

        $this->respondWithData(['message' => 'Skills saved']);
    }

    /**
     * POST /api/v2/onboarding/complete
     *
     * Complete the onboarding process:
     * 1. Save final skill selections (offers/needs)
     * 2. Auto-create listings from skill selections
     * 3. Mark onboarding as complete
     *
     * Request Body:
     * {
     *   "offers": [1, 3],
     *   "needs": [5, 7]
     * }
     *
     * Response: 200 OK
     * {
     *   "data": {
     *     "message": "Onboarding complete!",
     *     "listings_created": 4,
     *     "listing_ids": [101, 102, 103, 104]
     *   }
     * }
     */
    public function complete(): void
    {
        $userId = $this->getUserId();

        $offers = $this->input('offers', []);
        $needs = $this->input('needs', []);

        // Sanitize: ensure all IDs are integers
        if (is_array($offers)) {
            $offers = array_map('intval', $offers);
            $offers = array_filter($offers, fn($id) => $id > 0);
        } else {
            $offers = [];
        }

        if (is_array($needs)) {
            $needs = array_map('intval', $needs);
            $needs = array_filter($needs, fn($id) => $id > 0);
        } else {
            $needs = [];
        }

        // Save final skills
        OnboardingService::saveSkills($userId, $offers, $needs);

        // Auto-create listings from selections
        $listingIds = OnboardingService::autoCreateListings($userId, $offers, $needs);

        // Mark onboarding complete
        OnboardingService::completeOnboarding($userId);

        $this->respondWithData([
            'message' => 'Onboarding complete!',
            'listings_created' => count($listingIds),
            'listing_ids' => $listingIds,
        ]);
    }
}
