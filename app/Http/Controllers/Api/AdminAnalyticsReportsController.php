<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\SocialValueService;
use App\Services\HoursReportService;
use App\Services\InactiveMemberService;
use App\Services\ReportExportService;
use Illuminate\Http\JsonResponse;
use App\Core\TenantContext;
use App\Services\MemberReportService;
use App\Services\ContentModerationService;

/**
 * AdminAnalyticsReportsController -- Admin analytics and reporting endpoints.
 *
 * Converted from legacy delegation to direct service calls.
 */
class AdminAnalyticsReportsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SocialValueService $socialValueService,
        private readonly HoursReportService $hoursReportService,
        private readonly InactiveMemberService $inactiveMemberService,
        private readonly ReportExportService $reportExportService,
        private readonly MemberReportService $memberReportService,
        private readonly ContentModerationService $contentModerationService,
    ) {}

    // ============================================
    // A1: SOCIAL VALUE / SROI
    // ============================================

    /** GET /api/v2/admin/analytics/social-value */
    public function socialValue(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $dateRange = $this->getDateRange();

        $report = $this->socialValueService->calculateSROI($tenantId, $dateRange);

        return $this->respondWithData($report);
    }

    /** PUT /api/v2/admin/analytics/social-value/config */
    public function updateSocialValueConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $config = [
            'hour_value_currency' => $this->input('hour_value_currency', 'GBP'),
            'hour_value_amount' => (float) $this->input('hour_value_amount', 15.00),
            'social_multiplier' => (float) $this->input('social_multiplier', 3.5),
            'reporting_period' => $this->input('reporting_period', 'annually'),
        ];

        if ($config['hour_value_amount'] <= 0 || $config['hour_value_amount'] > 10000) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.hour_value_range'), 'hour_value_amount', 400);
        }

        if ($config['social_multiplier'] <= 0 || $config['social_multiplier'] > 100) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.social_multiplier_range'), 'social_multiplier', 400);
        }

        if (!in_array($config['reporting_period'], ['monthly', 'quarterly', 'annually'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_reporting_period'), 'reporting_period', 400);
        }

        $success = $this->socialValueService->saveConfig($tenantId, $config);

        if ($success) {
            return $this->respondWithData(['message' => __('api.social_value_config_updated'), 'config' => $config]);
        }

        return $this->respondWithError('SERVER_ERROR', __('api.failed_to_save_config'), null, 500);
    }

    // ============================================
    // A2: MEMBER REPORTS
    // ============================================

    /** GET /api/v2/admin/analytics/member-reports */
    public function memberReports(): JsonResponse
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
            'active' => $this->memberReportService->getActiveMembers($tenantId, $period, $limit, $offset),
            'registrations' => $this->memberReportService->getNewRegistrations($tenantId, $this->query('group_by', 'monthly'), $months),
            'retention' => $this->memberReportService->getMemberRetention($tenantId, $months),
            'engagement' => $this->memberReportService->getEngagementMetrics($tenantId, $period),
            'top_contributors' => ['contributors' => $this->memberReportService->getTopContributors($tenantId, $period, $limit)],
            'least_active' => $this->memberReportService->getLeastActiveMembers($tenantId, $period, $limit, $offset),
            default => null,
        };

        if ($data === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.unknown_report_type', ['type' => $type]), 'type', 400);
        }

        return $this->respondWithData($data);
    }

    // ============================================
    // A3: HOURS REPORTS
    // ============================================

    /** GET /api/v2/admin/analytics/hours-reports */
    public function hoursReports(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $groupBy = $this->query('group_by', 'category');
        $dateRange = $this->getDateRange();
        $sortBy = $this->query('sort_by', 'total');
        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $offset = max(0, ($this->queryInt('page', 1) - 1) * $limit);

        $data = match ($groupBy) {
            'category' => ['categories' => $this->hoursReportService->getHoursByCategory($tenantId, $dateRange)],
            'member' => ['members' => $this->hoursReportService->getHoursByMember($tenantId, $dateRange, $sortBy, $limit, $offset)],
            'period' => ['periods' => $this->hoursReportService->getHoursByPeriod($tenantId, $dateRange)],
            'summary' => $this->hoursReportService->getHoursSummary($tenantId, $dateRange),
            default => null,
        };

        if ($data === null) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.unknown_group_by', ['value' => $groupBy]), 'group_by', 400);
        }

        return $this->respondWithData($data);
    }

    // ============================================
    // A4: INACTIVE MEMBERS
    // ============================================

    /** GET /api/v2/admin/analytics/inactive-members */
    public function inactiveMembers(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $days = $this->queryInt('days', 90, 1, 730);
        $flagType = $this->query('flag_type');
        $limit = min(200, max(1, $this->queryInt('limit', 50)));
        $page = max(1, $this->queryInt('page', 1));
        $offset = ($page - 1) * $limit;

        $result = $this->inactiveMemberService->getInactiveMembers($tenantId, $days, $flagType, $limit, $offset);
        $stats = $this->inactiveMemberService->getInactivityStats($tenantId);

        return $this->respondWithData([
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

    /** POST /api/v2/admin/analytics/detect-inactive */
    public function detectInactive(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $thresholdDays = $this->inputInt('threshold_days', 90, 1, 730);

        $result = $this->inactiveMemberService->detectInactive($tenantId, $thresholdDays);

        return $this->respondWithData($result);
    }

    /** POST /api/v2/admin/analytics/mark-inactive-notified */
    public function markInactiveNotified(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userIds = $this->input('user_ids', []);

        if (empty($userIds) || !is_array($userIds)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_ids_required'), 'user_ids', 400);
        }

        $userIds = array_map('intval', $userIds);

        $updated = $this->inactiveMemberService->markNotified($tenantId, $userIds);

        return $this->respondWithData([
            'updated' => $updated,
            'message' => "{$updated} member(s) marked as notified",
        ]);
    }

    // ============================================
    // A5: CSV EXPORT
    // ============================================

    /** GET /api/v2/admin/analytics/export/{type} */
    public function exportReport(string $type)
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $format = $this->query('format', 'csv');

        if (!in_array($format, ['csv', 'pdf'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.supported_formats_csv_pdf'), 'format', 400);
        }

        $supportedTypes = $this->reportExportService->getSupportedTypes();
        if (!isset($supportedTypes[$type])) {
            $validTypes = implode(', ', array_keys($supportedTypes));
            return $this->respondWithError('VALIDATION_ERROR', __('api.unknown_export_type', ['type' => $type, 'valid' => $validTypes]), 'type', 400);
        }

        $filters = [
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'status' => $this->query('status'),
            'days' => $this->queryInt('days', 90),
        ];

        if ($format === 'pdf') {
            $result = $this->reportExportService->exportPdf($type, $tenantId, $filters);
            if (!$result['success']) {
                return $this->respondWithError('NO_DATA', $result['message'] ?? __('api.no_data_for_export'), null, 404);
            }
            return response($result['pdf'], 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Content-Length' => strlen($result['pdf']),
            ]);
        }

        $result = $this->reportExportService->export($type, $tenantId, $filters);

        if (!$result['success']) {
            return $this->respondWithError('NO_DATA', $result['message'] ?? __('api.no_data_for_export'), null, 404);
        }

        return response($result['csv'], 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Length' => strlen($result['csv']),
        ]);
    }

    /** GET /api/v2/admin/analytics/export-types */
    public function exportTypes(): JsonResponse
    {
        $this->requireAdmin();

        $types = $this->reportExportService->getSupportedTypes();

        $formatted = [];
        foreach ($types as $key => $label) {
            $formatted[] = ['type' => $key, 'label' => $label];
        }

        return $this->respondWithData($formatted);
    }

    // ============================================
    // A7: CONTENT MODERATION
    // ============================================

    /** GET /api/v2/admin/analytics/moderation-queue */
    public function moderationQueue(): JsonResponse
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

        $result = $this->contentModerationService->getQueue($tenantId, $filters, $limit, $offset);

        return $this->respondWithPaginatedCollection($result['items'], $result['total'], $page, $limit);
    }

    /** POST /api/v2/admin/analytics/moderation/{id}/review */
    public function moderationReview(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $decision = $this->input('decision');
        $rejectionReason = $this->input('rejection_reason');

        if (!$decision) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.decision_required'), 'decision', 400);
        }

        $result = $this->contentModerationService->review($id, $tenantId, $adminId, $decision, $rejectionReason);

        if ($result['success']) {
            return $this->respondWithData($result);
        }

        return $this->respondWithError('REVIEW_FAILED', $result['message'], null, 400);
    }

    /** GET /api/v2/admin/analytics/moderation-stats */
    public function moderationStats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $stats = $this->contentModerationService->getStats($tenantId);

        return $this->respondWithData($stats);
    }

    /** GET /api/v2/admin/analytics/moderation-settings */
    public function moderationSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = $this->contentModerationService->getModerationSettings($tenantId);

        return $this->respondWithData($settings);
    }

    /** PUT /api/v2/admin/analytics/moderation-settings */
    public function updateModerationSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = $this->getAllInput();

        $success = $this->contentModerationService->updateSettings($tenantId, $settings);

        if ($success) {
            $updatedSettings = $this->contentModerationService->getModerationSettings($tenantId);
            return $this->respondWithData([
                'message' => __('api.moderation_settings_updated'),
                'settings' => $updatedSettings,
            ]);
        }

        return $this->respondWithError('SERVER_ERROR', __('api.failed_update_moderation'), null, 500);
    }

    // ============================================
    // HELPERS
    // ============================================

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
