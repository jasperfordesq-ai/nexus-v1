<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\GoalService;
use Nexus\Services\GoalCheckinService;
use Nexus\Services\GoalProgressService;
use Nexus\Services\GoalTemplateService;
use Nexus\Services\GoalReminderService;
use Nexus\Core\ApiErrorCodes;

/**
 * GoalsApiController - RESTful API v2 for goals
 *
 * Provides full CRUD operations for goals with standardized v2 response format.
 * Includes progress tracking and buddy system.
 *
 * Endpoints:
 * - GET    /api/v2/goals              - List goals (my goals or public)
 * - POST   /api/v2/goals              - Create a new goal
 * - GET    /api/v2/goals/discover     - Get public goals available for buddy
 * - GET    /api/v2/goals/{id}         - Get a single goal
 * - PUT    /api/v2/goals/{id}         - Update a goal
 * - DELETE /api/v2/goals/{id}         - Delete a goal
 * - POST   /api/v2/goals/{id}/progress - Update goal progress
 * - POST   /api/v2/goals/{id}/buddy   - Offer to be a buddy
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 *
 * @package Nexus\Controllers\Api
 */
class GoalsApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/goals
     *
     * List goals with optional filtering and cursor-based pagination.
     * By default, returns the authenticated user's goals.
     *
     * Query Parameters:
     * - user_id: int (filter by user, default: current user)
     * - status: string ('active', 'completed', 'all') - default 'all'
     * - visibility: string ('public', 'private', 'all') - default 'all'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and pagination meta
     */
    public function index(): void
    {
        $userId = $this->getUserId();

        // Build filters from query parameters
        $filters = [
            'user_id' => $this->queryInt('user_id', $userId), // Default to current user
            'status' => $this->query('status', 'all'),
            'visibility' => $this->query('visibility', 'all'),
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        // If querying another user's goals, only show public
        if ($filters['user_id'] !== $userId) {
            $filters['visibility'] = 'public';
        }

        $result = GoalService::getAll($filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int)($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = ((int)($goal['buddy_id'] ?? 0) === $userId && $goal['buddy_id'] !== null);
            return $goal;
        }, $result['items']);

        $this->respondWithCollection(
            $items,
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/goals/discover
     *
     * Get public goals available for buddy offers.
     * Excludes the current user's goals and goals that already have buddies.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and pagination meta
     */
    public function discover(): void
    {
        $userId = $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GoalService::getPublicForBuddy($userId, $filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int)($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = ((int)($goal['buddy_id'] ?? 0) === $userId && $goal['buddy_id'] !== null);
            return $goal;
        }, $result['items']);

        $this->respondWithCollection(
            $items,
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/goals/mentoring
     *
     * Get goals where the current user is a buddy/mentor.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and pagination meta
     */
    public function mentoring(): void
    {
        $userId = $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GoalService::getGoalsIAmBuddyFor($userId, $filters);

        $items = array_map(function (array $goal) use ($userId) {
            $goal['is_owner'] = ((int)($goal['user_id'] ?? 0) === $userId);
            $goal['is_buddy'] = true;
            return $goal;
        }, $result['items']);

        $this->respondWithCollection(
            $items,
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/goals/{id}
     *
     * Get a single goal by ID.
     * Private goals can only be viewed by their owner.
     *
     * Response: 200 OK with goal data, or 404 if not found
     */
    public function show(int $id): void
    {
        $userId = $this->getUserId();

        $goal = GoalService::getById($id);

        if (!$goal) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Goal not found',
                null,
                404
            );
        }

        // Check if user can view this goal
        if (!$goal['is_public'] && (int)$goal['user_id'] !== $userId) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_FORBIDDEN,
                'This goal is private',
                null,
                403
            );
        }

        // Add ownership and buddy flags
        $goal['is_owner'] = ((int)$goal['user_id'] === $userId);
        $goal['is_buddy'] = ((int)($goal['buddy_id'] ?? 0) === $userId && $goal['buddy_id'] !== null);

        $this->respondWithData($goal);
    }

    /**
     * POST /api/v2/goals
     *
     * Create a new goal.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "description": "string (optional)",
     *   "target_value": "float (optional, default 0)",
     *   "deadline": "datetime (optional) - ISO 8601 format",
     *   "is_public": "bool (optional, default false)"
     * }
     *
     * Response: 201 Created with new goal data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_create', 10, 60);

        $data = $this->getAllInput();

        $goalId = GoalService::create($userId, $data);

        if ($goalId === null) {
            $errors = GoalService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        $goal = GoalService::getById($goalId);
        $goal['is_owner'] = true;

        $this->respondWithData($goal, null, 201);
    }

    /**
     * PUT /api/v2/goals/{id}
     *
     * Update an existing goal.
     * Only the goal owner can update it.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (optional)",
     *   "description": "string (optional)",
     *   "target_value": "float (optional)",
     *   "deadline": "datetime (optional)",
     *   "is_public": "bool (optional)"
     * }
     *
     * Response: 200 OK with updated goal data
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_update', 20, 60);

        $data = $this->getAllInput();

        $success = GoalService::update($id, $userId, $data);

        if (!$success) {
            $errors = GoalService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $goal = GoalService::getById($id);
        $goal['is_owner'] = true;

        $this->respondWithData($goal);
    }

    /**
     * DELETE /api/v2/goals/{id}
     *
     * Delete a goal.
     * Only the goal owner or an admin can delete it.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_delete', 10, 60);

        $success = GoalService::delete($id, $userId);

        if (!$success) {
            $errors = GoalService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/goals/{id}/progress
     *
     * Update goal progress by incrementing the current value.
     *
     * Request Body (JSON):
     * {
     *   "increment": "float (required) - can be negative"
     * }
     *
     * Response: 200 OK with updated goal data
     */
    public function progress(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_progress', 30, 60);

        $increment = $this->input('increment');

        if ($increment === null) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Increment value is required',
                'increment',
                400
            );
        }

        $goal = GoalService::updateProgress($id, $userId, (float)$increment);

        if ($goal === null) {
            $errors = GoalService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $goal['is_owner'] = true;

        $this->respondWithData($goal);
    }

    /**
     * POST /api/v2/goals/{id}/buddy
     *
     * Offer to be a buddy/mentor for a goal.
     * The goal must be public and not already have a buddy.
     *
     * Response: 200 OK with updated goal data
     */
    public function buddy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_buddy', 10, 60);

        $success = GoalService::offerBuddy($id, $userId);

        if (!$success) {
            $errors = GoalService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $goal = GoalService::getById($id);

        $this->respondWithData([
            'message' => 'You are now a buddy for this goal',
            'goal' => $goal
        ]);
    }

    /**
     * POST /api/v2/goals/{id}/complete
     *
     * Mark a goal as complete by setting progress to the target value.
     *
     * Response: 200 OK with updated goal data
     */
    public function complete(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_complete', 10, 60);

        $goal = GoalService::getById($id);

        if (!$goal) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Goal not found',
                null,
                404
            );
            return;
        }

        if ((int) ($goal['user_id'] ?? 0) !== $userId) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_FORBIDDEN,
                'You can only complete your own goals',
                null,
                403
            );
            return;
        }

        // Set progress to target value to complete the goal
        $target = (float) ($goal['target_value'] ?? 1);
        $current = (float) ($goal['current_value'] ?? 0);
        $remaining = $target - $current;

        if ($remaining > 0) {
            $updated = GoalService::updateProgress($id, $userId, $remaining);
            if ($updated === null) {
                $this->respondWithErrors(GoalService::getErrors(), 400);
                return;
            }
        }

        // Reload
        $goal = GoalService::getById($id);
        $goal['is_owner'] = true;

        $this->respondWithData($goal);
    }

    // ============================================
    // CHECK-INS (G3)
    // ============================================

    /**
     * POST /api/v2/goals/{id}/checkins
     *
     * Create a check-in for a goal.
     *
     * Request Body (JSON):
     * {
     *   "progress_percent": "float (optional, 0-100)",
     *   "note": "string (optional)",
     *   "mood": "string (optional) - great|good|neutral|struggling|stuck"
     * }
     *
     * Response: 201 Created with check-in data
     */
    public function createCheckin(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_checkin', 20, 60);

        $data = $this->getAllInput();

        $checkinId = GoalCheckinService::create($id, $userId, $data);

        if ($checkinId === null) {
            $errors = GoalCheckinService::getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) { $status = 403; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        // Return the check-in list for the goal
        $checkins = GoalCheckinService::getByGoalId($id, ['limit' => 1]);

        $this->respondWithData($checkins['items'][0] ?? ['id' => $checkinId], null, 201);
    }

    /**
     * GET /api/v2/goals/{id}/checkins
     *
     * Get check-in history for a goal.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with check-in list
     */
    public function listCheckins(int $id): void
    {
        $this->getUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GoalCheckinService::getByGoalId($id, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    // ============================================
    // PROGRESS HISTORY (G5)
    // ============================================

    /**
     * GET /api/v2/goals/{id}/history
     *
     * Get the full progress timeline for a goal.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 50, max 100)
     * - event_type: string (optional filter)
     *
     * Response: 200 OK with timeline events
     */
    public function history(int $id): void
    {
        $this->getUserId();

        // Verify goal exists
        $goal = GoalService::getById($id);
        if (!$goal) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found', null, 404);
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

        $result = GoalProgressService::getProgressHistory($id, $filters);

        // Also include summary
        $summary = GoalProgressService::getSummary($id);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/goals/{id}/history/summary
     *
     * Get a summary of progress history for a goal.
     *
     * Response: 200 OK with summary data
     */
    public function historySummary(int $id): void
    {
        $this->getUserId();

        $goal = GoalService::getById($id);
        if (!$goal) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Goal not found', null, 404);
        }

        $summary = GoalProgressService::getSummary($id);

        $this->respondWithData($summary);
    }

    // ============================================
    // TEMPLATES (G1)
    // ============================================

    /**
     * GET /api/v2/goals/templates
     *
     * List available goal templates.
     *
     * Query Parameters:
     * - category: string (filter by category)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 50)
     *
     * Response: 200 OK with template list
     */
    public function templates(): void
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

        $result = GoalTemplateService::getAll($filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/goals/templates/categories
     *
     * List available template categories.
     *
     * Response: 200 OK with category strings
     */
    public function templateCategories(): void
    {
        $this->getUserId();

        $categories = GoalTemplateService::getCategories();

        $this->respondWithData($categories);
    }

    /**
     * POST /api/v2/goals/templates
     *
     * Create a new goal template (admin only).
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "description": "string (optional)",
     *   "category": "string (optional)",
     *   "default_target_value": "float (optional)",
     *   "default_milestones": "[{title, target_value}] (optional)",
     *   "is_public": "bool (optional, default true)"
     * }
     *
     * Response: 201 Created with template data
     */
    public function createTemplate(): void
    {
        $userId = $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('goal_template_create', 10, 60);

        $data = $this->getAllInput();

        $templateId = GoalTemplateService::create($userId, $data);

        if ($templateId === null) {
            $this->respondWithErrors(GoalTemplateService::getErrors(), 422);
        }

        $template = GoalTemplateService::getById($templateId);

        $this->respondWithData($template, null, 201);
    }

    /**
     * POST /api/v2/goals/from-template/{templateId}
     *
     * Create a new goal from a template.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (optional - overrides template)",
     *   "description": "string (optional)",
     *   "target_value": "float (optional)",
     *   "deadline": "datetime (optional)",
     *   "is_public": "bool (optional)"
     * }
     *
     * Response: 201 Created with new goal data
     */
    public function createFromTemplate(int $templateId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_create', 10, 60);

        $overrides = $this->getAllInput();

        $goalId = GoalTemplateService::createGoalFromTemplate($templateId, $userId, $overrides);

        if ($goalId === null) {
            $errors = GoalTemplateService::getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $goal = GoalService::getById($goalId);
        $goal['is_owner'] = true;

        $this->respondWithData($goal, null, 201);
    }

    // ============================================
    // REMINDERS (G4)
    // ============================================

    /**
     * GET /api/v2/goals/{id}/reminder
     *
     * Get the reminder settings for a goal.
     *
     * Response: 200 OK with reminder data, or 204 if no reminder set
     */
    public function getReminder(int $id): void
    {
        $userId = $this->getUserId();

        $reminder = GoalReminderService::getReminder($id, $userId);

        if ($reminder === null) {
            $this->respondWithData(null);
            return;
        }

        $this->respondWithData($reminder);
    }

    /**
     * PUT /api/v2/goals/{id}/reminder
     *
     * Set or update reminder for a goal.
     *
     * Request Body (JSON):
     * {
     *   "frequency": "string (daily|weekly|biweekly|monthly)",
     *   "enabled": "bool (optional, default true)"
     * }
     *
     * Response: 200 OK with reminder data
     */
    public function setReminder(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('goal_reminder', 20, 60);

        $data = $this->getAllInput();

        $reminder = GoalReminderService::setReminder($id, $userId, $data);

        if ($reminder === null) {
            $errors = GoalReminderService::getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) { $status = 404; break; }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) { $status = 403; break; }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) { $status = 409; break; }
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($reminder);
    }

    /**
     * DELETE /api/v2/goals/{id}/reminder
     *
     * Remove the reminder for a goal.
     *
     * Response: 204 No Content
     */
    public function deleteReminder(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        GoalReminderService::deleteReminder($id, $userId);

        $this->noContent();
    }
}
