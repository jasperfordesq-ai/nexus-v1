<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\ConnectionService;

/**
 * ConnectionsApiController - RESTful API for user connections (friends)
 *
 * Provides connection management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/connections                     - List connections (cursor paginated)
 * - GET    /api/v2/connections/pending             - Pending request counts
 * - GET    /api/v2/connections/status/{userId}     - Get status with specific user
 * - POST   /api/v2/connections/request             - Send connection request
 * - POST   /api/v2/connections/{id}/accept         - Accept connection request
 * - DELETE /api/v2/connections/{id}                - Remove connection or reject/cancel request
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class ConnectionsApiController extends BaseApiController
{
    /**
     * GET /api/v2/connections
     *
     * List connections with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - status: 'accepted' (default), 'pending_sent', 'pending_received'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with connections array and pagination meta
     */
    public function index(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('connections_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = ConnectionService::getConnections($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/connections/pending
     *
     * Get pending connection request counts.
     *
     * Response: 200 OK with counts
     */
    public function pendingCounts(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('connections_pending', 120, 60);

        $counts = ConnectionService::getPendingCounts($userId);
        $counts['total_friends'] = ConnectionService::getFriendsCount($userId);

        $this->respondWithData($counts);
    }

    /**
     * GET /api/v2/connections/status/{userId}
     *
     * Get connection status with a specific user.
     *
     * Response: 200 OK with status info
     */
    public function status(int $otherUserId): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('connections_status', 120, 60);

        $status = ConnectionService::getStatus($userId, $otherUserId);

        $this->respondWithData($status);
    }

    /**
     * POST /api/v2/connections/request
     *
     * Send a connection request to another user.
     *
     * Request Body (JSON):
     * {
     *   "user_id": int (required)
     * }
     *
     * Response: 201 Created with status
     */
    public function request(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('connections_request', 20, 60);

        $receiverId = $this->inputInt('user_id');

        if (!$receiverId) {
            $this->respondWithError('VALIDATION_ERROR', 'User ID is required', 'user_id', 400);
        }

        $success = ConnectionService::sendRequest($userId, $receiverId);

        if (!$success) {
            $errors = ConnectionService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'ALREADY_CONNECTED' || $error['code'] === 'REQUEST_EXISTS') {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Get updated status
        $newStatus = ConnectionService::getStatus($userId, $receiverId);

        $this->respondWithData([
            'status' => $newStatus['status'],
            'connection_id' => $newStatus['connection_id'],
            'message' => $newStatus['status'] === 'accepted'
                ? 'Connection accepted (they had already sent you a request)'
                : 'Connection request sent',
        ], null, 201);
    }

    /**
     * POST /api/v2/connections/{id}/accept
     *
     * Accept a connection request.
     *
     * Response: 200 OK with status
     */
    public function accept(int $connectionId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('connections_accept', 30, 60);

        $success = ConnectionService::acceptRequest($connectionId, $userId);

        if (!$success) {
            $errors = ConnectionService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
                if ($error['code'] === 'INVALID_STATE') {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData([
            'connection_id' => $connectionId,
            'status' => 'accepted',
        ]);
    }

    /**
     * DELETE /api/v2/connections/{id}
     *
     * Remove a connection or reject/cancel a pending request.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $connectionId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('connections_delete', 30, 60);

        // Try to remove as accepted connection first
        $success = ConnectionService::removeConnection($connectionId, $userId);

        if (!$success) {
            // Maybe it's a pending request - try reject
            $success = ConnectionService::rejectRequest($connectionId, $userId);
        }

        if (!$success) {
            $errors = ConnectionService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }
}
