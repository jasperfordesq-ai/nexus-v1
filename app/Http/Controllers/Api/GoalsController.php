<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GoalService;
use App\Services\GoalCheckinService;
use App\Services\GoalProgressService;
use App\Services\GoalTemplateService;
use App\Services\GoalReminderService;
use Illuminate\Http\JsonResponse;

/**
 * GoalsController — Eloquent-powered CRUD and progress tracking for member goals.
 *
 * Fully migrated from legacy delegation to Eloquent via GoalService.
 */
class GoalsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GoalService $goalService,
        private readonly GoalCheckinService $checkinService,
        private readonly GoalProgressService $progressService,
        private readonly GoalTemplateService $templateService,
        private readonly GoalReminderService $reminderService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/goals
    // -----------------------------------------------------------------

    public function index(): JsonResponse
    {
        $userId = $this->getUserId();

        $filters = [
            'user_id'    => $this->queryInt('user_id', $userId),
            'status'     => $this->query('status', 'all'),
            'visibility' => $this->query('visibility', 'all'),
            'limit'      => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        // If querying another user's goals, only show public
        if ($filters['user_id'] !== $userId) {
            $filters['visibility'] = 'public';
        }

        $result = $this->goalService->getAll($filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int) ($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = ((int) ($goal['mentor_id'] ?? 0) === $userId && ($goal['mentor_id'] ?? null) !== null);
            return $goal;
        }, $result['items']);

        return $this->respondWithCollection(
            $items,
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/goals/{id}
    // -----------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        $goal = $this->goalService->getById($id);

        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found', null, 404);
        }

        if (! $goal->is_public && (int) $goal->user_id !== $userId) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', 'This goal is private', null, 403);
        }

        $data = $goal->toArray();
        $data['is_owner'] = ((int) $goal->user_id === $userId);
        $data['is_buddy'] = ((int) ($goal->mentor_id ?? 0) === $userId && $goal->mentor_id !== null);

        return $this->respondWithData($data);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/goals
    // -----------------------------------------------------------------

    public function store(): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_create', 10, 60);

        $data = $this->getAllInput();

        if (empty(trim($data['title'] ?? ''))) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Title is required', 'title', 400);
        }

        $goal = $this->goalService->create($userId, $data);
        $result = $goal->toArray();
        $result['is_owner'] = true;

        return $this->respondWithData($result, null, 201);
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/goals/{id}
    // -----------------------------------------------------------------

    public function update(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_update', 20, 60);

        $goal = $this->goalService->update($id, $userId, $this->getAllInput());

        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found or not owned', null, 404);
        }

        $data = $goal->toArray();
        $data['is_owner'] = true;

        return $this->respondWithData($data);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/goals/{id}
    // -----------------------------------------------------------------

    public function destroy(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_delete', 10, 60);

        $deleted = $this->goalService->delete($id, $userId);

        if (! $deleted) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found or not owned', null, 404);
        }

        return $this->noContent();
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/goals/{id}/progress
    // -----------------------------------------------------------------

    public function progress(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_progress', 30, 60);

        $increment = $this->input('increment');

        if ($increment === null) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Increment value is required', 'increment', 400);
        }

        $goal = $this->goalService->incrementProgress($id, $userId, (float) $increment);

        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found or not owned', null, 404);
        }

        $data = $goal->toArray();
        $data['is_owner'] = true;

        return $this->respondWithData($data);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/goals/{id}/complete
    // -----------------------------------------------------------------

    public function complete(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_complete', 10, 60);

        $goal = $this->goalService->complete($id, $userId);

        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found or not owned', null, 404);
        }

        $data = $goal->toArray();
        $data['is_owner'] = true;

        return $this->respondWithData($data);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/goals/discover
    // -----------------------------------------------------------------

    public function discover(): JsonResponse
    {
        $userId = $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->goalService->getPublicForBuddy($userId, $filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int) ($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = false;
            return $goal;
        }, $result['items']);

        return $this->respondWithCollection($items, $result['cursor'], $filters['limit'], $result['has_more']);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/goals/mentoring
    // -----------------------------------------------------------------

    public function mentoring(): JsonResponse
    {
        $userId = $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->goalService->getGoalsAsMentor($userId, $filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int) ($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = true;
            return $goal;
        }, $result['items']);

        return $this->respondWithCollection($items, $result['cursor'], $filters['limit'], $result['has_more']);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/goals/{id}/buddy
    // -----------------------------------------------------------------

    public function buddy(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_buddy', 10, 60);

        $goal = $this->goalService->offerBuddy($id, $userId);

        if (! $goal) {
            return $this->respondWithError('RESOURCE_CONFLICT', 'Cannot become buddy for this goal', null, 409);
        }

        return $this->respondWithData([
            'message' => 'You are now a buddy for this goal',
            'goal'    => $goal->toArray(),
        ]);
    }

    // -----------------------------------------------------------------
    //  Check-ins
    // -----------------------------------------------------------------

    public function createCheckin(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_checkin', 20, 60);

        $goal = $this->goalService->getById($id);
        if (! $goal || (int) $goal->user_id !== $userId) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found or not owned', null, 404);
        }

        $checkin = $this->checkinService->create($id, $userId, $this->getAllInput());

        return $this->respondWithData($checkin->toArray(), null, 201);
    }

    public function listCheckins(int $id): JsonResponse
    {
        $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->checkinService->getByGoalId($id, $filters);

        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    // -----------------------------------------------------------------
    //  Progress history
    // -----------------------------------------------------------------

    public function history(int $id): JsonResponse
    {
        $this->getUserId();

        $goal = $this->goalService->getById($id);
        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found', null, 404);
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        if ($this->query('event_type')) {
            $filters['event_type'] = $this->query('event_type');
        }

        $result = $this->progressService->getProgressHistory($id, $filters);

        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function historySummary(int $id): JsonResponse
    {
        $this->getUserId();

        $goal = $this->goalService->getById($id);
        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Goal not found', null, 404);
        }

        $summary = $this->progressService->getSummary($id);

        return $this->respondWithData($summary);
    }

    // -----------------------------------------------------------------
    //  Templates
    // -----------------------------------------------------------------

    public function templates(): JsonResponse
    {
        $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];

        if ($this->query('category')) {
            $filters['category'] = $this->query('category');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->templateService->getAll($filters);

        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function templateCategories(): JsonResponse
    {
        $this->getUserId();

        $categories = $this->templateService->getCategories();

        return $this->respondWithData($categories);
    }

    public function createTemplate(): JsonResponse
    {
        $userId = $this->requireAdmin();
        $this->rateLimit('goal_template_create', 10, 60);

        $data = $this->getAllInput();

        if (empty(trim($data['title'] ?? ''))) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Title is required', 'title', 400);
        }

        $template = $this->templateService->create($userId, $data);

        return $this->respondWithData($template->toArray(), null, 201);
    }

    public function createFromTemplate(int $templateId): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_create', 10, 60);

        $goal = $this->templateService->createGoalFromTemplate($templateId, $userId, $this->getAllInput());

        if (! $goal) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Template not found', null, 404);
        }

        $data = $goal->toArray();
        $data['is_owner'] = true;

        return $this->respondWithData($data, null, 201);
    }

    // -----------------------------------------------------------------
    //  Reminders
    // -----------------------------------------------------------------

    public function getReminder(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        $reminder = $this->reminderService->getReminder($id, $userId);

        return $this->respondWithData($reminder);
    }

    public function setReminder(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('goal_reminder', 20, 60);

        $reminder = $this->reminderService->setReminder($id, $userId, $this->getAllInput());

        return $this->respondWithData($reminder);
    }

    public function deleteReminder(int $id): JsonResponse
    {
        $userId = $this->getUserId();

        $this->reminderService->deleteReminder($id, $userId);

        return $this->noContent();
    }
}
