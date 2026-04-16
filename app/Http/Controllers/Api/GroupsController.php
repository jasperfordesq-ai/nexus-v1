<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupNotificationService;
use Illuminate\Http\JsonResponse;
use App\Services\GroupService;
use App\Services\GroupAnnouncementService;

/**
 * GroupsController - Groups CRUD, members, discussions, announcements.
 *
 * Converted from delegation to direct static service calls.
 * All methods are now native Laravel — no legacy delegation remains.
 */
class GroupsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupAnnouncementService $groupAnnouncementService,
        private readonly GroupNotificationService $groupNotificationService,
        private readonly GroupService $groupService,
    ) {}

    // ================================================================
    // LIST / SHOW
    // ================================================================

    /**
     * GET /api/v2/groups
     */
    public function index(): JsonResponse
    {
        $userId = $this->resolveSanctumUserOptionally();

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

        $result = $this->groupService->getAll($filters);

        // Batch-load viewer memberships (single query instead of N+1)
        if ($userId && !empty($result['items'])) {
            $groupIds = array_column($result['items'], 'id');
            $membershipMap = $this->groupService->getViewerMembershipsBatch($groupIds, $userId);
            foreach ($result['items'] as &$group) {
                $group['viewer_membership'] = $membershipMap[(int) $group['id']] ?? null;
            }
        }

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/groups/{id}
     *
     * Public endpoint (registered outside auth:sanctum group).
     * Resolves authenticated user via Auth::guard('api') when Bearer token is
     * present, so viewer_membership is populated for logged-in users.
     */
    public function show(int $id): JsonResponse
    {
        // This route is outside the auth:sanctum middleware group, so use the
        // base controller helper that falls back to manual token lookup when
        // the Sanctum guard fails for stateful-domain requests.
        $userId = $this->resolveSanctumUserOptionally();

        $group = $this->groupService->getById($id, $userId);

        if (!$group) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        return $this->respondWithData($group);
    }

    // ================================================================
    // CREATE / UPDATE / DELETE
    // ================================================================

    /**
     * POST /api/v2/groups
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('groups_create', 10, 60);

        $data = $this->getAllInput();

        $createdGroup = $this->groupService->create($userId, $data);

        if ($createdGroup === null) {
            $errors = $this->groupService->getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        // create() returns a Group model — use its ID to fetch the full response
        $groupId = $createdGroup instanceof \App\Models\Group ? $createdGroup->id : (int) $createdGroup;
        $group = $this->groupService->getById($groupId, $userId);

        // Award XP for creating a group
        try {
            \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['create_group'], 'create_group', 'Created a group');
        } catch (\Throwable $e) {
            \Log::warning('Gamification XP award failed', ['action' => 'create_group', 'user' => $userId, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData($group, null, 201);
    }

    /**
     * PUT /api/v2/groups/{id}
     */
    public function update($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_update', 20, 60);

        $data = $this->getAllInput();

        $success = $this->groupService->update($id, $userId, $data);

        if (!$success) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        $group = $this->groupService->getById($id, $userId);

        return $this->respondWithData($group);
    }

    /**
     * DELETE /api/v2/groups/{id}
     */
    public function destroy($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_delete', 10, 60);

        $success = $this->groupService->delete($id, $userId);

        if (!$success) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->noContent();
    }

    // ================================================================
    // JOIN / LEAVE
    // ================================================================

    /**
     * POST /api/v2/groups/{id}/join
     */
    public function join(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('groups_join', 30, 60);

        $result = $this->groupService->join($id, $userId);

        if (!($result['success'] ?? false)) {
            $error = $result['error'] ?? 'Failed to join group';
            $httpStatus = str_contains($error, 'banned') ? 403
                : (str_contains($error, 'Already') || str_contains($error, 'pending') ? 409 : 422);
            return $this->respondWithError('JOIN_FAILED', $error, null, $httpStatus);
        }

        $joinStatus = $result['status'] ?? 'active';

        // Notify based on join result
        try {
            if ($joinStatus === 'active') {
                $this->groupNotificationService->notifyJoined($id, $userId);
            } elseif ($joinStatus === 'pending') {
                $this->groupNotificationService->notifyJoinRequest($id, $userId);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Group join notification error: " . $e->getMessage());
        }

        // Award XP when user actually joins (not just pending request)
        if ($joinStatus === 'active') {
            try {
                \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['join_group'], 'join_group', 'Joined a group');
            } catch (\Throwable $e) {
                \Log::warning('Gamification XP award failed', ['action' => 'join_group', 'user' => $userId, 'error' => $e->getMessage()]);
            }
        }

        return $this->respondWithData([
            'status'  => $joinStatus,
            'message' => $joinStatus === 'active' ? __('api.group_joined') : __('api.group_join_requested'),
        ]);
    }

    /**
     * DELETE /api/v2/groups/{id}/membership
     */
    public function leave(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('groups_leave', 30, 60);

        $success = $this->groupService->leave($id, $userId);

        if (!$success) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->noContent();
    }

    // ================================================================
    // MEMBERS
    // ================================================================

    /**
     * GET /api/v2/groups/{id}/members
     */
    public function members($id): JsonResponse
    {
        $id = (int) $id;

        $group = $this->groupService->getById($id);
        if (!$group) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        // For private groups, only members can view the member list
        if (($group['visibility'] ?? 'public') === 'private') {
            $userId = $this->getOptionalUserId();
            if (!$userId) {
                return $this->respondWithError('FORBIDDEN', __('api.private_group_members_only'), null, 403);
            }
            $isMember = \Illuminate\Support\Facades\DB::table('group_members')
                ->where('group_id', $id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();
            if (!$isMember) {
                return $this->respondWithError('FORBIDDEN', __('api.private_group_members_only'), null, 403);
            }
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

        $result = $this->groupService->getMembers($id, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * PUT /api/v2/groups/{id}/members/{userId}
     */
    public function updateMember($id, $targetUserId): JsonResponse
    {
        $id = (int) $id;
        $targetUserId = (int) $targetUserId;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_member_update', 30, 60);

        $role = $this->input('role');

        if (empty($role)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.role_required'), 'role', 400);
        }

        $success = $this->groupService->updateMemberRole($id, $targetUserId, $userId, $role);

        if (!$success) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData([
            'user_id' => $targetUserId,
            'role'    => $role,
        ]);
    }

    /**
     * DELETE /api/v2/groups/{id}/members/{userId}
     */
    public function removeMember($id, $targetUserId): JsonResponse
    {
        $id = (int) $id;
        $targetUserId = (int) $targetUserId;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_member_remove', 20, 60);

        $success = $this->groupService->removeMember($id, $targetUserId, $userId);

        if (!$success) {
            $errors = $this->groupService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->noContent();
    }

    // ================================================================
    // JOIN REQUESTS
    // ================================================================

    /**
     * GET /api/v2/groups/{id}/requests
     */
    public function pendingRequests($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $requests = $this->groupService->getPendingRequests($id, $userId);

        if ($requests === null) {
            $errors = $this->groupService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($requests);
    }

    /**
     * POST /api/v2/groups/{id}/requests/{userId}
     */
    public function handleRequest($id, $requesterId): JsonResponse
    {
        $id = (int) $id;
        $requesterId = (int) $requesterId;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_handle_request', 30, 60);

        $action = $this->input('action');

        if (empty($action)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.action_required'), 'action', 400);
        }

        $success = $this->groupService->handleJoinRequest($id, $requesterId, $userId, $action);

        if (!$success) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        // Notify requester
        try {
            if ($action === 'accept') {
                $this->groupNotificationService->notifyJoined($id, $requesterId);
            } else {
                $this->groupNotificationService->notifyJoinRejected($id, $requesterId);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Group request notification error: " . $e->getMessage());
        }

        // Award XP to the requester when their join request is accepted
        if ($action === 'accept') {
            try {
                \App\Services\GamificationService::awardXP($requesterId, \App\Services\GamificationService::XP_VALUES['join_group'], 'join_group', 'Joined a group (request accepted)');
            } catch (\Throwable $e) {
                \Log::warning('Gamification XP award failed', ['action' => 'join_group', 'user' => $requesterId, 'error' => $e->getMessage()]);
            }
        }

        return $this->respondWithData([
            'user_id' => $requesterId,
            'action'  => $action,
            'result'  => $action === 'accept' ? 'approved' : 'rejected',
        ]);
    }

    // ================================================================
    // DISCUSSIONS
    // ================================================================

    /**
     * GET /api/v2/groups/{id}/discussions
     */
    public function discussions($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->groupService->getDiscussions($id, $userId, $filters);

        if ($result === null) {
            $errors = $this->groupService->getErrors();
            $status = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/groups/{id}/discussions
     */
    public function createDiscussion($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit("groups_create_discussion_{$id}", 10, 60);

        $data = $this->getAllInput();

        $discussion = $this->groupService->createDiscussion($id, $userId, $data);

        if ($discussion === null) {
            $errors = $this->groupService->getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        // Notify group members of new discussion
        try {
            $discussionTitle = $discussion['title'] ?? $data['title'] ?? 'New Discussion';
            $discussionId = $discussion['id'] ?? 0;
            $this->groupNotificationService->notifyNewDiscussion($id, $discussionId, $userId, $discussionTitle);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Group discussion notification error: " . $e->getMessage());
        }

        return $this->respondWithData($discussion, null, 201);
    }

    /**
     * GET /api/v2/groups/{id}/discussions/{discussionId}
     */
    public function discussionMessages($id, $discussionId): JsonResponse
    {
        $id = (int) $id;
        $discussionId = (int) $discussionId;
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->groupService->getDiscussionMessages($id, $discussionId, $userId, $filters);

        if ($result === null) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData([
            'discussion' => $result['discussion'],
            'messages'   => $result['items'],
        ], [
            'cursor'   => $result['cursor'],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/groups/{id}/discussions/{discussionId}/messages
     */
    public function postToDiscussion($id, $discussionId): JsonResponse
    {
        $id = (int) $id;
        $discussionId = (int) $discussionId;
        $userId = $this->requireAuth();
        $this->rateLimit("groups_post_to_discussion_{$id}", 30, 60);

        $data = $this->getAllInput();

        $message = $this->groupService->postToDiscussion($id, $discussionId, $userId, $data);

        if ($message === null) {
            $errors = $this->groupService->getErrors();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($message, null, 201);
    }

    // ================================================================
    // ANNOUNCEMENTS
    // ================================================================

    /**
     * GET /api/v2/groups/{id}/announcements
     */
    public function announcements($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $filters = [
            'cursor'          => $this->query('cursor'),
            'limit'           => $this->queryInt('limit', 20, 1, 100),
            'include_expired' => $this->queryBool('include_expired'),
        ];

        $result = $this->groupAnnouncementService->list($id, $userId, $filters);

        if ($result === null) {
            $errors = $this->groupAnnouncementService->getErrors();
            $status = $this->resolveErrorStatus($errors);
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($result);
    }

    /**
     * POST /api/v2/groups/{id}/announcements
     */
    public function createAnnouncement($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $data = $this->getAllInput();

        $result = $this->groupAnnouncementService->create($id, $userId, $data);

        if ($result === null) {
            $errors = $this->groupAnnouncementService->getErrors();
            $status = $this->resolveErrorStatus($errors);
            return $this->respondWithErrors($errors, $status);
        }

        // Notify group members of new announcement
        try {
            $announcementTitle = $result['title'] ?? $data['title'] ?? 'New Announcement';
            $this->groupNotificationService->notifyNewAnnouncement($id, $userId, $announcementTitle);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Group announcement notification error: " . $e->getMessage());
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * PUT /api/v2/groups/{id}/announcements/{announcementId}
     */
    public function updateAnnouncement($id, $announcementId): JsonResponse
    {
        $id = (int) $id;
        $announcementId = (int) $announcementId;
        $userId = $this->requireAuth();
        $data = $this->getAllInput();

        $result = $this->groupAnnouncementService->update($id, $announcementId, $userId, $data);

        if ($result === null) {
            $errors = $this->groupAnnouncementService->getErrors();
            $status = $this->resolveErrorStatus($errors);
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($result);
    }

    /**
     * DELETE /api/v2/groups/{id}/announcements/{announcementId}
     */
    public function deleteAnnouncement($id, $announcementId): JsonResponse
    {
        $id = (int) $id;
        $announcementId = (int) $announcementId;
        $userId = $this->requireAuth();

        $success = $this->groupAnnouncementService->delete($id, $announcementId, $userId);

        if (!$success) {
            $errors = $this->groupAnnouncementService->getErrors();
            $status = $this->resolveErrorStatus($errors);
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData(['deleted' => true]);
    }

    // ================================================================
    // IMAGE UPLOAD
    // ================================================================

    /**
     * POST /api/v2/groups/{id}/image
     *
     * Upload an image for a group (avatar or cover). Uses request()->file() (Laravel native).
     * Field name: 'image'. Query param 'type' = 'avatar' (default) or 'cover'.
     */
    public function uploadImage($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('groups_image_upload', 10, 60);

        $file = request()->file('image');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.no_image_uploaded'), 'image', 400);
        }

        $imageType = $this->query('type', 'avatar');
        if (!in_array($imageType, ['avatar', 'cover'])) {
            $imageType = 'avatar';
        }

        try {
            // Build a $_FILES-compatible array for ImageUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $imageUrl = \App\Core\ImageUploader::upload($fileArray);

            $success = $this->groupService->updateImage($id, $userId, $imageUrl, $imageType);

            if (!$success) {
                $errors = $this->groupService->getErrors();
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
                return $this->respondWithErrors($errors, $status);
            }

            return $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            \Log::error('Group image upload failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('UPLOAD_FAILED', __('api.failed_upload_image'), 'image', 500);
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function resolveErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            if ($error['code'] === 'NOT_FOUND') {
                return 404;
            }
            if ($error['code'] === 'FORBIDDEN') {
                return 403;
            }
        }
        return 400;
    }
}
