<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;

/**
 * NotificationService — Laravel DI-based service for notification operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\NotificationService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class NotificationService
{
    /**
     * Notification type categories for grouping and filtering.
     */
    private const TYPE_CATEGORIES = [
        'messages'     => ['message', 'new_message', 'message_received', 'federation_message'],
        'connections'  => ['connection_request', 'connection_accepted', 'friend_request', 'friend_accepted'],
        'reviews'      => ['review', 'new_review', 'review_received'],
        'transactions' => ['transaction', 'payment', 'payment_received', 'credits_received'],
        'social'       => ['like', 'comment', 'mention', 'post_like', 'post_comment'],
        'events'       => ['event', 'event_reminder', 'event_rsvp', 'event_update'],
        'groups'       => ['group_invite', 'group_join', 'group_post'],
        'system'       => ['system', 'announcement', 'welcome', 'badge', 'achievement', 'level_up'],
    ];

    public function __construct(
        private readonly Notification $notification,
    ) {}

    /**
     * Get notifications with cursor-based pagination.
     *
     * @param array{type?: string, unread_only?: bool, cursor?: string|null, limit?: int} $filters
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->notification->newQuery()
            ->where('user_id', $userId);

        if (! empty($filters['unread_only'])) {
            $query->unread();
        }

        if (! empty($filters['type']) && isset(self::TYPE_CATEGORIES[$filters['type']])) {
            $query->whereIn('type', self::TYPE_CATEGORIES[$filters['type']]);
        }

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get unread counts grouped by type category.
     *
     * @return array{total: int, categories: array<string, int>}
     */
    public function getCounts(int $userId): array
    {
        $unread = $this->notification->newQuery()
            ->where('user_id', $userId)
            ->unread()
            ->get(['type']);

        $total = $unread->count();

        $categories = [];
        foreach (self::TYPE_CATEGORIES as $category => $types) {
            $count = $unread->whereIn('type', $types)->count();
            if ($count > 0) {
                $categories[$category] = $count;
            }
        }

        return [
            'total'      => $total,
            'categories' => $categories,
        ];
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllRead(int $userId): int
    {
        return $this->notification->newQuery()
            ->where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }
}
