<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PollService;
use Illuminate\Http\JsonResponse;

/**
 * AdminPollsController -- Admin poll management.
 *
 * All endpoints require admin authentication.
 */
class AdminPollsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PollService $pollService,
    ) {}

    /**
     * GET /api/v2/admin/polls
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

        $result = $this->pollService->getAll($filters);

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
     * GET /api/v2/admin/polls/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();

        $poll = $this->pollService->getById($id);

        if (!$poll) {
            return $this->respondWithError('NOT_FOUND', __('api.poll_not_found'), null, 404);
        }

        return $this->respondWithData($poll);
    }

    /**
     * DELETE /api/v2/admin/polls/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $poll = $this->pollService->getById($id);
        if (!$poll) {
            return $this->respondWithError('NOT_FOUND', __('api.poll_not_found'), null, 404);
        }

        $deleted = $this->pollService->delete($id, $adminId);

        if ($deleted) {
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        }

        return $this->respondWithError('DELETE_FAILED', __('api.poll_delete_failed'), null, 400);
    }
}
