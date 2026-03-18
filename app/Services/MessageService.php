<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MessageService — Laravel DI-based service for messaging operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\MessageService.
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
    public function getConversations(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        // Get the latest message ID per conversation partner
        $latestIds = DB::table('messages')
            ->selectRaw('
                MAX(id) as latest_id,
                CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as partner_id
            ', [$userId])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
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

        $messages = $this->message->newQuery()
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
            $data['partner_id'] = $partnerId;
            $data['unread_count'] = $unreadCounts[$partnerId] ?? 0;
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
    public function getMessages(int $partnerId, int $userId, array $filters = []): ?array
    {
        // Verify the partner user exists
        $partner = User::find($partnerId);
        if ($partner === null) {
            return null;
        }

        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;
        $direction = $filters['direction'] ?? 'older';

        $query = $this->message->newQuery()
            ->with([
                'sender:id,first_name,last_name,avatar_url',
                'receiver:id,first_name,last_name,avatar_url',
            ])
            ->betweenUsers($userId, $partnerId);

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
            $this->markRead($userId, $partnerId);
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
    public function send(int $senderId, int|array $receiverIdOrData, ?array $data = null): array
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

        $body = trim($data['body'] ?? '');
        $voiceUrl = $data['voice_url'] ?? ($data['audio_url'] ?? null);

        if (empty($body) && empty($voiceUrl)) {
            return ['error' => 'Message body or voice message is required'];
        }

        $message = $this->message->newInstance([
            'sender_id'      => $senderId,
            'receiver_id'    => $receiverId,
            'subject'        => $data['subject'] ?? null,
            'body'           => $body,
            'audio_url'      => $voiceUrl,
            'audio_duration' => $data['voice_duration'] ?? ($data['audio_duration'] ?? null),
            'is_read'        => false,
            'created_at'     => now(),
        ]);

        $message->save();

        return $message->fresh(['sender', 'receiver'])->toArray();
    }

    /**
     * Mark all messages from a partner as read.
     */
    public function markRead(int $userId, int $partnerId): int
    {
        return $this->message->newQuery()
            ->where('sender_id', $partnerId)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    /**
     * Get total unread message count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->message->newQuery()
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    // -----------------------------------------------------------------
    //  Delegated to legacy (static methods not yet migrated to Eloquent)
    // -----------------------------------------------------------------

    /**
     * Get a single conversation summary with another user.
     *
     * Delegates to legacy MessageService::getConversation().
     */
    public function getConversation(int $otherUserId, int $userId): ?array
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return null; }
        return \Nexus\Services\MessageService::getConversation($otherUserId, $userId);
    }

    /**
     * Get validation errors from the last operation.
     *
     * Delegates to legacy MessageService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return []; }
        return \Nexus\Services\MessageService::getErrors();
    }

    /**
     * Archive a conversation with another user.
     *
     * Delegates to legacy MessageService::archiveConversation().
     */
    public function archiveConversation(int $otherUserId, int $userId): int
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return 0; }
        return \Nexus\Services\MessageService::archiveConversation($otherUserId, $userId);
    }

    /**
     * Unarchive a conversation with another user.
     *
     * Delegates to legacy MessageService::unarchiveConversation().
     */
    public function unarchiveConversation(int $otherUserId, int $userId): int
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return 0; }
        return \Nexus\Services\MessageService::unarchiveConversation($otherUserId, $userId);
    }

    /**
     * Edit a message body.
     *
     * Delegates to legacy MessageService::editMessage().
     */
    public function editMessage(int $messageId, int $userId, string $newBody): ?array
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return null; }
        return \Nexus\Services\MessageService::editMessage($messageId, $userId, $newBody);
    }

    /**
     * Delete a message.
     *
     * Delegates to legacy MessageService::deleteMessage().
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return false; }
        return \Nexus\Services\MessageService::deleteMessage($messageId, $userId);
    }

    /**
     * Toggle a reaction emoji on a message.
     *
     * Delegates to legacy MessageService::toggleReaction().
     */
    public function toggleReaction(int $messageId, int $userId, string $emoji): ?bool
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return null; }
        return \Nexus\Services\MessageService::toggleReaction($messageId, $userId, $emoji);
    }

    /**
     * Set typing indicator for a conversation.
     *
     * Delegates to legacy MessageService::setTypingIndicator().
     */
    public function setTypingIndicator(int $recipientId, int $userId, bool $isTyping): bool
    {
        if (!class_exists('\Nexus\Services\MessageService')) { return false; }
        return \Nexus\Services\MessageService::setTypingIndicator($recipientId, $userId, $isTyping);
    }
}
