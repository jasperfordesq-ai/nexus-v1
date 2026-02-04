<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\GroupService;
use Nexus\Core\ImageUploader;

/**
 * GroupsApiController - RESTful API for groups
 *
 * Provides group management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/groups                           - List groups (cursor paginated)
 * - GET    /api/v2/groups/{id}                      - Get single group
 * - POST   /api/v2/groups                           - Create group
 * - PUT    /api/v2/groups/{id}                      - Update group
 * - DELETE /api/v2/groups/{id}                      - Delete group
 * - POST   /api/v2/groups/{id}/join                 - Join group
 * - DELETE /api/v2/groups/{id}/membership           - Leave group
 * - GET    /api/v2/groups/{id}/members              - List members (cursor paginated)
 * - PUT    /api/v2/groups/{id}/members/{userId}     - Update member role
 * - DELETE /api/v2/groups/{id}/members/{userId}     - Remove member
 * - GET    /api/v2/groups/{id}/requests             - List pending join requests (admin)
 * - POST   /api/v2/groups/{id}/requests/{userId}    - Handle join request (admin)
 * - GET    /api/v2/groups/{id}/discussions          - List discussions (cursor paginated)
 * - POST   /api/v2/groups/{id}/discussions          - Create discussion
 * - GET    /api/v2/groups/{id}/discussions/{discId} - Get discussion messages
 * - POST   /api/v2/groups/{id}/discussions/{discId}/messages - Post to discussion
 * - POST   /api/v2/groups/{id}/image                - Upload group image
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class GroupsApiController extends BaseApiController
{
    /**
     * GET /api/v2/groups
     *
     * List groups with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - type: 'all' (default), 'hubs', or 'community'
     * - type_id: int (specific group type ID)
     * - visibility: 'public' or 'private'
     * - user_id: int (filter by user's memberships)
     * - q: string (search term)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with groups array and pagination meta
     */
    public function index(): void
    {
        // Optional auth - adds user's membership status to results if logged in
        $userId = $this->getOptionalUserId();
        $this->rateLimit('groups_list', 60, 60);

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

        $result = GroupService::getAll($filters);

        // Add user's membership status to each group if logged in
        if ($userId) {
            foreach ($result['items'] as &$group) {
                $fullGroup = GroupService::getById($group['id'], $userId);
                if ($fullGroup) {
                    $group['viewer_membership'] = $fullGroup['viewer_membership'] ?? null;
                }
            }
        }

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/groups/{id}
     *
     * Get a single group by ID with full details and membership info.
     *
     * Response: 200 OK with group data, or 404 if not found
     */
    public function show(int $id): void
    {
        // Optional auth - adds user's membership status
        $userId = $this->getOptionalUserId();
        $this->rateLimit('groups_show', 120, 60);

        $group = GroupService::getById($id, $userId);

        if (!$group) {
            $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        $this->respondWithData($group);
    }

    /**
     * POST /api/v2/groups
     *
     * Create a new group.
     *
     * Request Body (JSON):
     * {
     *   "name": "string (required)",
     *   "description": "string",
     *   "visibility": "public|private",
     *   "location": "string",
     *   "latitude": "float",
     *   "longitude": "float",
     *   "type_id": "int",
     *   "federated_visibility": "none|listed|joinable"
     * }
     *
     * Response: 201 Created with new group data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_create', 10, 60);

        $data = $this->getAllInput();

        $groupId = GroupService::create($userId, $data);

        if ($groupId === null) {
            $errors = GroupService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Fetch the created group
        $group = GroupService::getById($groupId, $userId);

        $this->respondWithData($group, null, 201);
    }

    /**
     * PUT /api/v2/groups/{id}
     *
     * Update an existing group.
     *
     * Request Body (JSON): Same as store, all fields optional
     *
     * Response: 200 OK with updated group data
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_update', 20, 60);

        $data = $this->getAllInput();

        $success = GroupService::update($id, $userId, $data);

        if (!$success) {
            $errors = GroupService::getErrors();
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
            }

            $this->respondWithErrors($errors, $status);
        }

        // Fetch the updated group
        $group = GroupService::getById($id, $userId);

        $this->respondWithData($group);
    }

    /**
     * DELETE /api/v2/groups/{id}
     *
     * Delete a group.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_delete', 10, 60);

        $success = GroupService::delete($id, $userId);

        if (!$success) {
            $errors = GroupService::getErrors();
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

    /**
     * POST /api/v2/groups/{id}/join
     *
     * Join a group. For private groups, this creates a join request.
     *
     * Response: 200 OK with membership status
     */
    public function join(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_join', 30, 60);

        $status = GroupService::join($id, $userId);

        if ($status === null) {
            $errors = GroupService::getErrors();
            $httpStatus = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'ALREADY_MEMBER' || $error['code'] === 'PENDING') {
                    $httpStatus = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $httpStatus);
        }

        $this->respondWithData([
            'status' => $status,
            'message' => $status === 'active' ? 'Successfully joined the group' : 'Join request submitted',
        ]);
    }

    /**
     * DELETE /api/v2/groups/{id}/membership
     *
     * Leave a group.
     *
     * Response: 204 No Content on success
     */
    public function leave(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_leave', 30, 60);

        $success = GroupService::leave($id, $userId);

        if (!$success) {
            $errors = GroupService::getErrors();
            $httpStatus = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'NOT_MEMBER') {
                    $httpStatus = 409;
                    break;
                }
                if ($error['code'] === 'SOLE_ADMIN') {
                    $httpStatus = 422;
                    break;
                }
            }

            $this->respondWithErrors($errors, $httpStatus);
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/groups/{id}/members
     *
     * List group members with cursor-based pagination.
     *
     * Query Parameters:
     * - role: 'owner', 'admin', 'member' (optional filter)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with members array and pagination meta
     */
    public function members(int $id): void
    {
        // Optional auth
        $this->getOptionalUserId();
        $this->rateLimit('groups_members', 60, 60);

        // Verify group exists
        $group = GroupService::getById($id);
        if (!$group) {
            $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('role')) {
            $filters['role'] = $this->query('role');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupService::getMembers($id, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * PUT /api/v2/groups/{id}/members/{userId}
     *
     * Update a member's role.
     *
     * Request Body (JSON):
     * {
     *   "role": "admin|member"
     * }
     *
     * Response: 200 OK with updated member info
     */
    public function updateMember(int $id, int $targetUserId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_member_update', 30, 60);

        $role = $this->input('role');

        if (empty($role)) {
            $this->respondWithError('VALIDATION_ERROR', 'Role is required', 'role', 400);
        }

        $success = GroupService::updateMemberRole($id, $targetUserId, $userId, $role);

        if (!$success) {
            $errors = GroupService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_MEMBER') {
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

        $this->respondWithData([
            'user_id' => $targetUserId,
            'role' => $role,
        ]);
    }

    /**
     * DELETE /api/v2/groups/{id}/members/{userId}
     *
     * Remove a member from the group.
     *
     * Response: 204 No Content on success
     */
    public function removeMember(int $id, int $targetUserId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_member_remove', 20, 60);

        $success = GroupService::removeMember($id, $targetUserId, $userId);

        if (!$success) {
            $errors = GroupService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/groups/{id}/requests
     *
     * List pending join requests (admin only).
     *
     * Response: 200 OK with pending requests array
     */
    public function pendingRequests(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('groups_requests', 60, 60);

        $requests = GroupService::getPendingRequests($id, $userId);

        if ($requests === null) {
            $errors = GroupService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($requests);
    }

    /**
     * POST /api/v2/groups/{id}/requests/{userId}
     *
     * Handle a join request (accept/reject).
     *
     * Request Body (JSON):
     * {
     *   "action": "accept|reject"
     * }
     *
     * Response: 200 OK with result
     */
    public function handleRequest(int $id, int $requesterId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_handle_request', 30, 60);

        $action = $this->input('action');

        if (empty($action)) {
            $this->respondWithError('VALIDATION_ERROR', 'Action is required', 'action', 400);
        }

        $success = GroupService::handleJoinRequest($id, $requesterId, $userId, $action);

        if (!$success) {
            $errors = GroupService::getErrors();
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
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData([
            'user_id' => $requesterId,
            'action' => $action,
            'result' => $action === 'accept' ? 'approved' : 'rejected',
        ]);
    }

    /**
     * GET /api/v2/groups/{id}/discussions
     *
     * List discussions in a group (members only).
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with discussions array and pagination meta
     */
    public function discussions(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('groups_discussions', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupService::getDiscussions($id, $userId, $filters);

        if ($result === null) {
            $errors = GroupService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/groups/{id}/discussions
     *
     * Create a discussion (members only).
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "content": "string (required, first post content)"
     * }
     *
     * Response: 201 Created with discussion ID
     */
    public function createDiscussion(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_create_discussion', 10, 60);

        $data = $this->getAllInput();

        $discussionId = GroupService::createDiscussion($id, $userId, $data);

        if ($discussionId === null) {
            $errors = GroupService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData(['id' => $discussionId], null, 201);
    }

    /**
     * GET /api/v2/groups/{id}/discussions/{discussionId}
     *
     * Get discussion messages (members only).
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 50, max 100)
     *
     * Response: 200 OK with discussion info and messages
     */
    public function discussionMessages(int $id, int $discussionId): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('groups_discussion_messages', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupService::getDiscussionMessages($id, $discussionId, $userId, $filters);

        if ($result === null) {
            $errors = GroupService::getErrors();
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

        // Return discussion with messages collection
        $this->respondWithData([
            'discussion' => $result['discussion'],
            'messages' => $result['items'],
        ], [
            'cursor' => $result['cursor'],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/groups/{id}/discussions/{discussionId}/messages
     *
     * Post a message to a discussion (members only).
     *
     * Request Body (JSON):
     * {
     *   "content": "string (required)"
     * }
     *
     * Response: 201 Created with message ID
     */
    public function postToDiscussion(int $id, int $discussionId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_post_to_discussion', 30, 60);

        $data = $this->getAllInput();

        $postId = GroupService::postToDiscussion($id, $discussionId, $userId, $data);

        if ($postId === null) {
            $errors = GroupService::getErrors();
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
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData(['id' => $postId], null, 201);
    }

    /**
     * POST /api/v2/groups/{id}/image
     *
     * Upload a group image (avatar or cover).
     *
     * Request: multipart/form-data with 'image' file
     * Query Parameters:
     * - type: 'avatar' (default) or 'cover'
     *
     * Response: 200 OK with image URL
     */
    public function uploadImage(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('groups_image_upload', 10, 60);

        // Check for uploaded file
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'No image file uploaded or upload error', 'image', 400);
        }

        $imageType = $this->query('type', 'avatar');
        if (!in_array($imageType, ['avatar', 'cover'])) {
            $imageType = 'avatar';
        }

        try {
            $imageUrl = ImageUploader::upload($_FILES['image']);

            $success = GroupService::updateImage($id, $userId, $imageUrl, $imageType);

            if (!$success) {
                $errors = GroupService::getErrors();
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

            $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image: ' . $e->getMessage(), 'image', 400);
        }
    }
}
