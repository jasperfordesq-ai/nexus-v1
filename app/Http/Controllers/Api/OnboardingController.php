<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\OnboardingConfigService;
use App\Services\OnboardingService;
use App\Services\SafeguardingPreferenceService;

/**
 * OnboardingController -- New member onboarding flow.
 *
 * Supports admin-configurable steps, safeguarding preferences, and
 * listing creation modes. All methods are tenant-scoped.
 */
class OnboardingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /** GET onboarding/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        $complete = $this->onboardingService->isOnboardingComplete($userId);
        $interests = $this->onboardingService->getUserInterests($userId);

        $user = \App\Models\User::findById($userId);
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

        $interests = $this->input('interests', []);
        $offers = $this->input('offers', []);
        $needs = $this->input('needs', []);

        // Sanitize: ensure all IDs are positive integers with no duplicates
        $interests = is_array($interests) ? array_values(array_unique(array_filter(array_map('intval', $interests), fn ($id) => $id > 0))) : [];
        $offers    = is_array($offers)    ? array_values(array_unique(array_filter(array_map('intval', $offers),    fn ($id) => $id > 0))) : [];
        $needs     = is_array($needs)     ? array_values(array_unique(array_filter(array_map('intval', $needs),     fn ($id) => $id > 0))) : [];

        // Validate category IDs belong to current tenant
        $tenantId = TenantContext::getId();
        if (!empty($interests) || !empty($offers) || !empty($needs)) {
            $allCatIds = array_unique(array_merge($interests, $offers, $needs));
            $validCatIds = DB::table('categories')
                ->where('tenant_id', $tenantId)
                ->whereIn('id', $allCatIds)
                ->pluck('id')
                ->all();
            $validSet = array_flip($validCatIds);
            $interests = array_values(array_filter($interests, fn ($id) => isset($validSet[$id])));
            $offers = array_values(array_filter($offers, fn ($id) => isset($validSet[$id])));
            $needs = array_values(array_filter($needs, fn ($id) => isset($validSet[$id])));
        }

        // All-or-nothing: wrap in transaction with row-level lock to prevent double-completion
        DB::beginTransaction();
        try {
            // Lock user row to prevent concurrent completion (TOCTOU race condition).
            // Identity is from the auth token — do NOT scope by tenant_id, because a
            // super-admin acting on a different tenant has their user record in their
            // home tenant. The auth token is the source of truth for identity.
            $user = DB::selectOne(
                "SELECT id, avatar_url, bio, onboarding_completed FROM users WHERE id = ? FOR UPDATE",
                [$userId]
            );

            if (!empty($user->onboarding_completed)) {
                DB::rollback();
                return $this->respondWithData([
                    'message' => __('api_controllers_2.onboarding.already_completed'),
                    'listings_created' => 0,
                    'listing_ids' => [],
                ]);
            }

            // Verify profile photo and bio are present
            if (empty($user->avatar_url)) {
                DB::rollback();
                return $this->respondWithError(
                    'VALIDATION_REQUIRED_FIELD',
                    __('api.profile_photo_required'),
                    'avatar_url',
                    422
                );
            }
            if (empty(trim($user->bio ?? ''))) {
                DB::rollback();
                return $this->respondWithError(
                    'VALIDATION_REQUIRED_FIELD',
                    __('api.bio_required'),
                    'bio',
                    422
                );
            }

            // Safeguarding step: options are opt-in. A user choosing none of the
            // checkboxes is a valid response ("none apply to me"). Marking the
            // step "required" means it's shown in the wizard, not that the user
            // must self-classify as vulnerable. Per-option `is_required` flags
            // are still enforced client-side at the SafeguardingStep component.

            $this->onboardingService->saveInterests($userId, $interests);
            $this->onboardingService->saveSkills($userId, $offers, $needs);
            $listingIds = $this->onboardingService->autoCreateListings($userId, $offers, $needs);
            $this->onboardingService->completeOnboarding($userId);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollback();
            \Illuminate\Support\Facades\Log::error('Onboarding complete() failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return $this->respondWithError('SERVER_ERROR', __('api.server_error'), null, 500);
        }

        return $this->respondWithData([
            'message'          => __('api.onboarding.complete'),
            'listings_created' => count($listingIds),
            'listing_ids'      => $listingIds,
        ]);
    }

    /** GET onboarding/config — tenant onboarding configuration for the frontend */
    public function getConfig(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = TenantContext::getId();

        $config = OnboardingConfigService::getConfig($tenantId);
        $steps = OnboardingConfigService::getActiveSteps($tenantId);

        return $this->respondWithData([
            'config' => $config,
            'steps' => $steps,
        ]);
    }

    /** GET onboarding/safeguarding-options — active safeguarding options for member */
    public function safeguardingOptions(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = TenantContext::getId();

        $options = SafeguardingPreferenceService::getOptionsForTenant($tenantId);

        // Strip internal fields — members see label, description, type, required, select_options only
        $memberOptions = array_map(fn ($opt) => [
            'id' => $opt['id'],
            'option_key' => $opt['option_key'],
            'option_type' => $opt['option_type'],
            'label' => $opt['label'],
            'description' => $opt['description'],
            'help_url' => $opt['help_url'],
            'is_required' => $opt['is_required'],
            'select_options' => $opt['select_options'],
        ], $options);

        return $this->respondWithData($memberOptions);
    }

    /** POST onboarding/safeguarding — save member safeguarding preferences */
    public function saveSafeguarding(): JsonResponse
    {
        $userId = $this->requireAuth();

        $preferences = $this->input('preferences', []);
        if (!is_array($preferences) || empty($preferences)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.preferences_array_required'),
                'preferences',
                422
            );
        }

        // Validate each preference has required fields
        foreach ($preferences as $idx => $pref) {
            if (empty($pref['option_id'])) {
                return $this->respondWithError(
                    'VALIDATION_ERROR',
                    __('api.preference_option_id_required', ['index' => $idx]),
                    'preferences',
                    422
                );
            }
        }

        $ipAddress = request()?->ip();

        SafeguardingPreferenceService::saveUserPreferences($userId, $preferences, $ipAddress);

        return $this->respondWithData([
            'message' => __('api_controllers_2.onboarding.safeguarding_saved'),
            'preferences_count' => count($preferences),
        ]);
    }
}
