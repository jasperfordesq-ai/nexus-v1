<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MemberActivityService;
use Illuminate\Http\JsonResponse;

/**
 * ActivityController — Member activity dashboard and timeline.
 */
class ActivityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MemberActivityService $activityService,
    ) {}

    /**
     * GET /api/v2/activity/dashboard
     *
     * Get the activity dashboard for the authenticated user.
     * Returns summary stats: total hours, exchanges, recent activity count.
     */
    public function dashboard(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $data = $this->activityService->getDashboard($userId, $tenantId);

        return $this->respondWithData($data);
    }

    /**
     * GET /api/v2/activity/timeline
     *
     * Get the activity timeline for the authenticated user.
     * Query params: cursor, per_page, type (exchange|listing|event|group).
     */
    public function timeline(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $cursor = $this->query('cursor');
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $type = $this->query('type');

        $result = $this->activityService->getTimeline($userId, $tenantId, $cursor, $perPage, $type);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $perPage,
            $result['has_more'] ?? false
        );
    }

    /**
     * GET /api/v2/activity/hours
     *
     * Get hour-based activity breakdown (given, received, balance).
     * Query params: period (week|month|quarter|year|all).
     */
    public function hours(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $period = $this->query('period', 'month');

        $data = $this->activityService->getHoursBreakdown($userId, $tenantId, $period);

        return $this->respondWithData($data);
    }
}
