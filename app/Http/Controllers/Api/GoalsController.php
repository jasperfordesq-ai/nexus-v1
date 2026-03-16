<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GoalService;

/**
 * GoalsController -- CRUD and progress tracking for member goals.
 */
class GoalsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GoalService $goalService,
    ) {}

    /** GET /api/v2/goals */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();
        $goals = $this->goalService->getForUser($userId, $this->getTenantId());

        return $this->respondWithData($goals);
    }

    /** GET /api/v2/goals/{id} */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $goal = $this->goalService->getById($id, $this->getTenantId());

        if ($goal === null) {
            return $this->respondWithError('NOT_FOUND', 'Goal not found', null, 404);
        }

        return $this->respondWithData($goal);
    }

    /** POST /api/v2/goals */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('goal_create', 10, 60);

        $data = $this->getAllInput();
        $goal = $this->goalService->create($userId, $this->getTenantId(), $data);

        return $this->respondWithData($goal, null, 201);
    }

    /** PUT /api/v2/goals/{id}/progress */
    public function progress(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $progress = $this->inputInt('progress', 0, 0, 100);
        $result = $this->goalService->updateProgress($id, $userId, $this->getTenantId(), $progress);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Goal not found', null, 404);
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/goals/{id}/complete */
    public function complete(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $result = $this->goalService->markComplete($id, $userId, $this->getTenantId());

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Goal not found', null, 404);
        }

        return $this->respondWithData($result);
    }
}
