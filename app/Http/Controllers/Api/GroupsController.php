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
 * Native Eloquent methods: index, show, store, join, leave.
 * Complex features (members, discussions, announcements, etc.) delegate to legacy.
 */
class GroupsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupService $groupService,
    ) {}

    /**
     * GET /api/v2/groups
     *
     * List groups with optional search, type/visibility filters, and cursor pagination.
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('type_id')) {
            $filters['type_id'] = $this->queryInt('type_id');
        }
        if ($this->query('visibility')) {
            $filters['visibility'] = $this->query('visibility');
        }
        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }
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
     * GET /api/v2/groups/{id}
     *
     * Get a single group by ID with member count and viewer's membership status.
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
     * POST /api/v2/groups
     *
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
     * POST /api/v2/groups/{id}/join
     *
     * Join a group. For private groups creates a pending request.
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
     * DELETE /api/v2/groups/{id}/membership
     *
     * Leave a group.
     */
    public function leave(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('group_leave', 20, 60);

        $result = $this->groupService->leave($id, $userId);

        if (! $result) {
            return $this->respondWithError('NOT_FOUND', 'Group not found or not a member', null, 404);
        }

        return $this->noContent();
    }

    // ================================================================
    // Delegated methods — complex features that still use legacy services
    // ================================================================

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'update', [$id]);
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'destroy', [$id]);
    }


    public function members($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'members', [$id]);
    }


    public function updateMember($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'updateMember', [$id, $userId]);
    }


    public function removeMember($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'removeMember', [$id, $userId]);
    }


    public function pendingRequests($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'pendingRequests', [$id]);
    }


    public function handleRequest($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'handleRequest', [$id, $userId]);
    }


    public function discussions($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'discussions', [$id]);
    }


    public function createDiscussion($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'createDiscussion', [$id]);
    }


    public function discussionMessages($id, $discussionId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'discussionMessages', [$id, $discussionId]);
    }


    public function postToDiscussion($id, $discussionId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'postToDiscussion', [$id, $discussionId]);
    }


    public function uploadImage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'uploadImage', [$id]);
    }


    public function announcements($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'announcements', [$id]);
    }


    public function createAnnouncement($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'createAnnouncement', [$id]);
    }


    public function updateAnnouncement($id, $announcementId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'updateAnnouncement', [$id, $announcementId]);
    }


    public function deleteAnnouncement($id, $announcementId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\GroupsApiController::class, 'deleteAnnouncement', [$id, $announcementId]);
    }
}
