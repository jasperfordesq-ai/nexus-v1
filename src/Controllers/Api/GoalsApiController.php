<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\GoalService;
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

        $this->respondWithCollection(
            $result['items'],
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

        $this->respondWithCollection(
            $result['items'],
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

        // Add ownership flag
        $goal['is_owner'] = ((int)$goal['user_id'] === $userId);

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
}
