<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedSidebarService;
use Illuminate\Http\JsonResponse;

/**
 * FeedSidebarController — Feed sidebar widgets (stats, suggestions).
 */
class FeedSidebarController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FeedSidebarService $sidebarService,
    ) {}

    /**
     * GET /api/v2/feed/sidebar/stats
     *
     * Get community statistics for the sidebar widget.
     */
    public function communityStats(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $stats = $this->sidebarService->getCommunityStats($tenantId);

        return $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/feed/sidebar/suggested-members
     *
     * Get suggested members to connect with.
     * Query params: limit (default 5).
     */
    public function suggestedMembers(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $limit = $this->queryInt('limit', 5, 1, 20);

        $members = $this->sidebarService->getSuggestedMembers($userId, $tenantId, $limit);

        return $this->respondWithData($members);
    }

    /**
     * GET /api/v2/feed/sidebar
     *
     * Get all sidebar data in a single request (stats + suggestions + trending).
     */
    public function sidebar(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        $data = $this->sidebarService->getFullSidebar($userId, $tenantId);

        return $this->respondWithData($data);
    }
}
