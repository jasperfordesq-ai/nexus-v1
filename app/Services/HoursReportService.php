<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * HoursReportService — Laravel DI wrapper for legacy \Nexus\Services\HoursReportService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class HoursReportService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy HoursReportService::getHoursByCategory().
     */
    public function getHoursByCategory(int $tenantId, array $dateRange = []): array
    {
        return \Nexus\Services\HoursReportService::getHoursByCategory($tenantId, $dateRange);
    }

    /**
     * Delegates to legacy HoursReportService::getHoursByMember().
     */
    public function getHoursByMember(int $tenantId, array $dateRange = [], string $sortBy = 'total', int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\HoursReportService::getHoursByMember($tenantId, $dateRange, $sortBy, $limit, $offset);
    }

    /**
     * Delegates to legacy HoursReportService::getHoursByPeriod().
     */
    public function getHoursByPeriod(int $tenantId, array $dateRange = []): array
    {
        return \Nexus\Services\HoursReportService::getHoursByPeriod($tenantId, $dateRange);
    }

    /**
     * Delegates to legacy HoursReportService::getHoursSummary().
     */
    public function getHoursSummary(int $tenantId, array $dateRange = []): array
    {
        return \Nexus\Services\HoursReportService::getHoursSummary($tenantId, $dateRange);
    }
}
