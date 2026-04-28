<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\TrustTierService;
use Illuminate\Http\JsonResponse;

/**
 * AG67 — Trust Tier API Controller
 *
 * Exposes endpoints for reading a member's trust tier and admin management
 * of per-tenant tier criteria + bulk recomputation.
 */
class TrustTierController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TrustTierService $service,
    ) {
    }

    /**
     * GET /api/v2/caring-community/my-trust-tier
     *
     * Returns the authenticated member's current tier, its label, and the
     * label of the next tier (or null if they are already at the top).
     */
    public function myTier(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!$this->service->isAvailable()) {
            return $this->respondWithError('FEATURE_UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        $tier      = $this->service->getTier($userId, $tenantId);
        $label     = $this->service->getTierLabel($tier);
        $nextTier  = $tier < TrustTierService::TIER_COORDINATOR ? $tier + 1 : null;
        $nextLabel = $nextTier !== null ? $this->service->getTierLabel($nextTier) : null;

        return $this->respondWithData([
            'tier'       => $tier,
            'label'      => $label,
            'next_tier'  => $nextLabel,
        ]);
    }

    /**
     * GET /api/v2/admin/caring-community/trust-tier/config
     *
     * Returns the current per-tenant trust tier criteria configuration.
     * Admin only.
     */
    public function getTierConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        return $this->respondWithData([
            'criteria' => $this->service->getConfig($tenantId),
        ]);
    }

    /**
     * PUT /api/v2/admin/caring-community/trust-tier/config
     *
     * Update per-tenant trust tier criteria thresholds.
     * Admin only.
     *
     * Body: { criteria: { member: {...}, trusted: {...}, verified: {...}, coordinator: {...} } }
     */
    public function updateTierConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $input    = $this->getAllInput();
        $criteria = $input['criteria'] ?? null;

        if (!is_array($criteria) || empty($criteria)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'criteria', 422);
        }

        $allowedTiers = ['member', 'trusted', 'verified', 'coordinator'];
        $validated    = [];

        foreach ($allowedTiers as $tierName) {
            if (!isset($criteria[$tierName]) || !is_array($criteria[$tierName])) {
                continue;
            }

            $tc = $criteria[$tierName];

            $validated[$tierName] = [
                'hours_logged'      => max(0, (int) ($tc['hours_logged']      ?? TrustTierService::DEFAULT_CRITERIA[$tierName]['hours_logged'])),
                'reviews_received'  => max(0, (int) ($tc['reviews_received']  ?? TrustTierService::DEFAULT_CRITERIA[$tierName]['reviews_received'])),
                'identity_verified' => (bool) ($tc['identity_verified'] ?? TrustTierService::DEFAULT_CRITERIA[$tierName]['identity_verified']),
            ];
        }

        if (empty($validated)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.field_required'), 'criteria', 422);
        }

        $this->service->updateConfig($tenantId, $validated);

        return $this->respondWithData([
            'criteria' => $this->service->getConfig($tenantId),
        ]);
    }

    /**
     * POST /api/v2/admin/caring-community/trust-tier/recompute
     *
     * Trigger a bulk recomputation of trust tiers for all active users in the
     * current tenant.  Admin only.
     */
    public function recomputeTiers(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (!$this->service->isAvailable()) {
            return $this->respondWithError('FEATURE_UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        $updated = $this->service->recomputeAll($tenantId);

        return $this->respondWithData(['updated' => $updated]);
    }
}
