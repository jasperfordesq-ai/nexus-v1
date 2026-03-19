<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupRecommendationEngine;
use Illuminate\Http\JsonResponse;

/**
 * GroupRecommendController -- Group recommendation engine.
 *
 * Native implementation using legacy GroupRecommendationEngine static methods.
 *
 * Endpoints:
 *   GET  /api/v2/groups/recommendations         index()
 *   POST /api/v2/groups/recommendations/track    track()
 *   GET  /api/v2/groups/recommendations/metrics  metrics()
 *   GET  /api/v2/groups/similar/{groupId}        similar()
 */
class GroupRecommendController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupRecommendationEngine $groupRecommendationEngine,
    ) {}

    /**
     * GET /api/v2/groups/recommendations
     *
     * Get personalized group recommendations for the authenticated user.
     *
     * Query Parameters:
     * - limit: int (default 10, max 50)
     * - type_id: int (optional, filter by group type)
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $limit = $this->queryInt('limit', 10, 1, 50);

        $options = [];
        $typeId = $this->queryInt('type_id');
        if ($typeId !== null) {
            $options['type_id'] = $typeId;
        }

        $recommendations = $this->groupRecommendationEngine->getRecommendations($userId, $limit, $options);

        return $this->respondWithData($recommendations, [
            'count' => count($recommendations),
            'limit' => $limit,
        ]);
    }

    /**
     * POST /api/v2/groups/recommendations/track
     *
     * Track user interaction with a group recommendation.
     *
     * Body: { "group_id": int, "action": "viewed"|"clicked"|"joined"|"dismissed" }
     */
    public function track(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('recommendation_track', 100, 60);

        $groupId = $this->inputInt('group_id');
        $action = $this->input('action');

        if (!$groupId) {
            return $this->respondWithError('VALIDATION_ERROR', 'group_id is required', 'group_id', 400);
        }

        if (!$action) {
            return $this->respondWithError('VALIDATION_ERROR', 'action is required', 'action', 400);
        }

        $validActions = ['viewed', 'clicked', 'joined', 'dismissed'];
        if (!in_array($action, $validActions)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid action. Valid actions: ' . implode(', ', $validActions),
                'action',
                400
            );
        }

        $this->groupRecommendationEngine->trackInteraction($userId, $groupId, $action);

        return $this->respondWithData([
            'tracked' => true,
            'group_id' => $groupId,
            'action' => $action,
        ]);
    }

    /**
     * GET /api/v2/groups/recommendations/metrics
     *
     * Get recommendation performance metrics (admin only).
     *
     * Query Parameters:
     * - days: int (default 30, max 365)
     */
    public function metrics(): JsonResponse
    {
        $this->requireAdmin();

        $days = $this->queryInt('days', 30, 1, 365);

        $metrics = $this->groupRecommendationEngine->getPerformanceMetrics($days);

        return $this->respondWithData($metrics, [
            'period_days' => $days,
        ]);
    }

    /**
     * GET /api/v2/groups/similar/{groupId}
     *
     * Get groups similar to a specific group.
     *
     * Query Parameters:
     * - limit: int (default 5, max 20)
     */
    public function similar(int $groupId): JsonResponse
    {
        $userId = $this->requireAuth();

        $limit = $this->queryInt('limit', 5, 1, 20);

        $options = ['exclude_ids' => [$groupId]];

        $recommendations = $this->groupRecommendationEngine->getRecommendations($userId, $limit, $options);

        return $this->respondWithData($recommendations, [
            'source_group_id' => $groupId,
            'count' => count($recommendations),
            'limit' => $limit,
        ]);
    }
}
