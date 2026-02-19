<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\GroupRecommendationEngine;
use Nexus\Core\TenantContext;

/**
 * GroupRecommendationController
 *
 * API endpoints for group discovery and recommendations.
 * Supports both session-based and Bearer token authentication.
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 */
class GroupRecommendationController extends BaseApiController
{
    /**
     * GET /api/recommendations/groups
     *
     * Get personalized group recommendations for the authenticated user.
     *
     * Query Parameters:
     * - limit: int (default 10, max 50)
     * - type_id: int (optional, filter by group type)
     *
     * Response: 200 OK with array of recommended groups
     */
    public function index(): void
    {
        $userId = $this->getUserId();

        $limit = $this->queryInt('limit', 10, 1, 50);

        $options = [];
        $typeId = $this->queryInt('type_id');
        if ($typeId !== null) {
            $options['type_id'] = $typeId;
        }

        $recommendations = GroupRecommendationEngine::getRecommendations($userId, $limit, $options);

        $this->respondWithData($recommendations, [
            'count' => count($recommendations),
            'limit' => $limit
        ]);
    }

    /**
     * POST /api/recommendations/track
     *
     * Track user interaction with a group recommendation.
     * Used to improve recommendation quality over time.
     *
     * Request Body (JSON):
     * {
     *   "group_id": int (required),
     *   "action": "viewed" | "clicked" | "joined" | "dismissed" (required)
     * }
     *
     * Response: 200 OK on success
     */
    public function track(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('recommendation_track', 100, 60);

        $groupId = $this->inputInt('group_id');
        $action = $this->input('action');

        if (!$groupId) {
            $this->respondWithError('VALIDATION_ERROR', 'group_id is required', 'group_id', 400);
        }

        if (!$action) {
            $this->respondWithError('VALIDATION_ERROR', 'action is required', 'action', 400);
        }

        // Validate action
        $validActions = ['viewed', 'clicked', 'joined', 'dismissed'];
        if (!in_array($action, $validActions)) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid action. Valid actions: ' . implode(', ', $validActions),
                'action',
                400
            );
        }

        GroupRecommendationEngine::trackInteraction($userId, $groupId, $action);

        $this->respondWithData([
            'tracked' => true,
            'group_id' => $groupId,
            'action' => $action
        ]);
    }

    /**
     * GET /api/recommendations/metrics
     *
     * Get recommendation performance metrics (admin only).
     *
     * Query Parameters:
     * - days: int (default 30, max 365)
     *
     * Response: 200 OK with metrics data
     */
    public function metrics(): void
    {
        $this->requireAdmin();

        $days = $this->queryInt('days', 30, 1, 365);

        $metrics = GroupRecommendationEngine::getPerformanceMetrics($days);

        $this->respondWithData($metrics, [
            'period_days' => $days
        ]);
    }

    /**
     * GET /api/recommendations/similar/{groupId}
     *
     * Get groups similar to a specific group.
     *
     * Query Parameters:
     * - limit: int (default 5, max 20)
     *
     * Response: 200 OK with array of similar groups
     */
    public function similar(int $groupId): void
    {
        $userId = $this->getUserId();

        $limit = $this->queryInt('limit', 5, 1, 20);

        // Get recommendations but exclude the current group
        $options = ['exclude_ids' => [$groupId]];

        $recommendations = GroupRecommendationEngine::getRecommendations($userId, $limit, $options);

        $this->respondWithData($recommendations, [
            'source_group_id' => $groupId,
            'count' => count($recommendations),
            'limit' => $limit
        ]);
    }
}
