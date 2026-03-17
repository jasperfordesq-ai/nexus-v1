<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Services\BrokerMessageVisibilityService;

/**
 * CoreController -- Core platform endpoints (members, listings, groups,
 * messages, notifications).
 *
 * Converted from legacy delegation to DB facade / static services.
 */
class CoreController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ──────────────────────────────────────────────
    // Messaging endpoints — keep as delegation because
    // sendMessage uses Pusher real-time + email sending
    // ──────────────────────────────────────────────

    /** POST /api/messages/send — uses Pusher + email, keep delegation */
    public function sendMessage(): JsonResponse
    {
        $this->requireAuth();

        return $this->delegate(
            \Nexus\Controllers\Api\CoreApiController::class,
            'sendMessage'
        );
    }

    /** POST /api/messages/typing — uses Pusher, keep delegation */
    public function typing(): JsonResponse
    {
        $this->requireAuth();

        return $this->delegate(
            \Nexus\Controllers\Api\CoreApiController::class,
            'typing'
        );
    }

    /** GET /api/messages/poll */
    public function pollMessages(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('poll_messages', 120, 60);

        $otherUserId = $this->queryInt('other_user_id', 0, 1);
        $afterId = $this->queryInt('after', 0, 0);

        if (!$otherUserId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Missing other_user_id', 'other_user_id', 400);
        }

        $query = DB::table('messages')
            ->select('id', 'sender_id', 'body', 'audio_url', 'audio_duration', 'created_at')
            ->where(function ($q) use ($userId, $otherUserId) {
                $q->where(function ($inner) use ($userId, $otherUserId) {
                    $inner->where('sender_id', $userId)->where('receiver_id', $otherUserId);
                })->orWhere(function ($inner) use ($userId, $otherUserId) {
                    $inner->where('sender_id', $otherUserId)->where('receiver_id', $userId);
                });
            });

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $messages = $query->orderBy('id', 'asc')->limit(50)->get()->map(fn ($m) => (array) $m)->all();

        return $this->respondWithData($messages);
    }

    /** GET /api/messages/unread-count */
    public function unreadMessagesCount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('unread_messages', 120, 60);

        $count = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $count += (int) $thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $count = 0;
        }

        return $this->respondWithData(['count' => $count]);
    }

    // ──────────────────────────────────────────────
    // Contact form — uses email sending, keep delegation
    // ──────────────────────────────────────────────

    /** POST /api/contact */
    public function apiSubmit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\ContactController::class, 'apiSubmit');
    }

    // ──────────────────────────────────────────────
    // Members, Listings, Groups — converted to DB facade
    // ──────────────────────────────────────────────

    /** GET /api/members */
    public function members(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('members', 60, 60);

        $tenantId = $this->getTenantId();
        $searchQuery = trim($this->query('q', ''));
        $activeOnly = $this->query('active') === 'true';
        $limit = $this->queryInt('limit', 100, 1, 500);
        $offset = $this->queryInt('offset', 0, 0);

        $builder = DB::table('users')
            ->select('id', 'name', 'email', 'avatar_url as avatar', 'role', 'bio', 'location', 'last_active_at')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('avatar_url')
            ->whereRaw('LENGTH(avatar_url) > 0');

        if (!empty($searchQuery) && strlen($searchQuery) >= 2) {
            $term = "%{$searchQuery}%";
            $builder->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term)
                  ->orWhere('bio', 'like', $term)
                  ->orWhere('location', 'like', $term);
            });
        }

        if ($activeOnly) {
            $builder->whereRaw('last_active_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
        }

        $totalCount = $builder->count();

        $members = $builder
            ->orderByDesc('last_active_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn ($m) => (array) $m)
            ->all();

        $page = ($offset / max($limit, 1)) + 1;

        return $this->respondWithPaginatedCollection($members, $totalCount, (int) $page, $limit);
    }

    /** GET /api/listings */
    public function listings(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('listings', 60, 60);

        $listings = DB::table('listings')
            ->select('id', 'title', 'description', 'price', 'type', 'created_at', 'image_url as image', 'user_id')
            ->where('tenant_id', $this->getTenantId())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => (array) $l)
            ->all();

        return $this->respondWithData($listings);
    }

    /** GET /api/groups */
    public function groups(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('groups', 60, 60);

        $groups = DB::table('groups')
            ->select('id', 'name', 'description', 'image_url as image')
            ->where('tenant_id', $this->getTenantId())
            ->get()
            ->map(fn ($g) => (array) $g)
            ->all();

        foreach ($groups as &$g) {
            $g['members'] = (int) DB::table('group_members')
                ->where('group_id', $g['id'])
                ->count();
        }

        return $this->respondWithData($groups);
    }

    /** GET /api/messages */
    public function messages(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages', 60, 60);

        $messages = DB::table('messages as m')
            ->join('users as u', 'm.sender_id', '=', 'u.id')
            ->select('m.*', 'u.name as sender_name', 'u.avatar_url as sender_avatar')
            ->where('m.recipient_id', $userId)
            ->groupBy('m.sender_id')
            ->orderByDesc('m.created_at')
            ->get()
            ->map(fn ($m) => (array) $m)
            ->all();

        return $this->respondWithData($messages);
    }

    /** GET /api/notifications */
    public function notifications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('notifications', 120, 60);

        $notifs = DB::table('notifications')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => (array) $n)
            ->all();

        return $this->respondWithData($notifs);
    }

    /** GET /api/notifications/check */
    public function checkNotifications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('check_notifications', 120, 60);

        $count = \Nexus\Models\Notification::countUnread($userId);

        return $this->respondWithData(['unread_count' => $count]);
    }

    /** GET /api/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('unread_count', 120, 60);

        $messagesCount = 0;
        try {
            if (class_exists('Nexus\Models\MessageThread')) {
                $threads = \Nexus\Models\MessageThread::getForUser($userId);
                foreach ($threads as $thread) {
                    if (!empty($thread['unread_count'])) {
                        $messagesCount += (int) $thread['unread_count'];
                    }
                }
            }
        } catch (\Exception) {
            $messagesCount = 0;
        }

        $notificationsCount = \Nexus\Models\Notification::countUnread($userId);

        return $this->respondWithData([
            'messages' => $messagesCount,
            'notifications' => $notificationsCount,
            'total' => $messagesCount + $notificationsCount,
        ]);
    }

    /**
     * Delegate to legacy controller via output buffering.
     * Kept for methods that use Pusher real-time or email sending.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
