<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupRecommendationService;
use Illuminate\Http\JsonResponse;

/**
 * GroupRecommendationsController — AI-powered group recommendations.
 */
class GroupRecommendationsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupRecommendationService $recommendationService,
    ) {}

    /**
     * GET /api/v2/groups/recommendations
     *
     * Get personalized group recommendations for the authenticated user.
     * Query params: limit (default 5).
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $limit = $this->queryInt('limit', 5, 1, 20);

        $recommendations = $this->recommendationService->getForUser($userId, $tenantId, $limit);

        return $this->respondWithData($recommendations);
    }

    /**
     * POST /api/v2/groups/recommendations/track
     *
     * Track a recommendation interaction (clicked, dismissed, joined).
     * Body: group_id (required), action (required: clicked|dismissed|joined).
     */
    public function track(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $groupId = (int) $this->requireInput('group_id');
        $action = $this->requireInput('action');

        $this->recommendationService->trackInteraction($userId, $groupId, $action, $tenantId);

        return $this->respondWithData(['tracked' => true]);
    }

    /**
     * GET /api/v2/groups/{id}/similar
     *
     * Get groups similar to the given group.
     * Query params: limit (default 5).
     */
    public function similar(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $limit = $this->queryInt('limit', 5, 1, 20);

        $groups = $this->recommendationService->getSimilar($id, $tenantId, $limit);

        return $this->respondWithData($groups);
    }
}
