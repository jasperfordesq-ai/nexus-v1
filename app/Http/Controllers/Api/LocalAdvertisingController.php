<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\LocalAdvertisingService;
use Illuminate\Http\JsonResponse;

/**
 * LocalAdvertisingController — AG56 Local Advertising Platform.
 *
 * Endpoints:
 *
 * Member/advertiser (auth required):
 *   GET  /v2/me/ad-campaigns                          myAdCampaigns()
 *   POST /v2/me/ad-campaigns                          createCampaign()
 *   GET  /v2/me/ad-campaigns/{id}/stats               getMyCampaignStats()
 *   POST /v2/me/ad-campaigns/{campaignId}/creatives   addCreative()
 *
 * Admin (admin role required):
 *   GET  /v2/admin/ad-campaigns                       adminListCampaigns()
 *   GET  /v2/admin/ad-campaigns/stats                 adminOverviewStats()
 *   GET  /v2/admin/ad-campaigns/{id}                  adminGetCampaign()
 *   POST /v2/admin/ad-campaigns/{id}/approve          adminApproveCampaign()
 *   POST /v2/admin/ad-campaigns/{id}/reject           adminRejectCampaign()
 *   POST /v2/admin/ad-campaigns/{id}/pause            adminPauseCampaign()
 *
 * Feed/beacon (optional auth, rate-limited at route level):
 *   GET  /v2/ads/active                               getActiveAds()
 *   POST /v2/ads/impression                           recordImpression()
 *   POST /v2/ads/impression/{impressionId}/click      recordClick()
 */
