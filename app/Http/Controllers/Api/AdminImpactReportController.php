<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ImpactReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminImpactReportController -- Admin impact report configuration.
 *
 * Provides SROI calculations, community health metrics, impact timelines,
 * and configurable report parameters for the admin panel.
 * All endpoints require admin authentication.
 */
class AdminImpactReportController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ImpactReportingService $impactReportingService,
    ) {}

    /**
     * GET /api/v2/admin/impact-report
     *
     * Returns full impact report including SROI, community health metrics,
     * and monthly timeline data.
     *
     * Query params: months (int, 1-60, default 12)
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $months = $this->queryInt('months', 12, 1, 60);

        $config = $this->impactReportingService->getReportConfig();

        return $this->respondWithData([
            'sroi' => $this->impactReportingService->calculateSROI([
                'months' => $months,
                'hourly_value' => $config['hourly_value'],
                'social_multiplier' => $config['social_multiplier'],
            ]),
            'health' => $this->impactReportingService->getCommunityHealthMetrics(),
            'timeline' => $this->impactReportingService->getImpactTimeline($months),
            'config' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/impact-report/config
     *
     * Update the SROI configuration (hourly value and social multiplier).
     *
     * Body params:
     *   hourly_value (float, 0-1000)
     *   social_multiplier (float, 0-100)
     */
    public function updateConfig(): JsonResponse
    {
        $this->requireAdmin();

        $hourlyValue = (float) $this->input('hourly_value', 15);
        $socialMultiplier = (float) $this->input('social_multiplier', 3.5);

        if ($hourlyValue <= 0 || $hourlyValue > 1000) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.hour_value_range'),
                'hourly_value',
                400
            );
        }

        if ($socialMultiplier <= 0 || $socialMultiplier > 100) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.social_multiplier_range'),
                'social_multiplier',
                400
            );
        }

        $tenantId = $this->getTenantId();
        $tenant = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $config = json_decode($tenant->configuration ?? '{}', true) ?: [];

        if (!isset($config['settings'])) {
            $config['settings'] = [];
        }
        $config['settings']['impact_hourly_value'] = $hourlyValue;
        $config['settings']['impact_social_multiplier'] = $socialMultiplier;

        DB::update(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($config), $tenantId]
        );

        return $this->respondWithData(['message' => 'Configuration updated']);
    }
}
