<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupService;
use Illuminate\Http\JsonResponse;

/**
 * GroupsController - Groups CRUD with join/leave.
 *
 * Endpoints (v2):
 *   GET    /api/v2/groups              index()
 *   GET    /api/v2/groups/{id}         show()
 *   POST   /api/v2/groups              store()
 *   POST   /api/v2/groups/{id}/join    join()
 *   POST   /api/v2/groups/{id}/leave   leave()
 */
class GroupsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupService $groupService,
    ) {}

    /**
     * List groups with optional search and pagination.
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('q')) {
            $filters['search'] = $this->query('q');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        $result = $this->groupService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Get a single group by ID.
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $group = $this->groupService->getById($id, $userId);

        if ($group === null) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData($group);
    }

    /**
     * Create a new group. Requires authentication.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('group_create', 5, 60);

        $group = $this->groupService->create($userId, $this->getAllInput());

        return $this->respondWithData($group, null, 201);
    }

    /**
     * Join a group. Requires authentication.
     */
    public function join(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('group_join', 20, 60);

        $result = $this->groupService->join($id, $userId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData($result);
    }

    /**
     * Leave a group. Requires authentication.
     */
    public function leave(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('group_leave', 20, 60);

        $result = $this->groupService->leave($id, $userId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData($result);
    }
}
