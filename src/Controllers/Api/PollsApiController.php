<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\PollService;
use Nexus\Core\ApiErrorCodes;

/**
 * PollsApiController - RESTful API v2 for polls
 *
 * Provides full CRUD operations for polls with standardized v2 response format.
 * This controller follows the same patterns as ListingsApiController.
 *
 * Endpoints:
 * - GET    /api/v2/polls           - List all polls (paginated)
 * - POST   /api/v2/polls           - Create a new poll
 * - GET    /api/v2/polls/{id}      - Get a single poll
 * - PUT    /api/v2/polls/{id}      - Update a poll
 * - DELETE /api/v2/polls/{id}      - Delete a poll
 * - POST   /api/v2/polls/{id}/vote - Vote on a poll
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 *
 * @package Nexus\Controllers\Api
 */
class PollsApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/polls
     *
     * List polls with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - status: string ('open', 'closed', 'all') - default 'open'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     * - user_id: int (filter by creator)
     *
     * Response: 200 OK with data array and pagination meta
     */
    public function index(): void
    {
        // Require authentication
        $userId = $this->getUserId();

        // Build filters from query parameters
        $filters = [
            'status' => $this->query('status', 'open'),
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }

        // Get polls
        $result = PollService::getAll($filters);

        // Add has_voted to each poll for current user
        foreach ($result['items'] as &$poll) {
            if (!isset($poll['has_voted'])) {
                $poll = PollService::getById($poll['id'], $userId);
            }
        }

        // Return with cursor-based pagination
        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/polls/{id}
     *
     * Get a single poll by ID.
     *
     * Response: 200 OK with poll data, or 404 if not found
     */
    public function show(int $id): void
    {
        $userId = $this->getUserId();

        $poll = PollService::getById($id, $userId);

        if (!$poll) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Poll not found',
                null,
                404
            );
        }

        $this->respondWithData($poll);
    }

    /**
     * POST /api/v2/polls
     *
     * Create a new poll.
     *
     * Request Body (JSON):
     * {
     *   "question": "string (required)",
     *   "description": "string (optional)",
     *   "expires_at": "datetime (optional) - ISO 8601 format",
     *   "options": ["Option 1", "Option 2", ...] (required, min 2, max 10)
     * }
     *
     * Response: 201 Created with new poll data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('poll_create', 5, 60);

        $data = $this->getAllInput();

        $pollId = PollService::create($userId, $data);

        if ($pollId === null) {
            $errors = PollService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        // Fetch the created poll
        $poll = PollService::getById($pollId, $userId);

        $this->respondWithData($poll, null, 201);
    }

    /**
     * PUT /api/v2/polls/{id}
     *
     * Update an existing poll.
     * Only the poll creator can update it.
     * Cannot edit polls that are closed or have votes.
     *
     * Request Body (JSON):
     * {
     *   "question": "string (optional)",
     *   "description": "string (optional)",
     *   "expires_at": "datetime (optional)"
     * }
     *
     * Note: Options cannot be modified after creation.
     *
     * Response: 200 OK with updated poll data
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('poll_update', 10, 60);

        $data = $this->getAllInput();

        $success = PollService::update($id, $userId, $data);

        if (!$success) {
            $errors = PollService::getErrors();
            $status = 422;

            // Determine appropriate status code
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

        // Fetch the updated poll
        $poll = PollService::getById($id, $userId);

        $this->respondWithData($poll);
    }

    /**
     * DELETE /api/v2/polls/{id}
     *
     * Delete a poll.
     * Only the poll creator or an admin can delete it.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('poll_delete', 5, 60);

        $success = PollService::delete($id, $userId);

        if (!$success) {
            $errors = PollService::getErrors();
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
     * POST /api/v2/polls/{id}/vote
     *
     * Vote on a poll.
     *
     * Request Body (JSON):
     * {
     *   "option_id": int (required)
     * }
     *
     * Response: 200 OK with updated poll data (showing results)
     */
    public function vote(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('poll_vote', 20, 60);

        $optionId = $this->input('option_id');

        if (empty($optionId)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Option ID is required',
                'option_id',
                400
            );
        }

        $success = PollService::vote($id, (int)$optionId, $userId);

        if (!$success) {
            $errors = PollService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Return the updated poll with vote results
        $poll = PollService::getById($id, $userId);

        $this->respondWithData($poll);
    }
}
