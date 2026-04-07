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

        $query = \App\Models\Notification::query()
            ->where('user_id', $userId);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        // Fetch more than needed to allow grouping
        $rawNotifications = $query->orderByDesc('id')
            ->limit(200)
            ->get();

        // Group by type + link (link represents the target, e.g. "/posts/123")
        $groups = [];
        foreach ($rawNotifications as $notification) {
            $groupKey = $notification->type . ':' . ($notification->link ?? 'none');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            $groups[$groupKey][] = $notification;
        }

        // Build grouped response
        $result = [];
        foreach ($groups as $groupKey => $notifications) {
            $latest = $notifications[0]; // Already ordered by id DESC

            if (count($notifications) === 1) {
                // Single notification, no grouping
                $item = $latest->toArray();
                $item['is_grouped'] = false;
                $result[] = $item;
            } else {
                // Grouped notification
                $actorIds = [];
                $actors = [];
                $allRead = true;

                foreach ($notifications as $n) {
                    if (!$n->is_read) {
                        $allRead = false;
                    }
                    // Extract actor info if actor_id exists
                    if ($n->actor_id && !in_array($n->actor_id, $actorIds, true)) {
                        $actorIds[] = $n->actor_id;
                    }
                }

                // Fetch actor details (first 3)
                if (!empty($actorIds)) {
                    $actorUsers = DB::table('users')
                        ->whereIn('id', array_slice($actorIds, 0, 3))
                        ->get(['id', 'name', 'first_name', 'last_name', 'avatar_url']);

                    foreach ($actorUsers as $actorUser) {
                        $actors[] = [
                            'id' => $actorUser->id,
                            'name' => $actorUser->name ?: trim(($actorUser->first_name ?? '') . ' ' . ($actorUser->last_name ?? '')),
                            'avatar_url' => $actorUser->avatar_url,
                        ];
                    }
                }

                $count = count($notifications);
                $remainingCount = max(0, $count - 3);

                // Build grouped message
                $actorNames = array_map(fn ($a) => $a['name'], $actors);
                $action = $this->getActionVerb($latest->type);
                $target = $this->getTargetLabel($latest->type);

                if (count($actorNames) >= 2 && $remainingCount > 0) {
                    $message = "{$actorNames[0]}, {$actorNames[1]}, and {$remainingCount} others {$action} your {$target}";
                } elseif (count($actorNames) >= 2) {
                    $message = implode(' and ', $actorNames) . " {$action} your {$target}";
                } elseif (count($actorNames) === 1 && $count > 1) {
                    $message = "{$actorNames[0]} and " . ($count - 1) . " others {$action} your {$target}";
                } else {
                    $message = $latest->message ?? "{$count} notifications";
                }

                $item = $latest->toArray();
                $item['is_grouped'] = true;
                $item['group_key'] = $groupKey;
                $item['group_count'] = $count;
                $item['actors'] = $actors;
                $item['remaining_count'] = $remainingCount;
                $item['message'] = $message;
                $item['body'] = $message;
                $item['latest_at'] = $latest->created_at?->toIso8601String();
                $item['read_at'] = $allRead ? ($latest->created_at?->toIso8601String() ?? now()->toIso8601String()) : null;
                $item['notification_ids'] = array_map(fn ($n) => $n->id, $notifications);
                $result[] = $item;
            }
        }

        // Sort by latest_at (most recent first) and paginate
        usort($result, function ($a, $b) {
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });

        $paginated = array_slice($result, 0, $limit);
        $hasMore = count($result) > $limit;

        return $this->respondWithCollection(
            $paginated,
            $hasMore && !empty($paginated) ? base64_encode((string) end($paginated)['id']) : null,
            $limit,
            $hasMore
        );
    }

    /**
     * POST /api/v2/notifications/group/{groupKey}/read
     *
     * Mark all notifications in a group as read.
     */
    public function markGroupRead(string $groupKey): JsonResponse
    {
        $userId = $this->requireAuth();
        $groupKey = urldecode($groupKey);

        // Parse group key: "type:link"
        $parts = explode(':', $groupKey, 2);
        $type = $parts[0] ?? '';
        $link = ($parts[1] ?? '') === 'none' ? null : ($parts[1] ?? null);

        $query = \App\Models\Notification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('is_read', false);

        if ($link !== null) {
            $query->where('link', $link);
        } else {
            $query->whereNull('link');
        }

        $count = $query->update(['is_read' => true]);

        return $this->respondWithData([
            'marked_read' => $count,
        ]);
    }

    /**
     * Get a human-readable action verb for a notification type.
     */
    private function getActionVerb(string $type): string
    {
        return match ($type) {
            'like', 'post_like' => 'liked',
            'comment', 'post_comment' => 'commented on',
            'mention' => 'mentioned you in',
            'connection_request', 'friend_request' => 'sent you a connection request',
            'connection_accepted', 'friend_accepted' => 'accepted your connection',
            'review', 'new_review', 'review_received' => 'reviewed',
            'event_rsvp' => 'RSVP\'d to',
            'group_join' => 'joined',
            default => 'interacted with',
        };
    }

    /**
     * Get a human-readable target label for a notification type.
     */
    private function getTargetLabel(string $type): string
    {
        return match ($type) {
            'like', 'post_like', 'comment', 'post_comment', 'mention' => 'post',
            'review', 'new_review', 'review_received' => 'listing',
            'event_rsvp', 'event_reminder', 'event_update' => 'event',
            'group_join', 'group_invite', 'group_post' => 'group',
            'listing_interest', 'listing_match' => 'listing',
            default => 'content',
        };
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
