<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupService;
use App\Services\GroupTagService;

/**
 * GroupTagController — Tag management, discovery, and suggestions for groups.
 */
class GroupTagController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/tags
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        if (!GroupService::canView($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_tags_forbidden'), null, 403);
        }

        $tags = GroupTagService::getForGroup($id);

        return $this->respondWithData($tags);
    }

    /**
     * PUT /api/v2/groups/{id}/tags
     */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_admin_required'), null, 403);
        }

        $tagIds = request()->input('tag_ids');

        if (!is_array($tagIds)) {
            return $this->error(__('api.group_tags_array_required'), 422);
        }

        GroupTagService::setForGroup($id, $tagIds);
        $updatedTags = GroupTagService::getForGroup($id);

        return $this->respondWithData($updatedTags);
    }

    /**
     * GET /api/v2/tags
     */
    public function allTags(): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $filters = [
            'search' => $this->query('q'),
            'limit' => $this->queryInt('limit', 50),
        ];

        $tags = GroupTagService::getAll($filters);

        return $this->respondWithData($tags);
    }

    /**
     * GET /api/v2/tags/popular
     */
    public function popular(): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $limit = $this->queryInt('limit', 20);
        $tags = GroupTagService::getPopular($limit);

        return $this->respondWithData($tags);
    }

    /**
     * GET /api/v2/tags/suggest
     */
    public function suggest(): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $q = $this->query('q', '');
        $suggestions = GroupTagService::suggest($q);

        return $this->respondWithData($suggestions);
    }
}
