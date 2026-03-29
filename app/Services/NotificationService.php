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
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class NotificationService
{
    /**
     * Notification type categories for grouping and filtering.
     */
    private const TYPE_CATEGORIES = [
        'messages'     => ['message', 'new_message', 'message_received', 'federation_message'],
        'connections'  => ['connection_request', 'connection_accepted', 'friend_request', 'friend_accepted', 'federation_connection_request', 'federation_connection_accepted'],
        'reviews'      => ['review', 'new_review', 'review_received'],
        'transactions' => ['transaction', 'payment', 'payment_received', 'credits_received', 'federation_transaction'],
        'social'       => ['like', 'comment', 'mention', 'post_like', 'post_comment'],
        'events'       => ['event', 'event_reminder', 'event_rsvp', 'event_update'],
        'groups'       => ['group_invite', 'group_join', 'group_post', 'federation_group_join'],
        'listings'     => ['listing', 'listing_interest', 'listing_match', 'listing_expiry', 'hot_match', 'mutual_match'],
        'jobs'         => ['job_application', 'job_application_status'],
        'safeguarding' => ['safeguarding_flag', 'safeguarding_assignment', 'broker_review', 'safeguarding_incident'],
        'system'       => ['system', 'announcement', 'welcome', 'badge', 'achievement', 'level_up'],
        'security'     => ['password_changed', 'email_changed', '2fa_enabled', '2fa_disabled', 'passkey_registered', 'passkey_removed', 'security'],
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

        // Build flat counts matching legacy response shape:
        // { total, messages, connections, reviews, transactions, social, events, groups, listings, system, other }
        $counts = [
            'total' => 0,
            'messages' => 0,
            'connections' => 0,
            'reviews' => 0,
            'transactions' => 0,
            'social' => 0,
            'events' => 0,
            'groups' => 0,
            'listings' => 0,
            'jobs' => 0,
            'safeguarding' => 0,
            'system' => 0,
            'security' => 0,
            'other' => 0,
        ];

        foreach ($unread as $notification) {
            $counts['total']++;
            $categorized = false;

            foreach (self::TYPE_CATEGORIES as $category => $types) {
                if (in_array($notification->type, $types, true)) {
                    $counts[$category]++;
                    $categorized = true;
                    break;
                }
            }

            if (!$categorized) {
                $counts['other']++;
            }
        }

        return $counts;
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

    /**
     * Get a single notification by ID for the given user.
     *
     * @return array|null Notification data or null if not found
     */
    public function getById(int $id, int $userId): ?array
    {
        $notification = $this->notification->newQuery()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        return $notification?->toArray();
    }

    /**
     * Delete (soft-delete) a notification.
     */
    public function delete(int $id, int $userId): bool
    {
        $notification = $this->notification->newQuery()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($notification === null) {
            return false;
        }

        return (bool) $notification->delete();
    }
}
