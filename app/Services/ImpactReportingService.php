<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ImpactReportingService — Laravel DI wrapper for legacy \Nexus\Services\ImpactReportingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ImpactReportingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ImpactReportingService::calculateSROI().
     */
    public function calculateSROI(array $config = []): array
    {
        return \Nexus\Services\ImpactReportingService::calculateSROI($config);
    }

    /**
     * Delegates to legacy ImpactReportingService::getCommunityHealthMetrics().
     */
    public function getCommunityHealthMetrics(): array
    {
        return \Nexus\Services\ImpactReportingService::getCommunityHealthMetrics();
    }

    /**
     * Delegates to legacy ImpactReportingService::getImpactTimeline().
     */
    public function getImpactTimeline(int $months = 12): array
    {
        return \Nexus\Services\ImpactReportingService::getImpactTimeline($months);
    }

    /**
     * Delegates to legacy ImpactReportingService::getReportConfig().
     */
    public function getReportConfig(): array
    {
        return \Nexus\Services\ImpactReportingService::getReportConfig();
    }
}
