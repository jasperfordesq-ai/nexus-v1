<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\MemberActivityService;

/**
 * MemberActivityApiController - API for member activity dashboard
 *
 * Endpoints:
 * - GET /api/v2/users/me/activity/dashboard    - Full dashboard data
 * - GET /api/v2/users/me/activity/timeline     - Activity timeline
 * - GET /api/v2/users/me/activity/hours        - Hours summary
 * - GET /api/v2/users/me/activity/monthly      - Monthly hours chart data
 * - GET /api/v2/users/{id}/activity/dashboard   - Public activity dashboard
 */
class MemberActivityApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/activity/dashboard
     */
    public function getDashboard(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('activity_dashboard', 10, 60);

        $data = MemberActivityService::getDashboardData($userId);
        $this->respondWithData($data);
    }

    /**
     * GET /api/v2/users/me/activity/timeline
     */
    public function getTimeline(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('activity_timeline', 20, 60);

        $limit = $this->queryInt('limit', 30, 1, 100);
        $timeline = MemberActivityService::getRecentTimeline($userId, null, $limit);

        $this->respondWithData($timeline);
    }

    /**
     * GET /api/v2/users/me/activity/hours
     */
    public function getHours(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('activity_hours', 20, 60);

        $summary = MemberActivityService::getHoursSummary($userId);
        $this->respondWithData($summary);
    }

    /**
     * GET /api/v2/users/me/activity/monthly
     */
    public function getMonthlyHours(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('activity_monthly', 10, 60);

        $monthly = MemberActivityService::getMonthlyHours($userId);
        $this->respondWithData($monthly);
    }

    /**
     * GET /api/v2/users/{id}/activity/dashboard
     */
    public function getPublicDashboard(int $id): void
    {
        $this->rateLimit('activity_public_dashboard', 20, 60);

        $data = MemberActivityService::getDashboardData($id);
        $this->respondWithData($data);
    }
}
