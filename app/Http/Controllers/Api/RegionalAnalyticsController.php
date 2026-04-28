<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\RegionalAnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * RegionalAnalyticsController — AG59 Regional Analytics Product
 *
 * Admin-only endpoints serving geographic, demographic, engagement, volunteer,
 * and help-request analytics for municipalities and SME partners.
 *
 * All endpoints require admin authentication (requireAdmin()).
 * No feature gate is applied — available to all admin users.
 */
class RegionalAnalyticsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Allowed period values — rejects anything not in this list */
    private const VALID_PERIODS = ['last_30d', 'last_90d', 'last_12m', 'all_time'];

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function resolvePeriod(string $default = 'last_30d'): string
    {
        $period = (string) $this->query('period', $default);
        return in_array($period, self::VALID_PERIODS, true) ? $period : $default;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Endpoints
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /v2/admin/regional-analytics/overview
     * Hero headline metrics (active members, vol hours, help requests, top category).
     */
    public function overview(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $data = RegionalAnalyticsService::getOverviewSummary($tenantId);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/heatmap?period=
     * Geographic activity density bucketed to ~0.01° grid cells.
     */
    public function heatmap(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_90d');

        $data = RegionalAnalyticsService::getMemberHeatmap($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/demand-supply?period=
     * Per-category request vs offer counts, ratio, and trend.
     */
    public function demandSupply(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_30d');

        $data = RegionalAnalyticsService::getDemandSupplyRatio($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/demographics
     * Age groups, language distribution, monthly member growth curve.
     */
    public function demographics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $data = RegionalAnalyticsService::getDemographics($tenantId);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/engagement-trends?period=
     * Monthly active members, vol hours, new listings, new events, help requests.
     */
    public function engagementTrends(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_12m');

        $data = RegionalAnalyticsService::getEngagementTrends($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/volunteer-breakdown?period=
     * Top orgs by hours, avg hours/volunteer, total hours, reciprocity ratio.
     */
    public function volunteerBreakdown(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_90d');

        $data = RegionalAnalyticsService::getVolunteerBreakdown($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/help-requests?period=
     * Help request breakdown by category with resolution rates and trend.
     */
    public function helpRequests(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_30d');

        $data = RegionalAnalyticsService::getHelpRequestAnalysis($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * GET /v2/admin/regional-analytics/export?period=
     * Full JSON report export (all sections assembled into one payload).
     */
    public function exportReport(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $period   = $this->resolvePeriod('last_30d');

        $data = RegionalAnalyticsService::exportReportJson($tenantId, $period);

        return $this->respondWithData($data);
    }

    /**
     * POST /v2/admin/regional-analytics/invalidate-cache
     * Forces cache invalidation so next request recomputes fresh data.
     */
    public function invalidateCache(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        RegionalAnalyticsService::invalidateCache($tenantId);

        return $this->respondWithData(['invalidated' => true]);
    }
}
