<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\MerchantOnboardingService;
use Illuminate\Http\JsonResponse;

/**
 * MerchantOnboardingController — Self-serve business onboarding wizard (AG48).
 *
 * Endpoints (v2, all auth-required):
 *   GET  /v2/merchant-onboarding/status   status()     — current wizard state
 *   POST /v2/merchant-onboarding/step-1   saveStep1()  — business identity
 *   POST /v2/merchant-onboarding/step-2   saveStep2()  — location & opening hours
 *   POST /v2/merchant-onboarding/step-3   saveStep3()  — profile & cover images
 *   POST /v2/merchant-onboarding/complete complete()   — finalise wizard + badge
 *
 * Feature gate: marketplace must be enabled for the tenant.
 *
 * Do NOT modify MarketplaceSellerController.php — this is a separate controller
 * dedicated to the onboarding wizard flow only.
 */
class MerchantOnboardingController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─────────────────────────────────────────────────────────────────────────
    //  Guards
    // ─────────────────────────────────────────────────────────────────────────

    private function ensureFeature(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('marketplace')) {
            return $this->respondForbidden(
                'The marketplace feature is not enabled for this community.',
                'FEATURE_DISABLED'
            );
        }
        return null;
    }

    private function ensureAuth(): ?JsonResponse
    {
        if (!auth()->check()) {
            return $this->respondUnauthorized();
        }
        return null;
    }

    private function tenantId(): int
    {
        return (int) TenantContext::getId();
    }

    private function userId(): int
    {
        return (int) auth()->id();
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /v2/merchant-onboarding/status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the current onboarding status for the authenticated user.
     */
    public function status(): JsonResponse
    {
        if ($err = $this->ensureAuth()) {
            return $err;
        }
        if ($err = $this->ensureFeature()) {
            return $err;
        }

        $status = MerchantOnboardingService::getOnboardingStatus(
            $this->tenantId(),
            $this->userId()
        );

        return $this->respondWithData($status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /v2/merchant-onboarding/step-1
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save Step 1 — Business identity.
     *
     * Body: {
     *   business_name: string (required for seller_type=business),
     *   display_name:  string,
     *   bio:           string,
     *   seller_type:   'private'|'business',
     *   business_registration?: string
     * }
     */
    public function saveStep1(): JsonResponse
    {
        if ($err = $this->ensureAuth()) {
            return $err;
        }
        if ($err = $this->ensureFeature()) {
            return $err;
        }

        $data = [
            'business_name'         => $this->input('business_name'),
            'display_name'          => $this->input('display_name'),
            'bio'                   => $this->input('bio'),
            'seller_type'           => $this->input('seller_type', 'business'),
            'business_registration' => $this->input('business_registration'),
        ];

        // Strip null-valued optional keys so we don't overwrite with null
        $data = array_filter($data, fn ($v) => $v !== null);

        $profile = MerchantOnboardingService::saveStep1(
            $this->tenantId(),
            $this->userId(),
            $data
        );

        return $this->respondWithData(['profile' => $profile]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /v2/merchant-onboarding/step-2
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save Step 2 — Location and opening hours.
     *
     * Body: {
     *   business_address: { street, city, postal_code, country },
     *   opening_hours?:   { mon?: {open, close}|null, tue?: ..., sun?: ... }
     * }
     */
    public function saveStep2(): JsonResponse
    {
        if ($err = $this->ensureAuth()) {
            return $err;
        }
        if ($err = $this->ensureFeature()) {
            return $err;
        }

        $data = [];

        $address = $this->input('business_address');
        if ($address !== null) {
            $data['business_address'] = $address;
        }

        $hours = $this->input('opening_hours');
        if ($hours !== null) {
            $data['opening_hours'] = $hours;
        }

        $profile = MerchantOnboardingService::saveStep2(
            $this->tenantId(),
            $this->userId(),
            $data
        );

        return $this->respondWithData(['profile' => $profile]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /v2/merchant-onboarding/step-3
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save Step 3 — Profile photo and optional cover image (already-uploaded URLs).
     *
     * Body: {
     *   avatar_url?:       string,
     *   cover_image_url?:  string
     * }
     */
    public function saveStep3(): JsonResponse
    {
        if ($err = $this->ensureAuth()) {
            return $err;
        }
        if ($err = $this->ensureFeature()) {
            return $err;
        }

        $avatarUrl     = $this->input('avatar_url');
        $coverImageUrl = $this->input('cover_image_url');

        if (empty($avatarUrl)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'avatar_url is required.',
                'avatar_url'
            );
        }

        $profile = MerchantOnboardingService::saveStep3(
            $this->tenantId(),
            $this->userId(),
            (string) $avatarUrl,
            $coverImageUrl ? (string) $coverImageUrl : null
        );

        return $this->respondWithData(['profile' => $profile]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  POST /v2/merchant-onboarding/complete
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Finalise the onboarding wizard.
     *
     * Sets onboarding_completed_at and grants the 'marktplatz_partner' badge.
     */
    public function complete(): JsonResponse
    {
        if ($err = $this->ensureAuth()) {
            return $err;
        }
        if ($err = $this->ensureFeature()) {
            return $err;
        }

        $result = MerchantOnboardingService::completeOnboarding(
            $this->tenantId(),
            $this->userId()
        );

        return $this->respondWithData($result);
    }
}
