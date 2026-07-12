<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * NotificationsController - User notification management.
 *
 * Native Eloquent/DB implementation (no delegation).
 *
 * Endpoints (v2):
 *   GET    /api/v2/notifications            index()
 *   GET    /api/v2/notifications/counts     counts()
 *   POST   /api/v2/notifications/read-all   markAllRead()
 *   POST   /api/v2/notifications/{id}/read  markRead()
 *   GET    /api/v2/notifications/{id}       show()
 *   DELETE /api/v2/notifications/{id}       destroy()
 *   DELETE /api/v2/notifications            destroyAll()
 *
 * Legacy endpoints (converted from delegation):
 *   GET    /api/v2/notifications/poll       poll()
 *   DELETE /api/v2/notifications/delete     delete()
 */
class NotificationsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * GET /api/v2/notifications
     *
     * List notifications for the authenticated user with cursor-based pagination.
     *
     * Query params: per_page (int, default 20), cursor (string), type (string),
     *               unread_only (bool).
     *
     * Response: { data: [...], meta: { cursor, per_page, has_more } }
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        $type = $this->query('type');
        if (is_string($type) && $type !== '') {
            if (! $this->notificationService->supportsCategory($type)) {
                return $this->invalidCategoryResponse('type');
            }
            $filters['type'] = $type;
        }
        if ($this->queryBool('unread_only')) {
            $filters['unread_only'] = true;
        }

        $result = $this->notificationService->getAll($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * GET /api/v2/notifications/counts
     *
     * Get unread notification counts (total + by category).
     *
     * Response: { data: { total: N, categories: { messages: N, ... } } }
     */
    public function counts(): JsonResponse
    {
        $userId = $this->requireAuth();

        $counts = $this->notificationService->getCounts($userId);

        return $this->respondWithData($counts);
    }

    /**
     * POST /api/v2/notifications/read-all
     *
     * Mark all notifications as read for the authenticated user.
     *
     * Response: { data: { marked_all_read: true } }
     */
    public function markAllRead(): JsonResponse
    {
        $userId = $this->requireAuth();

        $count = $this->notificationService->markAllRead($userId);

        return $this->respondWithData([
            'marked_all_read' => true,
            'marked_read' => $count,
        ]);
    }

    /**
     * GET /api/v2/notifications/grouped
     *
     * List notifications grouped by type + target (link).
     * Similar notifications (e.g., multiple likes on the same post)
     * are combined into a single grouped entry.
     *
     * Query params: per_page (int, default 20), cursor (string)
     *
     * Response: { data: [...groupedNotifications], meta: {...} }
     */
    public function grouped(): JsonResponse
    {
        $userId = $this->requireAuth();

        $limit = $this->queryInt('per_page', 20, 1, 100);
        $cursor = $this->query('cursor');

        $result = $this->notificationService->getGroupedNotifications($userId, [
            'limit' => $limit,
            'cursor' => is_string($cursor) ? $cursor : null,
        ]);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $limit,
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/notifications/group/read
     * POST /api/v2/notifications/group/{groupKey}/read
     *
     * Mark all notifications in a group as read.
     *
     * The body-based route exists because some group keys contain encoded slashes
     * (for example notification links like /federation/messages), which are not
     * reliable as path parameters across proxies and web servers.
     */
    public function markGroupRead(Request $request, ?string $groupKey = null): JsonResponse
    {
        $userId = $this->requireAuth();
        $groupKey = $groupKey ?? $request->input('group_key');

        if (!is_string($groupKey) || trim($groupKey) === '') {
            return $this->respondWithError('INVALID_GROUP_KEY', __('api.field_required'), 'group_key', 422);
        }

        $count = $this->notificationService->markGroupRead($userId, $groupKey);

        return $this->respondWithData([
            'marked_read' => $count,
        ]);
    }

    /**
     * POST /api/v2/notifications/{id}/read
     *
     * Mark a single notification as read.
     *
     * Response: { data: { ...notification } }
     */
    public function markRead(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $notification = $this->notificationService->getById($id, $userId);
        if ($notification === null) {
            return $this->respondWithError('NOT_FOUND', __('api.notification_not_found'), null, 404);
        }

        // Mark as read via direct update
        \App\Models\Notification::where('id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => true]);

        // Return updated notification
        $updated = $this->notificationService->getById($id, $userId);
        return $this->respondWithData($updated);
    }

    /**
     * GET /api/v2/notifications/{id}
     *
     * Get a single notification by ID.
     *
     * Response: { data: { ...notification } }
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $notification = $this->notificationService->getById($id, $userId);

        if ($notification === null) {
            return $this->respondWithError('NOT_FOUND', __('api.notification_not_found'), null, 404);
        }

        return $this->respondWithData($notification);
    }

    /**
     * DELETE /api/v2/notifications/{id}
     *
     * Delete (soft-delete) a notification.
     *
     * Response: 204 No Content
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $deleted = $this->notificationService->delete($id, $userId);

        if (!$deleted) {
            return $this->respondWithError('NOT_FOUND', __('api.notification_not_found'), null, 404);
        }

        return $this->noContent();
    }

    /**
     * DELETE /api/v2/notifications
     *
     * Delete all notifications for the user.
     *
     * Response: { data: { deleted: N } }
     */
    public function destroyAll(): JsonResponse
    {
        $userId = $this->requireAuth();

        $category = $this->query('category');

        if (is_string($category) && $category !== '' && ! $this->notificationService->supportsCategory($category)) {
            return $this->invalidCategoryResponse();
        }

        $count = $this->notificationService->deleteAll(
            $userId,
            is_string($category) && $category !== '' ? $category : null
        );

        return $this->respondWithData(['deleted' => $count]);
    }

    private function invalidCategoryResponse(string $field = 'category'): JsonResponse
    {
        return $this->respondWithError(
            'INVALID_CATEGORY',
            __('api.invalid_category_type', [
                'types' => implode(', ', $this->notificationService->categoryNames()),
            ]),
            $field,
            422
        );
    }

    // ========================================================================
    // Converted legacy endpoints (formerly delegation)
    // ========================================================================

    /**
     * GET /api/v2/notifications/poll
     *
     * Lightweight polling endpoint — returns unread notification count.
     *
     * Response: { "success": true, "count": N }
     */
    public function poll(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        if (!$userId) {
            return $this->respondWithData(['count' => 0]);
        }

        $unreadCount = (int) DB::table('notifications')
            ->where('tenant_id', TenantContext::getId())
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->whereNull('deleted_at')
            ->count();

        return $this->respondWithData(['count' => $unreadCount]);
    }

    /**
     * DELETE /api/v2/notifications/delete (legacy)
     *
     * Legacy delete endpoint. Accepts { id: N } or { all: true }.
     *
     * Response: { "success": true }
     */
    public function delete(): JsonResponse
    {
        $userId = $this->requireAuth();

        $id = $this->input('id');
        $all = $this->input('all');

        if ($id) {
            DB::table('notifications')
                ->where('id', (int) $id)
                ->where('tenant_id', TenantContext::getId())
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        } elseif ($all === true || $all === 'true') {
            DB::table('notifications')
                ->where('tenant_id', TenantContext::getId())
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        } else {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_id_or_action'), null, 400);
        }

        return $this->respondWithData(['message' => __('api_controllers_2.notifications.deleted')]);
    }
}
