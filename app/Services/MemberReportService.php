<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * MemberReportService — Laravel DI wrapper for legacy \Nexus\Services\MemberReportService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class MemberReportService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy MemberReportService::getActiveMembers().
     */
    public function getActiveMembers(int $tenantId, int $days = 30, int $limit = 50, int $offset = 0): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getActiveMembers($tenantId, $days, $limit, $offset);
    }

    /**
     * Delegates to legacy MemberReportService::getNewRegistrations().
     */
    public function getNewRegistrations(int $tenantId, string $period = 'monthly', int $months = 12): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getNewRegistrations($tenantId, $period, $months);
    }

    /**
     * Delegates to legacy MemberReportService::getMemberRetention().
     */
    public function getMemberRetention(int $tenantId, int $months = 12): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getMemberRetention($tenantId, $months);
    }

    /**
     * Delegates to legacy MemberReportService::getEngagementMetrics().
     */
    public function getEngagementMetrics(int $tenantId, int $days = 30): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getEngagementMetrics($tenantId, $days);
    }

    /**
     * Delegates to legacy MemberReportService::getTopContributors().
     */
    public function getTopContributors(int $tenantId, int $days = 30, int $limit = 20): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getTopContributors($tenantId, $days, $limit);
    }

    /**
     * Delegates to legacy MemberReportService::getLeastActiveMembers().
     */
    public function getLeastActiveMembers(int $tenantId, int $days = 30, int $limit = 50, int $offset = 0): array
    {
        if (!class_exists('\Nexus\Services\MemberReportService')) { return []; }
        return \Nexus\Services\MemberReportService::getLeastActiveMembers($tenantId, $days, $limit, $offset);
    }
}
