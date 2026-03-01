<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\SocialValueService;
use Nexus\Services\MemberReportService;
use Nexus\Services\HoursReportService;
use Nexus\Services\InactiveMemberService;
use Nexus\Services\ReportExportService;
use Nexus\Services\ContentModerationService;

/**
 * AdminAnalyticsReportsApiController - V2 API for Admin Analytics & Reporting
 *
 * Consolidates all admin reporting endpoints:
 *
 * Social Value (A1):
 * - GET    /api/v2/admin/reports/social-value           - SROI report
 * - PUT    /api/v2/admin/reports/social-value/config    - Update SROI config
 *
 * Member Reports (A2):
 * - GET    /api/v2/admin/reports/members?type=active&period=30d    - Member reports
 *
 * Hours Reports (A3):
 * - GET    /api/v2/admin/reports/hours?group_by=category           - Hours reports
 *
 * Inactive Members (A4):
 * - GET    /api/v2/admin/members/inactive?days=90                  - Inactive members
 * - POST   /api/v2/admin/members/inactive/detect                   - Run detection
 * - POST   /api/v2/admin/members/inactive/notify                   - Mark notified
 *
 * CSV Export (A5):
 * - GET    /api/v2/admin/reports/{type}/export?format=csv          - CSV export
 *
 * Content Moderation (A7):
 * - GET    /api/v2/admin/moderation/queue                          - Moderation queue
 * - POST   /api/v2/admin/moderation/{id}/review                    - Review item
 * - GET    /api/v2/admin/moderation/stats                          - Moderation stats
 * - GET    /api/v2/admin/moderation/settings                       - Moderation settings
 * - PUT    /api/v2/admin/moderation/settings                       - Update settings
 */
class AdminAnalyticsReportsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ============================================
    // A1: SOCIAL VALUE / SROI
    // ============================================

    /**
     * GET /api/v2/admin/reports/social-value
     *
     * Query params: date_from, date_to
     */
    public function socialValue(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $dateRange = $this->getDateRange();

        $report = SocialValueService::calculateSROI($tenantId, $dateRange);

        $this->respondWithData($report);
    }

    /**
     * PUT /api/v2/admin/reports/social-value/config
     *
     * Body: { hour_value_currency, hour_value_amount, social_multiplier, reporting_period }
     */
    public function updateSocialValueConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $config = [
            'hour_value_currency' => $this->input('hour_value_currency', 'GBP'),
            'hour_value_amount' => (float) $this->input('hour_value_amount', 15.00),
            'social_multiplier' => (float) $this->input('social_multiplier', 3.5),
            'reporting_period' => $this->input('reporting_period', 'annually'),
        ];

        // Validation
        if ($config['hour_value_amount'] <= 0 || $config['hour_value_amount'] > 10000) {
            $this->respondWithError('VALIDATION_ERROR', 'Hour value must be between 0 and 10,000', 'hour_value_amount', 400);
            return;
        }

        if ($config['social_multiplier'] <= 0 || $config['social_multiplier'] > 100) {
            $this->respondWithError('VALIDATION_ERROR', 'Social multiplier must be between 0 and 100', 'social_multiplier', 400);
            return;
        }

        if (!in_array($config['reporting_period'], ['monthly', 'quarterly', 'annually'], true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid reporting period', 'reporting_period', 400);
            return;
        }

        $success = SocialValueService::saveConfig($tenantId, $config);

        if ($success) {
            $this->respondWithData(['message' => 'Social value configuration updated', 'config' => $config]);
        } else {
            $this->respondWithError('SERVER_ERROR', 'Failed to save configuration', null, 500);
        }
    }

    // ============================================
    // A2: MEMBER REPORTS
    // ============================================

    /**
     * GET /api/v2/admin/reports/members
     *
     * Query params: type (active|registrations|retention|engagement|top_contributors|least_active),
     *               period (number of days), months (for retention/registrations),
     *               group_by (daily|weekly|monthly), page, limit
     */
    public function memberReports(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $type = $this->query('type', 'active');
        $period = $this->queryInt('period', 30, 1, 365);
        $months = $this->queryInt('months', 12, 1, 60);
        $page = max(1, $this->queryInt('page', 1));
        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $offset = ($page - 1) * $limit;

        $data = match ($type) {
            'active' => MemberReportService::getActiveMembers($tenantId, $period, $limit, $offset),
            'registrations' => MemberReportService::getNewRegistrations($tenantId, $this->query('group_by', 'monthly'), $months),
            'retention' => MemberReportService::getMemberRetention($tenantId, $months),
            'engagement' => MemberReportService::getEngagementMetrics($tenantId, $period),
            'top_contributors' => ['contributors' => MemberReportService::getTopContributors($tenantId, $period, $limit)],
            'least_active' => MemberReportService::getLeastActiveMembers($tenantId, $period, $limit, $offset),
            default => null,
        };

        if ($data === null) {
            $this->respondWithError('VALIDATION_ERROR', "Unknown report type: {$type}. Valid types: active, registrations, retention, engagement, top_contributors, least_active", 'type', 400);
            return;
        }

        $this->respondWithData($data);
    }

    // ============================================
    // A3: HOURS REPORTS
    // ============================================

    /**
     * GET /api/v2/admin/reports/hours
     *
     * Query params: group_by (category|member|period|summary), date_from, date_to,
     *               sort_by (total|given|received), page, limit
     */
    public function hoursReports(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $groupBy = $this->query('group_by', 'category');
        $dateRange = $this->getDateRange();
        $sortBy = $this->query('sort_by', 'total');
        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $offset = max(0, ($this->queryInt('page', 1) - 1) * $limit);

        $data = match ($groupBy) {
            'category' => ['categories' => HoursReportService::getHoursByCategory($tenantId, $dateRange)],
            'member' => ['members' => HoursReportService::getHoursByMember($tenantId, $dateRange, $sortBy, $limit, $offset)],
            'period' => ['periods' => HoursReportService::getHoursByPeriod($tenantId, $dateRange)],
            'summary' => HoursReportService::getHoursSummary($tenantId, $dateRange),
            default => null,
        };

        if ($data === null) {
            $this->respondWithError('VALIDATION_ERROR', "Unknown group_by: {$groupBy}. Valid values: category, member, period, summary", 'group_by', 400);
            return;
        }

        $this->respondWithData($data);
    }

    // ============================================
    // A4: INACTIVE MEMBERS
    // ============================================

    /**
     * GET /api/v2/admin/members/inactive
     *
     * Query params: days (threshold), flag_type, page, limit
     */
    public function inactiveMembers(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $days = $this->queryInt('days', 90, 1, 730);
        $flagType = $this->query('flag_type');
        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $page = max(1, $this->queryInt('page', 1));
        $offset = ($page - 1) * $limit;

        $result = InactiveMemberService::getInactiveMembers($tenantId, $days, $flagType, $limit, $offset);
        $stats = InactiveMemberService::getInactivityStats($tenantId);

        $this->respondWithData([
            'members' => $result['members'],
            'stats' => $stats,
        ], [
            'page' => $page,
            'per_page' => $limit,
            'total' => $result['total'],
            'total_pages' => $result['total'] > 0 ? (int) ceil($result['total'] / $limit) : 0,
            'has_more' => ($page * $limit) < $result['total'],
            'threshold_days' => $days,
        ]);
    }

    /**
     * POST /api/v2/admin/members/inactive/detect
     *
     * Body: { threshold_days: int }
     * Runs the inactive member detection scan.
     */
    public function detectInactive(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $thresholdDays = $this->inputInt('threshold_days', 90, 1, 730);

        $result = InactiveMemberService::detectInactive($tenantId, $thresholdDays);

        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/admin/members/inactive/notify
     *
     * Body: { user_ids: [int] }
     * Marks specified inactive members as notified.
     */
    public function markInactiveNotified(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userIds = $this->input('user_ids', []);

        if (empty($userIds) || !is_array($userIds)) {
            $this->respondWithError('VALIDATION_ERROR', 'user_ids must be a non-empty array', 'user_ids', 400);
            return;
        }

        // Sanitize to ints
        $userIds = array_map('intval', $userIds);

        $updated = InactiveMemberService::markNotified($tenantId, $userIds);

        $this->respondWithData([
            'updated' => $updated,
            'message' => "{$updated} member(s) marked as notified",
        ]);
    }

    // ============================================
    // A5: CSV EXPORT
    // ============================================

    /**
     * GET /api/v2/admin/reports/{type}/export
     *
     * Query params: format (csv), date_from, date_to, days (for inactive_members)
     */
    public function exportReport(string $type): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $format = $this->query('format', 'csv');

        if ($format !== 'csv') {
            $this->respondWithError('VALIDATION_ERROR', 'Only CSV format is currently supported', 'format', 400);
            return;
        }

        // Validate report type
        $supportedTypes = ReportExportService::getSupportedTypes();
        if (!isset($supportedTypes[$type])) {
            $validTypes = implode(', ', array_keys($supportedTypes));
            $this->respondWithError('VALIDATION_ERROR', "Unknown report type: {$type}. Valid types: {$validTypes}", 'type', 400);
            return;
        }

        $filters = [
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'status' => $this->query('status'),
            'days' => $this->queryInt('days', 90),
        ];

        $result = ReportExportService::export($type, $tenantId, $filters);

        if (!$result['success']) {
            $this->respondWithError('NO_DATA', $result['message'] ?? 'No data found for export', null, 404);
            return;
        }

        // Send as CSV download
        ReportExportService::sendCSVDownload($result['csv'], $result['filename']);
    }

    /**
     * GET /api/v2/admin/reports/export-types
     *
     * Returns list of supported export types.
     */
    public function exportTypes(): void
    {
        $this->requireAdmin();

        $types = ReportExportService::getSupportedTypes();

        $formatted = [];
        foreach ($types as $key => $label) {
            $formatted[] = ['type' => $key, 'label' => $label];
        }

        $this->respondWithData($formatted);
    }

    // ============================================
    // A7: CONTENT MODERATION
    // ============================================

    /**
     * GET /api/v2/admin/moderation/queue
     *
     * Query params: status, content_type, search, page, limit
     */
    public function moderationQueue(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $filters = [
            'status' => $this->query('status'),
            'content_type' => $this->query('content_type'),
            'search' => $this->query('search'),
        ];

        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $page = max(1, $this->queryInt('page', 1));
        $offset = ($page - 1) * $limit;

        $result = ContentModerationService::getQueue($tenantId, $filters, $limit, $offset);

        $this->respondWithPaginatedCollection($result['items'], $result['total'], $page, $limit);
    }

    /**
     * POST /api/v2/admin/moderation/{id}/review
     *
     * Body: { decision: 'approved'|'rejected', rejection_reason: string (required if rejected) }
     */
    public function moderationReview(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $decision = $this->input('decision');
        $rejectionReason = $this->input('rejection_reason');

        if (!$decision) {
            $this->respondWithError('VALIDATION_ERROR', 'Decision is required (approved or rejected)', 'decision', 400);
            return;
        }

        $result = ContentModerationService::review($id, $tenantId, $adminId, $decision, $rejectionReason);

        if ($result['success']) {
            $this->respondWithData($result);
        } else {
            $this->respondWithError('REVIEW_FAILED', $result['message'], null, 400);
        }
    }

    /**
     * GET /api/v2/admin/moderation/stats
     */
    public function moderationStats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stats = ContentModerationService::getStats($tenantId);

        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/moderation/settings
     */
    public function moderationSettings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = ContentModerationService::getModerationSettings($tenantId);

        $this->respondWithData($settings);
    }

    /**
     * PUT /api/v2/admin/moderation/settings
     *
     * Body: { enabled, require_post, require_listing, require_event, require_comment, auto_filter }
     */
    public function updateModerationSettings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = $this->getAllInput();

        $success = ContentModerationService::updateSettings($tenantId, $settings);

        if ($success) {
            $updatedSettings = ContentModerationService::getModerationSettings($tenantId);
            $this->respondWithData([
                'message' => 'Moderation settings updated',
                'settings' => $updatedSettings,
            ]);
        } else {
            $this->respondWithError('SERVER_ERROR', 'Failed to update moderation settings', null, 500);
        }
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * Extract date range from query params
     */
    private function getDateRange(): array
    {
        $range = [];

        $from = $this->query('date_from');
        $to = $this->query('date_to');

        if ($from) {
            $range['from'] = $from;
        }
        if ($to) {
            $range['to'] = $to;
        }

        return $range;
    }
}
