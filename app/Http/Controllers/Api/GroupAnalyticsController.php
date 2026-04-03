<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupAnalyticsService;

/**
 * GroupAnalyticsController — Dashboard, growth, engagement, retention, and export analytics for groups.
 */
class GroupAnalyticsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/analytics/dashboard
     */
    public function dashboard(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $days = $this->queryInt('days', 30);
        $result = GroupAnalyticsService::getDashboard($id, $days);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/growth
     */
    public function growth(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $days = $this->queryInt('days', 30);
        $result = GroupAnalyticsService::getMemberGrowth($id, $days);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/engagement
     */
    public function engagement(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $days = $this->queryInt('days', 30);
        $result = GroupAnalyticsService::getEngagementMetrics($id, $days);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/contributors
     */
    public function contributors(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $days = $this->queryInt('days', 30);
        $limit = $this->queryInt('limit', 10);
        $result = GroupAnalyticsService::getTopContributors($id, $days, $limit);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/retention
     */
    public function retention(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $months = $this->queryInt('months', 6);
        $result = GroupAnalyticsService::getRetentionMetrics($id, $months);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/comparative
     */
    public function comparative(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = GroupAnalyticsService::getComparativeAnalytics($id);

        return $this->successResponse($result);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/export-members
     */
    public function exportMembers(int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $members = GroupAnalyticsService::exportMembers($id);

        return response()->streamDownload(function () use ($members) {
            $output = fopen('php://output', 'w');

            if (!empty($members) && is_array($members[0] ?? null)) {
                fputcsv($output, array_keys($members[0]));
            } elseif (!empty($members) && is_object($members[0] ?? null)) {
                fputcsv($output, array_keys((array) $members[0]));
            }

            foreach ($members as $row) {
                fputcsv($output, (array) $row);
            }

            fclose($output);
        }, 'group-' . $id . '-members.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * GET /api/v2/groups/{id}/analytics/export-activity
     */
    public function exportActivity(int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $days = $this->queryInt('days', 30);
        $activity = GroupAnalyticsService::exportActivity($id, $days);

        return response()->streamDownload(function () use ($activity) {
            $output = fopen('php://output', 'w');

            if (!empty($activity) && is_array($activity[0] ?? null)) {
                fputcsv($output, array_keys($activity[0]));
            } elseif (!empty($activity) && is_object($activity[0] ?? null)) {
                fputcsv($output, array_keys((array) $activity[0]));
            }

            foreach ($activity as $row) {
                fputcsv($output, (array) $row);
            }

            fclose($output);
        }, 'group-' . $id . '-activity.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
