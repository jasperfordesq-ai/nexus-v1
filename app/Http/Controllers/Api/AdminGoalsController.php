<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\GoalService;

/**
 * AdminGoalsController -- Admin goals management.
 *
 * All endpoints require admin authentication.
 */
class AdminGoalsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/goals
     *
     * Query params: search, page, limit
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $filters = [
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = GoalService::getAll($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            return $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        }

        return $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/goals/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();

        $goal = GoalService::getById($id);

        if (!$goal) {
            return $this->respondWithError('NOT_FOUND', 'Goal not found', null, 404);
        }

        return $this->respondWithData($goal);
    }

    /**
     * DELETE /api/v2/admin/goals/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $goal = GoalService::getById($id);
        if (!$goal) {
            return $this->respondWithError('NOT_FOUND', 'Goal not found', null, 404);
        }

        $deleted = GoalService::delete($id, $adminId);

        if ($deleted) {
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        }

        return $this->respondWithError('DELETE_FAILED', 'Failed to delete goal', null, 400);
    }
}
