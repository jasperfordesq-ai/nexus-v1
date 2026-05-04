<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupService;
use App\Services\GroupScheduledPostService;

class GroupScheduledPostController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_admin_required'), null, 403);
        }
        return $this->successResponse(GroupScheduledPostService::getScheduled($id));
    }

    public function store(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_admin_required'), null, 403);
        }
        $data = request()->only(['post_type', 'title', 'content', 'scheduled_at', 'is_recurring', 'recurrence_pattern']);
        if (empty($data['content']) || empty($data['scheduled_at'])) {
            return $this->errorResponse(__('api.group_scheduled_content_date_required'), 400);
        }
        $postId = GroupScheduledPostService::schedule($id, $userId, $data);
        return $this->successResponse(['id' => $postId], 201);
    }

    public function cancel(int $id, int $postId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_admin_required'), null, 403);
        }
        return GroupScheduledPostService::cancel($id, $postId)
            ? $this->successResponse(['message' => __('api_controllers_3.group_scheduled_post.cancelled')])
            : $this->errorResponse(__('api_controllers_3.group_scheduled_post.not_found_or_published'), 404);
    }
}
