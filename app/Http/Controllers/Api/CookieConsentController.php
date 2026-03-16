<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\CookieConsentService;
use Illuminate\Http\JsonResponse;

/**
 * CookieConsentController — Cookie consent preferences management.
 */
class CookieConsentController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CookieConsentService $consentService,
    ) {}

    /**
     * GET /api/v2/cookie-consent
     *
     * Get current cookie consent settings for the user/session.
     */
    public function show(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        $consent = $this->consentService->getConsent($userId, $tenantId, request()->ip());

        return $this->respondWithData($consent);
    }

    /**
     * POST /api/v2/cookie-consent
     *
     * Store cookie consent preferences.
     * Body: analytics (bool), marketing (bool), functional (bool).
     */
    public function store(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        $preferences = [
            'analytics' => $this->inputBool('analytics', false),
            'marketing' => $this->inputBool('marketing', false),
            'functional' => $this->inputBool('functional', true),
        ];

        $result = $this->consentService->storeConsent($userId, $tenantId, request()->ip(), $preferences);

        return $this->respondWithData($result);
    }

    /**
     * GET /api/v2/cookie-consent/check
     *
     * Quick check whether consent has been given.
     */
    public function check(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        $hasConsent = $this->consentService->hasConsent($userId, $tenantId, request()->ip());

        return $this->respondWithData(['has_consent' => $hasConsent]);
    }
}
