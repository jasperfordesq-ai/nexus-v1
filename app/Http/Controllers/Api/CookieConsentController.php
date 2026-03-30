<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\CookieConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CookieConsentController — Cookie consent preferences management.
 *
 * All endpoints migrated to native DB facade — no legacy delegation.
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

    /**
     * GET /api/cookie-consent/inventory
     *
     * Get cookie inventory for banner display.
     * Response: { "success": true, "cookies": {...}, "counts": {...}, "settings": {...} }
     */
    public function inventory(): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();

            // Get all active cookies grouped by category
            $cookieRows = DB::table('cookie_inventory')
                ->where(function ($q) use ($tenantId) {
                    $q->whereNull('tenant_id')
                      ->orWhere('tenant_id', $tenantId);
                })
                ->where('is_active', 1)
                ->orderBy('category')
                ->orderBy('cookie_name')
                ->get();

            $grouped = [
                'essential'  => [],
                'functional' => [],
                'analytics'  => [],
                'marketing'  => [],
            ];

            foreach ($cookieRows as $cookie) {
                $cat = $cookie->category;
                if (isset($grouped[$cat])) {
                    $grouped[$cat][] = (array) $cookie;
                }
            }

            // Get cookie counts per category
            $countRows = DB::table('cookie_inventory')
                ->where(function ($q) use ($tenantId) {
                    $q->whereNull('tenant_id')
                      ->orWhere('tenant_id', $tenantId);
                })
                ->where('is_active', 1)
                ->select('category', DB::raw('COUNT(*) as count'))
                ->groupBy('category')
                ->get();

            $counts = [
                'essential'  => 0,
                'functional' => 0,
                'analytics'  => 0,
                'marketing'  => 0,
            ];

            foreach ($countRows as $row) {
                if (isset($counts[$row->category])) {
                    $counts[$row->category] = (int) $row->count;
                }
            }

            // Get tenant settings
            $settings = DB::table('tenant_cookie_settings')
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $settings) {
                $tenantSettings = [
                    'analytics_enabled'     => false,
                    'marketing_enabled'     => false,
                    'consent_validity_days' => 365,
                    'auto_block_scripts'    => true,
                    'strict_mode'           => true,
                    'show_reject_all'       => true,
                ];
            } else {
                $tenantSettings = (array) $settings;
            }

            return $this->respondWithData([
                'cookies'  => $grouped,
                'counts'   => $counts,
                'settings' => $tenantSettings,
            ]);
        } catch (\Exception $e) {
            report($e);
            return $this->respondWithError('SERVER_ERROR', __('api.failed_retrieve_cookie_inventory'), null, 500);
        }
    }

    /**
     * PUT /api/cookie-consent/{id}
     *
     * Update existing consent record.
     * Body: { functional, analytics, marketing }
     * Response: { "success": true, "message": "...", "consent": {...} }
     */
    public function update($id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = $this->getOptionalUserId();
        $input = $this->getAllInput();

        $categories = [
            'functional' => $input['functional'] ?? false,
            'analytics'  => $input['analytics'] ?? false,
            'marketing'  => $input['marketing'] ?? false,
        ];

        try {
            // Build query with tenant scope AND user ownership check
            // Users can only update their own consent records (prevents IDOR)
            $query = DB::table('cookie_consents')
                ->where('id', $id)
                ->where('tenant_id', $tenantId);

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('ip_address', request()->ip());
            }

            $updated = $query->update(array_merge($categories, ['updated_at' => now()]));

            if ($updated) {
                $userId = $this->getOptionalUserId();
                if ($userId) {
                    try {
                        DB::table('activity_logs')->insert([
                            'user_id'    => $userId,
                            'tenant_id'  => $tenantId,
                            'action'     => 'cookie_consent_updated',
                            'details'    => 'Cookie consent preferences updated',
                            'created_at' => now(),
                        ]);
                    } catch (\Exception $e) { /* activity_logs may not exist */ }
                }

                return $this->respondWithData([
                    'message' => 'Consent preferences updated successfully',
                    'consent' => array_merge(['id' => (int) $id], $categories),
                ]);
            }

            return $this->respondWithError('NOT_FOUND', __('api.consent_record_not_found'), null, 404);
        } catch (\Exception $e) {
            report($e);
            return $this->respondWithError('SERVER_ERROR', __('api.failed_update_consent'), null, 500);
        }
    }

    /**
     * DELETE /api/cookie-consent/{id}
     *
     * Withdraw consent.
     * Response: { "success": true, "message": "Consent withdrawn successfully" }
     */
    public function withdraw($id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = $this->getOptionalUserId();

        try {
            // Build query with tenant scope AND user ownership check
            // Users can only withdraw their own consent records (prevents IDOR)
            $query = DB::table('cookie_consents')
                ->where('id', $id)
                ->where('tenant_id', $tenantId);

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('ip_address', request()->ip());
            }

            $updated = $query->update(['withdrawal_date' => now()]);

            if ($updated) {
                $userId = $this->getOptionalUserId();
                if ($userId) {
                    try {
                        DB::table('activity_logs')->insert([
                            'user_id'    => $userId,
                            'tenant_id'  => $tenantId,
                            'action'     => 'cookie_consent_withdrawn',
                            'details'    => 'Cookie consent withdrawn',
                            'created_at' => now(),
                        ]);
                    } catch (\Exception $e) { /* activity_logs may not exist */ }
                }

                return $this->respondWithData([
                    'message' => 'Consent withdrawn successfully',
                ]);
            }

            return $this->respondWithError('NOT_FOUND', __('api.consent_record_not_found'), null, 404);
        } catch (\Exception $e) {
            report($e);
            return $this->respondWithError('SERVER_ERROR', __('api.failed_withdraw_consent'), null, 500);
        }
    }
}
