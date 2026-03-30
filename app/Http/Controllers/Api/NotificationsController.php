<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
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
        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
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
        $query = \App\Models\Notification::where('user_id', $userId);

        if ($category) {
            // Use the same type categories as the service
            $typeCategories = [
                'messages'     => ['message', 'new_message', 'message_received', 'federation_message'],
                'connections'  => ['connection_request', 'connection_accepted', 'friend_request', 'friend_accepted'],
                'reviews'      => ['review', 'new_review', 'review_received'],
                'transactions' => ['transaction', 'payment', 'payment_received', 'credits_received'],
                'social'       => ['like', 'comment', 'mention', 'post_like', 'post_comment'],
                'events'       => ['event', 'event_reminder', 'event_rsvp', 'event_update'],
                'groups'       => ['group_invite', 'group_join', 'group_post'],
                'system'       => ['system', 'announcement', 'welcome', 'badge', 'achievement', 'level_up'],
            ];
            if (isset($typeCategories[$category])) {
                $query->whereIn('type', $typeCategories[$category]);
            }
        }

        $count = $query->count();
        $query->delete(); // soft-delete via SoftDeletes trait

        return $this->respondWithData(['deleted' => $count]);
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
                ->delete();
        } elseif ($all === true || $all === 'true') {
            DB::table('notifications')
                ->where('tenant_id', TenantContext::getId())
                ->where('user_id', $userId)
                ->delete();
        } else {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_id_or_action'), null, 400);
        }

        return $this->respondWithData(['message' => 'Notification deleted']);
    }
}
