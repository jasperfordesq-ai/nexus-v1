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
 * Handles the post-registration 5-step onboarding process:
 * 1. Welcome (no API call needed)
 * 2. Profile photo + bio
 * 3. Select interests (categories)
 * 4. Select skills (offers/needs)
 * 5. Confirm and auto-create listings
 *
 * Endpoints:
 * - GET  /api/v2/onboarding/status       - Get onboarding completion status
 * - GET  /api/v2/onboarding/categories   - Get available categories for selection
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

        // Include profile completeness for frontend enforcement
        $user = \Nexus\Models\User::findById($userId);
        $hasAvatar = !empty($user['avatar_url'] ?? '');
        $hasBio = !empty(trim($user['bio'] ?? ''));

        $this->respondWithData([
            'onboarding_completed' => $complete,
            'has_avatar' => $hasAvatar,
            'has_bio' => $hasBio,
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
     * POST /api/v2/onboarding/complete
     *
     * Complete the onboarding process:
     * 1. Save interest selections (optional)
     * 2. Save skill selections (offers/needs)
     * 3. Auto-create listings from skill selections
     * 4. Mark onboarding as complete
     *
     * Request Body:
     * {
     *   "interests": [1, 2],
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

        // Verify profile photo and bio are present (mandatory for onboarding completion)
        $user = \Nexus\Models\User::findById($userId);
        if (empty($user['avatar_url'])) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Profile photo is required to complete onboarding',
                'avatar_url',
                422
            );
            return;
        }
        if (empty(trim($user['bio'] ?? ''))) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Bio is required to complete onboarding',
                'bio',
                422
            );
            return;
        }

        $interests = $this->input('interests', []);
        $offers = $this->input('offers', []);
        $needs = $this->input('needs', []);

        // Sanitize: ensure all IDs are integers
        if (is_array($interests)) {
            $interests = array_map('intval', $interests);
            $interests = array_filter($interests, fn($id) => $id > 0);
        } else {
            $interests = [];
        }

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

        // Save interests (optional — may be empty)
        OnboardingService::saveInterests($userId, $interests);

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
