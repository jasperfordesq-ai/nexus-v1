<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * AdminAnalyticsReportsController -- Admin analytics and reporting endpoints.
 *
 * Delegates to legacy controller during migration.
 */
class AdminAnalyticsReportsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    /** GET /api/v2/admin/analytics/social-value */
    public function socialValue(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'socialValue');
    }

    /** PUT /api/v2/admin/analytics/social-value/config */
    public function updateSocialValueConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'updateSocialValueConfig');
    }

    /** GET /api/v2/admin/analytics/member-reports */
    public function memberReports(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'memberReports');
    }

    /** GET /api/v2/admin/analytics/hours-reports */
    public function hoursReports(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'hoursReports');
    }

    /** GET /api/v2/admin/analytics/inactive-members */
    public function inactiveMembers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'inactiveMembers');
    }

    /** POST /api/v2/admin/analytics/detect-inactive */
    public function detectInactive(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'detectInactive');
    }

    /** POST /api/v2/admin/analytics/mark-inactive-notified */
    public function markInactiveNotified(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'markInactiveNotified');
    }

    /** GET /api/v2/admin/analytics/export/{type} */
    public function exportReport(string $type): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'exportReport', [$type]);
    }

    /** GET /api/v2/admin/analytics/export-types */
    public function exportTypes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'exportTypes');
    }

    /** GET /api/v2/admin/analytics/moderation-queue */
    public function moderationQueue(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'moderationQueue');
    }

    /** POST /api/v2/admin/analytics/moderation/{id}/review */
    public function moderationReview(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'moderationReview', [$id]);
    }

    /** GET /api/v2/admin/analytics/moderation-stats */
    public function moderationStats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'moderationStats');
    }

    /** GET /api/v2/admin/analytics/moderation-settings */
    public function moderationSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'moderationSettings');
    }

    /** PUT /api/v2/admin/analytics/moderation-settings */
    public function updateModerationSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminAnalyticsReportsApiController::class, 'updateModerationSettings');
    }
}
