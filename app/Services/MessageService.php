<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MessageService — Laravel DI-based service for messaging operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class MessageService
{
    public function __construct(
        private readonly Message $message,
    ) {}

    /**
     * Get user's conversations (inbox) with cursor-based pagination.
     *
     * Groups messages by conversation partner and returns the latest message
     * in each conversation, ordered by most recent.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getConversations(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        // Get the latest message ID per conversation partner
        $tenantId = app('tenant.id');
        $showArchived = (bool) ($filters['archived'] ?? false);

        $latestIds = DB::table('messages')
            ->selectRaw('
                MAX(id) as latest_id,
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id
            ', [$userId])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->when(!$showArchived, function ($q) use ($userId) {
                // Inbox: exclude messages archived by the current user
                $q->whereRaw('NOT (sender_id = ? AND archived_by_sender IS NOT NULL)', [$userId])
                  ->whereRaw('NOT (receiver_id = ? AND archived_by_receiver IS NOT NULL)', [$userId]);
            })
            ->when($showArchived, function ($q) use ($userId) {
                // Archive tab: only messages archived by the current user
                $q->where(function ($q2) use ($userId) {
                    $q2->whereRaw('(sender_id = ? AND archived_by_sender IS NOT NULL)', [$userId])
                       ->orWhereRaw('(receiver_id = ? AND archived_by_receiver IS NOT NULL)', [$userId]);
                });
            })
            ->groupByRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END', [$userId])
            ->orderByDesc('latest_id');

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $latestIds->having('latest_id', '<', (int) $cursorId);
            }
        }

        $conversationIds = $latestIds->limit($limit + 1)->pluck('latest_id');

        $hasMore = $conversationIds->count() > $limit;
        if ($hasMore) {
            $conversationIds->pop();
        }

        $messages = Message::query()
            ->with([
                'sender:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type,last_active_at',
            ])
            ->whereIn('id', $conversationIds)
            ->orderByDesc('id')
            ->get();

        // Batch-fetch unread counts per partner (avoids N+1)
        $partnerIds = $messages->map(function (Message $msg) use ($userId) {
            return $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;
        })->unique()->values()->all();

        $unreadCounts = [];
        if (!empty($partnerIds)) {
            $rows = DB::table('messages')
                ->selectRaw('sender_id, COUNT(*) as cnt')
                ->where('tenant_id', $tenantId)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->whereIn('sender_id', $partnerIds)
                ->groupBy('sender_id')
                ->get();
            foreach ($rows as $row) {
                $unreadCounts[(int) $row->sender_id] = (int) $row->cnt;
            }
        }

        $items = $messages->map(function (Message $msg) use ($userId, $unreadCounts) {
            $data = $msg->toArray();
            $partnerId = $msg->sender_id === $userId ? $msg->receiver_id : $msg->sender_id;
            $partner = $msg->sender_id === $userId ? $msg->receiver : $msg->sender;
            $data['partner_id'] = $partnerId;
            $data['unread_count'] = $unreadCounts[$partnerId] ?? 0;
            $data['other_user'] = $partner ? [
                'id'         => $partner->id,
                'name'       => $partner->name,
                'first_name' => $partner->first_name,
                'last_name'  => $partner->last_name,
                'avatar_url' => $partner->avatar_url,
                'is_online'  => ($partner->last_active_at && $partner->last_active_at->gt(now()->subMinutes(5))),
            ] : null;
            $data['last_message'] = [
                'id'         => $msg->id,
                'content'    => $msg->body,
                'sender_id'  => $msg->sender_id,
                'created_at' => $msg->created_at?->toISOString(),
                'is_read'    => $msg->is_read,
            ];
            $data['id'] = $partnerId;
            return $data;
        })->all();

        return [
            'items'    => array_values($items),
            'cursor'   => $hasMore && $messages->isNotEmpty() ? base64_encode((string) $messages->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get messages in a conversation with a specific user, with cursor-based pagination.
     *
     * @param int $partnerId The other user's ID
     * @param int $userId The authenticated user's ID
     * @param array $filters Pagination filters (limit, cursor, direction)
     * @return array{items: array, cursor: string|null, has_more: bool}|null Null if partner user not found
     */
    public static function getMessages(int $partnerId, int $userId, array $filters = []): ?array
    {
        // Verify the partner user exists (skip tenant scope — users may be cross-tenant visible)
        $partner = User::withoutGlobalScopes()->find($partnerId);
        if ($partner === null) {
            return null;
        }

        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;
        $direction = $filters['direction'] ?? 'older';

        $query = Message::query()
            ->with([
                'sender:id,first_name,last_name,avatar_url',
                'receiver:id,first_name,last_name,avatar_url',
            ])
            ->betweenUsers($userId, $partnerId)
            ->whereRaw('NOT (sender_id = ? AND is_deleted_sender = 1)', [$userId])
            ->whereRaw('NOT (receiver_id = ? AND is_deleted_receiver = 1)', [$userId]);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                if ($direction === 'newer') {
                    $query->where('id', '>', (int) $cursorId);
                } else {
                    $query->where('id', '<', (int) $cursorId);
                }
            }
        }

        if ($direction === 'newer') {
            $query->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages->pop();
        }

        // Mark messages as read when viewing (older direction or no cursor)
        if ($direction !== 'newer' || $cursor === null) {
            self::markAsRead($partnerId, $userId);
        }

        $items = $messages->map(fn (Message $msg) => $msg->toArray())->all();

        return [
            'items'    => array_values($items),
            'cursor'   => $hasMore && $messages->isNotEmpty() ? base64_encode((string) $messages->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Send a message.
     *
     * Accepts either (senderId, receiverId, data) or (senderId, data) where
     * data contains 'recipient_id'. The second form is used by controllers
     * passing getAllInput() directly.
     *
     * @param int $senderId
     * @param int|array $receiverIdOrData
     * @param array|null $data
     * @return array The created message as an array
     */
    public static function send(int $senderId, int|array $receiverIdOrData, ?array $data = null): array
    {
        if (is_array($receiverIdOrData)) {
            // Controller-style call: send($userId, $allInput)
            $data = $receiverIdOrData;
            $receiverId = (int) ($data['recipient_id'] ?? 0);
        } else {
            // Direct call: send($senderId, $receiverId, $data)
            $receiverId = $receiverIdOrData;
            $data = $data ?? [];
        }

        if ($receiverId <= 0) {
            return ['error' => 'recipient_id is required'];
        }

        $content = trim($data['body'] ?? ($data['content'] ?? ''));

        if (empty($content)) {
            return ['error' => 'Message body is required'];
        }

        $attributes = [
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId,
            'body'           => $content,
            'is_read'        => false,
            'created_at'     => now(),
        ];

        // Pass through contextual messaging fields if provided
        if (!empty($data['context_type'])) {
            $attributes['context_type'] = $data['context_type'];
        }
        if (!empty($data['context_id'])) {
            $attributes['context_id'] = (int) $data['context_id'];
        }

        $message = new Message($attributes);

        $message->save();

        return $message->fresh(['sender', 'receiver'])->toArray();
    }

    /**
     * Mark all messages from a partner as read.
     */
    public static function markAsRead(int $partnerId, int $userId): int
    {
        return Message::query()
            ->where('sender_id', $partnerId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get total unread message count for a user.
     */
    public static function getUnreadCount(int $userId): int
    {
        return Message::query()
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    // -----------------------------------------------------------------
    //  Validation errors
    // -----------------------------------------------------------------

    /** @var array */
    private static array $errors = [];

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    // -----------------------------------------------------------------
    //  Conversation summary
    // -----------------------------------------------------------------

    /**
     * Get a single conversation summary with another user.
     */
    public static function getConversation(int $otherUserId, int $userId): ?array
    {
        self::$errors = [];

        $otherUser = User::withoutGlobalScopes()->find($otherUserId);
        if (! $otherUser) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        $tenantId = app('tenant.id');

        $unreadCount = Message::query()
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();

        $messageCount = Message::query()
            ->betweenUsers($userId, $otherUserId)
            ->count();

        return [
            'id' => $otherUserId,
            'other_user' => [
                'id'         => $otherUser->id,
                'name'       => $otherUser->name ?? trim(($otherUser->first_name ?? '') . ' ' . ($otherUser->last_name ?? '')),
                'first_name' => $otherUser->first_name,
                'last_name'  => $otherUser->last_name,
                'avatar_url' => $otherUser->avatar_url,
                'is_online'  => ($otherUser->last_active_at && $otherUser->last_active_at->gt(now()->subMinutes(5))),
            ],
            'unread_count'  => $unreadCount,
            'message_count' => $messageCount,
        ];
    }

    // -----------------------------------------------------------------
    //  Archive / Unarchive
    // -----------------------------------------------------------------

    /**
     * Archive a conversation with another user.
     *
     * @param string $scope 'self'     — hides from current user's inbox only (restorable).
     *                      'everyone' — hides from both users' inboxes.
     *
     * Uses per-user archival columns so each user can independently archive.
     */
    public static function archiveConversation(int $otherUserId, int $userId, string $scope = 'self'): int
    {
        $tenantId = app('tenant.id');
        $now = now();
        $totalUpdated = 0;

        // Check if archived columns exist
        $hasArchived = DB::getSchemaBuilder()->hasColumn('messages', 'archived_by_sender');

        if (! $hasArchived) {
            // Fall back to hard delete if columns don't exist
            return DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId, $otherUserId) {
                    $q->where(function ($q2) use ($userId, $otherUserId) {
                        $q2->where('sender_id', $userId)->where('receiver_id', $otherUserId);
                    })->orWhere(function ($q2) use ($userId, $otherUserId) {
                        $q2->where('sender_id', $otherUserId)->where('receiver_id', $userId);
                    });
                })
                ->delete();
        }

        if ($scope === 'everyone') {
            // Hide from both users' inboxes in one pass
            $totalUpdated += DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $userId)
                ->where('receiver_id', $otherUserId)
                ->update(['archived_by_sender' => $now, 'archived_by_receiver' => $now]);

            $totalUpdated += DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('sender_id', $otherUserId)
                ->where('receiver_id', $userId)
                ->update(['archived_by_sender' => $now, 'archived_by_receiver' => $now]);

            return $totalUpdated;
        }

        // scope = 'self': archive from current user's view only
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->where('receiver_id', $otherUserId)
            ->whereNull('archived_by_sender')
            ->update(['archived_by_sender' => $now]);

        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->whereNull('archived_by_receiver')
            ->update(['archived_by_receiver' => $now]);

        return $totalUpdated;
    }

    /**
     * Unarchive a conversation with another user.
     */
    public static function unarchiveConversation(int $otherUserId, int $userId): int
    {
        $tenantId = app('tenant.id');

        $hasArchived = DB::getSchemaBuilder()->hasColumn('messages', 'archived_by_sender');
        if (! $hasArchived) {
            return 0;
        }

        $totalUpdated = 0;

        // Unarchive messages where user is the sender
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->where('receiver_id', $otherUserId)
            ->whereNotNull('archived_by_sender')
            ->update(['archived_by_sender' => null]);

        // Unarchive messages where user is the receiver
        $totalUpdated += DB::table('messages')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $otherUserId)
            ->where('receiver_id', $userId)
            ->whereNotNull('archived_by_receiver')
            ->update(['archived_by_receiver' => null]);

        return $totalUpdated;
    }

    // -----------------------------------------------------------------
    //  Edit / Delete messages
    // -----------------------------------------------------------------

    /**
     * Edit a message body.
     */
    public static function editMessage(int $messageId, int $userId, string $newBody): ?array
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return null;
        }

        if ((int) $message->sender_id !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You can only edit your own messages'];
            return null;
        }

        $message->body = $newBody;

        if (in_array('is_edited', $message->getFillable()) || DB::getSchemaBuilder()->hasColumn('messages', 'is_edited')) {
            $message->is_edited = true;
            $message->edited_at = now();
        }

        $message->save();

        return [
            'id'         => $message->id,
            'body'       => $newBody,
            'is_edited'  => true,
            'sender_id'  => (int) $message->sender_id,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * Delete a message (soft delete).
     *
     * @param string $scope 'everyone' — blanks body, shows placeholder to both parties (sender or receiver can do this).
     *                      'self'     — hides message from current user's view only; other party unaffected.
     */
    public static function deleteMessage(int $messageId, int $userId, string $scope = 'everyone'): bool
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return false;
        }

        $isSender   = (int) $message->sender_id   === $userId;
        $isReceiver = (int) $message->receiver_id === $userId;

        if (! $isSender && ! $isReceiver) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not a participant in this conversation'];
            return false;
        }

        $tenantId = app('tenant.id');

        if ($scope === 'self') {
            // Per-user hide: only affects current user's view, body unchanged
            if ($isSender) {
                DB::table('messages')
                    ->where('id', $messageId)
                    ->where('tenant_id', $tenantId)
                    ->update(['is_deleted_sender' => true]);
            } else {
                DB::table('messages')
                    ->where('id', $messageId)
                    ->where('tenant_id', $tenantId)
                    ->update(['is_deleted_receiver' => true]);
            }

            return true;
        }

        // scope = 'everyone': blank body for both parties
        $hasDeleted = DB::getSchemaBuilder()->hasColumn('messages', 'is_deleted');

        if ($hasDeleted) {
            DB::table('messages')
                ->where('id', $messageId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'is_deleted'  => true,
                    'body'        => '[Message deleted]',
                    'deleted_at'  => now(),
                ]);
        } else {
            $message->delete();
        }

        return true;
    }

    // -----------------------------------------------------------------
    //  Reactions
    // -----------------------------------------------------------------

    /**
     * Toggle a reaction emoji on a message.
     *
     * @return bool|null True if added, false if removed, null on error
     */
    public static function toggleReaction(int $messageId, int $userId, string $emoji): ?bool
    {
        self::$errors = [];

        /** @var Message|null $message */
        $message = Message::query()->find($messageId);

        if (! $message) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return null;
        }

        // User must be sender or receiver
        if ((int) $message->sender_id !== $userId && (int) $message->receiver_id !== $userId) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You cannot react to this message'];
            return null;
        }

        $hasReactions = DB::getSchemaBuilder()->hasColumn('messages', 'reactions');
        if (! $hasReactions) {
            self::$errors[] = ['code' => 'FEATURE_UNAVAILABLE', 'message' => 'Reactions feature not yet enabled'];
            return null;
        }

        $reactions = [];
        $rawReactions = DB::table('messages')->where('id', $messageId)->value('reactions');
        if (! empty($rawReactions)) {
            $reactions = json_decode($rawReactions, true) ?? [];
        }

        $userReactions = $reactions['_users'] ?? [];
        $userKey = "{$userId}_{$emoji}";

        $wasAdded = false;
        if (isset($userReactions[$userKey])) {
            // Remove
            unset($userReactions[$userKey]);
            if (isset($reactions[$emoji]) && $reactions[$emoji] > 0) {
                $reactions[$emoji]--;
                if ($reactions[$emoji] <= 0) {
                    unset($reactions[$emoji]);
                }
            }
        } else {
            // Add
            $userReactions[$userKey] = true;
            $reactions[$emoji] = ($reactions[$emoji] ?? 0) + 1;
            $wasAdded = true;
        }

        $reactions['_users'] = $userReactions;

        DB::table('messages')
            ->where('id', $messageId)
            ->where('tenant_id', app('tenant.id'))
            ->update(['reactions' => json_encode($reactions)]);

        return $wasAdded;
    }

    // -----------------------------------------------------------------
    //  Typing indicator
    // -----------------------------------------------------------------

    /**
     * Set typing indicator for a conversation.
     *
     * This is a no-op in Eloquent mode — real-time typing indicators
     * are handled by the frontend via Pusher directly.
     */
    public static function setTypingIndicator(int $recipientId, int $userId, bool $isTyping): bool
    {
        // Typing indicators are broadcast-only (Pusher) — no DB persistence needed.
        // The legacy implementation called RealtimeService::broadcastTyping() which
        // is a Pusher broadcast. In the Laravel app, this should be handled by
        // a dedicated broadcasting event rather than a service method.
        return true;
    }
}
