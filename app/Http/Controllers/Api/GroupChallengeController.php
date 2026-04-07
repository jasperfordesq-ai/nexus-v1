<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupChallengeService;

class GroupChallengeController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;

        $showAll = $this->query('all') === '1';
        $challenges = $showAll
            ? GroupChallengeService::getAll($id, $this->queryInt('limit', 20))
            : GroupChallengeService::getActive($id);

        return $this->successResponse($challenges);
    }

    public function store(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;

        $data = request()->only(['title', 'description', 'metric', 'target_value', 'reward_xp', 'reward_badge', 'ends_at']);
        if (empty($data['title']) || empty($data['metric']) || empty($data['target_value']) || empty($data['ends_at'])) {
            return $this->errorResponse('title, metric, target_value, and ends_at are required', 400);
        }

        $challengeId = GroupChallengeService::create($id, $userId, $data);
        return $this->successResponse(['id' => $challengeId], 201);
    }

    public function destroy(int $id, int $challengeId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;

        return GroupChallengeService::delete($challengeId)
            ? $this->successResponse(['message' => __('api_controllers_1.group_challenge.challenge_deleted')])
            : $this->errorResponse('Challenge not found', 404);
    }
}
