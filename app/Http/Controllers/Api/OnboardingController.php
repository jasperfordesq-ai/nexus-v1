<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Services\OnboardingService;

/**
 * OnboardingController -- New member onboarding flow.
 *
 * All methods now call legacy static services directly (no ob_start delegation).
 */
class OnboardingController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET onboarding/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        $complete = OnboardingService::isOnboardingComplete($userId);
        $interests = OnboardingService::getUserInterests($userId);

        $user = \Nexus\Models\User::findById($userId);
        $hasAvatar = !empty($user['avatar_url'] ?? '');
        $hasBio = !empty(trim($user['bio'] ?? ''));

        return $this->respondWithData([
            'onboarding_completed' => $complete,
            'has_avatar'           => $hasAvatar,
            'has_bio'              => $hasBio,
            'interests'            => $interests,
        ]);
    }

    /** GET onboarding/categories */
    public function categories(): JsonResponse
    {
        $this->requireAuth();

        $tenantId = TenantContext::getId();
        $categories = DB::select(
            "SELECT id, name, slug, color FROM categories WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        );

        return $this->respondWithData(array_map(fn ($c) => (array) $c, $categories));
    }

    /** POST onboarding/complete */
    public function complete(): JsonResponse
    {
        $userId = $this->requireAuth();

        // Verify profile photo and bio are present
        $user = \Nexus\Models\User::findById($userId);
        if (empty($user['avatar_url'])) {
            return $this->respondWithError(
                'VALIDATION_REQUIRED_FIELD',
                'Profile photo is required to complete onboarding',
                'avatar_url',
                422
            );
        }
        if (empty(trim($user['bio'] ?? ''))) {
            return $this->respondWithError(
                'VALIDATION_REQUIRED_FIELD',
                'Bio is required to complete onboarding',
                'bio',
                422
            );
        }

        $interests = $this->input('interests', []);
        $offers = $this->input('offers', []);
        $needs = $this->input('needs', []);

        // Sanitize: ensure all IDs are integers
        $interests = is_array($interests) ? array_filter(array_map('intval', $interests), fn ($id) => $id > 0) : [];
        $offers = is_array($offers) ? array_filter(array_map('intval', $offers), fn ($id) => $id > 0) : [];
        $needs = is_array($needs) ? array_filter(array_map('intval', $needs), fn ($id) => $id > 0) : [];

        // All-or-nothing: wrap in transaction
        \Nexus\Core\Database::beginTransaction();
        try {
            OnboardingService::saveInterests($userId, $interests);
            OnboardingService::saveSkills($userId, $offers, $needs);
            $listingIds = OnboardingService::autoCreateListings($userId, $offers, $needs);
            OnboardingService::completeOnboarding($userId);

            \Nexus\Core\Database::commit();
        } catch (\Throwable $e) {
            \Nexus\Core\Database::rollback();
            throw $e;
        }

        return $this->respondWithData([
            'message'          => 'Onboarding complete!',
            'listings_created' => count($listingIds),
            'listing_ids'      => $listingIds,
        ]);
    }
}
