<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ImpactReportingService;

/**
 * AdminImpactReportApiController - V2 API for Impact Reporting module
 *
 * Provides SROI calculations, community health metrics, impact timelines,
 * and configurable report parameters for the admin panel.
 *
 * Endpoints:
 * - GET /api/v2/admin/impact-report          - Full impact report data
 * - PUT /api/v2/admin/impact-report/config   - Update SROI configuration
 */
class AdminImpactReportApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/impact-report
     *
     * Returns full impact report including SROI, community health metrics,
     * and monthly timeline data.
     *
     * Query params:
     *   months (int, 1-60, default 12) - Period for SROI and timeline calculations
     */
    public function index(): void
    {
        $this->requireAdmin();

        $months = $this->queryInt('months', 12, 1, 60);

        $config = ImpactReportingService::getReportConfig();

        $this->respondWithData([
            'sroi' => ImpactReportingService::calculateSROI([
                'months' => $months,
                'hourly_value' => $config['hourly_value'],
                'social_multiplier' => $config['social_multiplier'],
            ]),
            'health' => ImpactReportingService::getCommunityHealthMetrics(),
            'timeline' => ImpactReportingService::getImpactTimeline($months),
            'config' => $config,
        ]);
    }

    /**
     * PUT /api/v2/admin/impact-report/config
     *
     * Update the SROI configuration (hourly value and social multiplier)
     * for the current tenant. Values are stored in tenants.settings JSON.
     *
     * Body params:
     *   hourly_value (float, 0-1000) - GBP value per hour of service
     *   social_multiplier (float, 0-100) - SROI multiplier factor
     */
    public function updateConfig(): void
    {
        $this->requireAdmin();

        $hourlyValue = (float) ($this->input('hourly_value', 15));
        $socialMultiplier = (float) ($this->input('social_multiplier', 3.5));

        if ($hourlyValue <= 0 || $hourlyValue > 1000) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Hourly value must be between 0 and 1000',
                'hourly_value',
                400
            );
        }

        if ($socialMultiplier <= 0 || $socialMultiplier > 100) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Social multiplier must be between 0 and 100',
                'social_multiplier',
                400
            );
        }

        $tenantId = TenantContext::getId();
        $stmt = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
        $tenant = $stmt->fetch();
        $config = json_decode($tenant['configuration'] ?? '{}', true) ?: [];

        if (!isset($config['settings'])) {
            $config['settings'] = [];
        }
        $config['settings']['impact_hourly_value'] = $hourlyValue;
        $config['settings']['impact_social_multiplier'] = $socialMultiplier;

        Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($config), $tenantId]
        );

        $this->respondWithData(['message' => 'Configuration updated']);
    }
}