class LocalAdvertisingController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ──────────────────────────────────────────────────────────────────────────
    // Feature gate helper
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return true when the local_advertising feature is enabled for this tenant,
     * or when the caller is an admin (admins can always access).
     */
    private function featureEnabled(?int $userId): bool
    {
        if (TenantContext::hasFeature('local_advertising')) {
            return true;
        }

        // Admins bypass the gate so they can configure advertising before enabling it.
        if ($userId !== null) {
            try {
                $user = \Illuminate\Support\Facades\Auth::user();
                if ($user) {
                    $role = $user->role ?? 'member';
                    if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)) {
                        return true;
                    }
                    if (($user->is_super_admin ?? false) || ($user->is_tenant_super_admin ?? false)) {
                        return true;
                    }
                }
            } catch (\Throwable) {
                // Guard not resolved — deny
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Member / advertiser endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /v2/me/ad-campaigns
     * Returns campaigns owned by the authenticated user.
     */
    public function myAdCampaigns(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = $this->getTenantId();

        if (!$this->featureEnabled($userId)) {
            return $this->respondForbidden('Local advertising is not enabled for this community.', 'FEATURE_DISABLED');
        }

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $status = $this->query('status');
        $filters = ['created_by' => $userId];
        if ($status) {
            $filters['status'] = $status;
        }

        $all = LocalAdvertisingService::listCampaigns($tenantId, $filters);

        // Filter to only this user's own campaigns
        $own = array_values(array_filter($all, fn ($c) => (int) ($c['created_by'] ?? 0) === $userId));

        return $this->respondWithData($own);
    }

    /**
     * POST /v2/me/ad-campaigns
     * Any authenticated user can submit a campaign for review.
     */
    public function createCampaign(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = $this->getTenantId();

        if (!$this->featureEnabled($userId)) {
            return $this->respondForbidden('Local advertising is not enabled for this community.', 'FEATURE_DISABLED');
        }

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'name']), 'name');
        }

        $audienceFilters = $this->input('audience_filters');
        if (is_string($audienceFilters) && $audienceFilters !== '') {
            $decoded = json_decode($audienceFilters, true);
            $audienceFilters = is_array($decoded) ? $decoded : null;
        } elseif (!is_array($audienceFilters)) {
            $audienceFilters = null;
        }

        $data = [
            'name'             => $name,
            'advertiser_type'  => $this->input('advertiser_type', 'sme'),
            'budget_cents'     => $this->inputInt('budget_cents', 0, 0),
            'start_date'       => $this->input('start_date'),
            'end_date'         => $this->input('end_date'),
            'audience_filters' => $audienceFilters,
            'placement'        => $this->input('placement', 'feed'),
        ];

        try {
            $campaign = LocalAdvertisingService::createCampaign($tenantId, $userId, $data);
            return $this->respondWithData($campaign, null, 201);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.service_unavailable'));
        }
    }

    /**
     * GET /v2/me/ad-campaigns/{id}/stats
     * Campaign owner can view their own campaign stats.
     */
    public function getMyCampaignStats(int $id): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = $this->getTenantId();

        if (!$this->featureEnabled($userId)) {
            return $this->respondForbidden('Local advertising is not enabled for this community.', 'FEATURE_DISABLED');
        }

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($id, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        // Ownership check — only the creator can see stats via this endpoint
        if ((int) ($campaign['created_by'] ?? 0) !== $userId) {
            return $this->respondForbidden();
        }

        $stats = LocalAdvertisingService::getCampaignStats($id, $tenantId);

        return $this->respondWithData($stats);
    }

    /**
     * POST /v2/me/ad-campaigns/{campaignId}/creatives
     * Campaign owner can add creatives to their pending or active campaigns.
     */
    public function addCreative(int $campaignId): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = $this->getTenantId();

        if (!$this->featureEnabled($userId)) {
            return $this->respondForbidden('Local advertising is not enabled for this community.', 'FEATURE_DISABLED');
        }

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($campaignId, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        if ((int) ($campaign['created_by'] ?? 0) !== $userId) {
            return $this->respondForbidden();
        }

        if (!in_array($campaign['status'] ?? '', ['pending_review', 'active'], true)) {
            return $this->respondWithError(
                'CAMPAIGN_NOT_EDITABLE',
                'Creatives can only be added to campaigns with pending_review or active status.',
                null,
                422
            );
        }

        $headline = trim((string) $this->input('headline', ''));
        $body     = trim((string) $this->input('body', ''));

        if ($headline === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'headline']), 'headline');
        }
        if ($body === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'body']), 'body');
        }

        try {
            $creative = LocalAdvertisingService::addCreative($campaignId, $tenantId, [
                'headline'        => $headline,
                'body'            => $body,
                'cta_text'        => $this->input('cta_text'),
                'image_url'       => $this->input('image_url'),
                'destination_url' => $this->input('destination_url'),
            ]);
            return $this->respondWithData($creative, null, 201);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.service_unavailable'));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Admin endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /v2/admin/ad-campaigns
     * Admin: list all campaigns with optional ?status= filter.
     */
    public function adminListCampaigns(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $filters = [];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('advertiser_type')) {
            $filters['advertiser_type'] = $this->query('advertiser_type');
        }

        $campaigns = LocalAdvertisingService::listCampaigns($tenantId, $filters);

        return $this->respondWithData($campaigns);
    }

    /**
     * GET /v2/admin/ad-campaigns/stats
     * Admin: tenant-level overview stats for the dashboard.
     */
    public function adminOverviewStats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $stats = LocalAdvertisingService::getOverviewStats($tenantId);

        return $this->respondWithData($stats);
    }

    /**
     * GET /v2/admin/ad-campaigns/{id}
     * Admin: single campaign with creatives and 30-day stats.
     */
    public function adminGetCampaign(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($id, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        $campaign['stats'] = LocalAdvertisingService::getCampaignStats($id, $tenantId);

        return $this->respondWithData($campaign);
    }

    /**
     * POST /v2/admin/ad-campaigns/{id}/approve
     * Admin: approve a pending campaign.
     */
    public function adminApproveCampaign(int $id): JsonResponse
    {
        $adminId  = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($id, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        try {
            $updated = LocalAdvertisingService::approveCampaign($id, $tenantId, $adminId);
            return $this->respondWithData($updated);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.service_unavailable'));
        }
    }

    /**
     * POST /v2/admin/ad-campaigns/{id}/reject
     * Admin: reject a campaign with a reason.
     * Body: { "reason": "..." }
     */
    public function adminRejectCampaign(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($id, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        $reason = trim((string) $this->input('reason', ''));
        if ($reason === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'reason']), 'reason');
        }

        try {
            LocalAdvertisingService::rejectCampaign($id, $tenantId, $reason);
            return $this->respondWithData(['id' => $id, 'status' => 'rejected']);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.service_unavailable'));
        }
    }

    /**
     * POST /v2/admin/ad-campaigns/{id}/pause
     * Admin: pause an active campaign.
     */
    public function adminPauseCampaign(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondServerError(__('api.service_unavailable'));
        }

        $campaign = LocalAdvertisingService::getCampaignById($id, $tenantId);

        if ($campaign === null) {
            return $this->respondNotFound();
        }

        try {
            LocalAdvertisingService::pauseCampaign($id, $tenantId);
            return $this->respondWithData(['id' => $id, 'status' => 'paused']);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.service_unavailable'));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Feed / beacon endpoints (optional auth, rate-limited at route level)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /v2/ads/active
     * Return active ads for the current tenant and placement.
     * Query: ?placement=feed&limit=3
     */
    public function getActiveAds(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId   = $this->getOptionalUserId();

        if (!$this->featureEnabled($userId)) {
            return $this->respondWithData([]);
        }

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondWithData([]);
        }

        $placement = $this->query('placement', 'feed');
        $limit     = $this->queryInt('limit', 3, 1, 10);

        $ads = LocalAdvertisingService::getActiveAds($tenantId, (string) $placement, $limit);

        return $this->respondWithData($ads);
    }

    /**
     * POST /v2/ads/impression
     * Record that an ad was displayed.
     * Body: { campaign_id, creative_id, placement }
     */
    public function recordImpression(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId   = $this->getOptionalUserId();

        if (!LocalAdvertisingService::isAvailable()) {
            return $this->respondWithData(['impression_id' => null]);
        }

        $campaignId = $this->inputInt('campaign_id');
        $creativeId = $this->inputInt('creative_id');
        $placement  = trim((string) $this->input('placement', 'feed'));

        if ($campaignId === null || $creativeId === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'campaign_id']));
        }

        try {
            $impressionId = LocalAdvertisingService::recordImpression(
                $campaignId,
                $creativeId,
                $tenantId,
                $placement,
                $userId
            );
            return $this->respondWithData(['impression_id' => $impressionId]);
        } catch (\Throwable $e) {
            // Silently fail — tracking should never break the user experience
            return $this->respondWithData(['impression_id' => null]);
        }
    }

    /**
     * POST /v2/ads/impression/{impressionId}/click
     * Record a click on an impression.
     * Body: { campaign_id }
     */
    public function recordClick(int $impressionId): JsonResponse
    {
        $tenantId   = $this->getTenantId();
        $userId     = $this->getOptionalUserId();
        $campaignId = $this->inputInt('campaign_id');

        if (!LocalAdvertisingService::isAvailable() || $campaignId === null) {
            return $this->respondWithData(['ok' => true]);
        }

        try {
            LocalAdvertisingService::recordClick($impressionId, $campaignId, $tenantId, $userId);
        } catch (\Throwable $e) {
            // Silently fail — tracking should never break the user experience
        }

        return $this->respondWithData(['ok' => true]);
    }
}
