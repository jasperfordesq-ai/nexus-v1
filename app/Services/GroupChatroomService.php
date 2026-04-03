<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * GroupChatroomService — Native Laravel implementation for group chatroom operations.
 *
 * Manages group chatrooms (channels) and their messages.
 * Tables: group_chatrooms, group_chatroom_messages
 */
class GroupChatroomService
{
    /** @var array<int, array{code: string, message: string, field?: string}> */
    private array $errors = [];

    public function __construct()
    {
    }

    /**
     * Get validation/operation errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all chatrooms for a group.
     *
     * @param string|null $category  Optional category filter (e.g. 'general', 'announcements')
     * @param int|null    $userId    Optional user ID for private-channel visibility check
     */
    public function getChatrooms(int $groupId, ?string $category = null, ?int $userId = null): array
    {
        $tenantId = TenantContext::getId();

        $query = DB::table('group_chatrooms')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if ($category !== null) {
            $query->where('category', $category);
        }

        $chatrooms = $query->get();

        // If a userId is supplied, filter out private chatrooms for non-members
        if ($userId !== null) {
            $isMember = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                $chatrooms = $chatrooms->filter(fn ($c) => ! $c->is_private);
            }
        }

        return $chatrooms->map(fn ($c) => [
            'id'          => (int) $c->id,
            'group_id'    => (int) $c->group_id,
            'name'        => $c->name,
            'description' => $c->description,
            'category'    => $c->category,
            'is_default'  => (bool) $c->is_default,
            'is_private'  => (bool) $c->is_private,
            'created_by'  => (int) $c->created_by,
            'created_at'  => $c->created_at,
        ])->values()->all();
    }

    /**
     * Get a single chatroom by ID.
     */
    public function getById(int $chatroomId): ?array
    {
        $tenantId = TenantContext::getId();

        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            return null;
        }

        return [
            'id'          => (int) $chatroom->id,
            'group_id'    => (int) $chatroom->group_id,
            'name'        => $chatroom->name,
            'description' => $chatroom->description,
            'category'    => $chatroom->category ?? null,
            'is_default'  => (bool) $chatroom->is_default,
            'is_private'  => (bool) ($chatroom->is_private ?? false),
            'created_by'  => (int) $chatroom->created_by,
            'created_at'  => $chatroom->created_at,
        ];
    }

    /**
     * Create a new chatroom in a group.
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Chatroom name is required', 'field' => 'name'];
            return null;
        }

        if (mb_strlen($name) > 100) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Chatroom name must not exceed 100 characters', 'field' => 'name'];
            return null;
        }

        // Verify user is an active member of the group
        $isMember = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a group member to create chatrooms'];
            return null;
        }

        $category = isset($data['category']) ? trim($data['category']) : null;
        if ($category !== null && mb_strlen($category) > 100) {
            $category = mb_substr($category, 0, 100);
        }

        $isPrivate = (bool) ($data['is_private'] ?? false);
        $permissions = isset($data['permissions']) ? json_encode($data['permissions']) : null;

        $id = DB::table('group_chatrooms')->insertGetId([
            'group_id'    => $groupId,
            'tenant_id'   => $tenantId,
            'name'        => $name,
            'description' => trim($data['description'] ?? '') ?: null,
            'category'    => $category ?: null,
            'is_private'  => $isPrivate ? 1 : 0,
            'permissions' => $permissions,
            'created_by'  => $userId,
            'is_default'  => 0,
            'created_at'  => now(),
        ]);

        return (int) $id;
    }

    /**
     * Ensure a default "General" chatroom exists for a group. Returns its ID.
     */
    public function ensureDefaultChatroom(int $groupId, int $userId): ?int
    {
        $tenantId = TenantContext::getId();

        // Check if a default chatroom already exists
        $existing = DB::table('group_chatrooms')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('is_default', 1)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        // Create the default chatroom
        $id = DB::table('group_chatrooms')->insertGetId([
            'group_id'    => $groupId,
            'tenant_id'   => $tenantId,
            'name'        => 'General',
            'description' => 'Default group chatroom',
            'created_by'  => $userId,
            'is_default'  => 1,
            'created_at'  => now(),
        ]);

        return (int) $id;
    }

    /**
     * Delete a chatroom.
     */
    public function delete(int $chatroomId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Chatroom not found'];
            return false;
        }

        if ($chatroom->is_default) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Cannot delete the default chatroom'];
            return false;
        }

        // Check user is admin/owner of the group
        $membership = DB::table('group_members')
            ->where('group_id', $chatroom->group_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        $isAdmin = $membership && in_array($membership->role, ['owner', 'admin']);
        $isCreator = (int) $chatroom->created_by === $userId;

        if (! $isAdmin && ! $isCreator) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the chatroom creator or a group admin can delete this chatroom'];
            return false;
        }

        // Messages are cascade-deleted via FK
        DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return true;
    }

    /**
     * Get messages in a chatroom with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getMessages(int $chatroomId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;
        $tenantId = TenantContext::getId();

        $query = DB::table('group_chatroom_messages as m')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->join('group_chatrooms as c', 'm.chatroom_id', '=', 'c.id')
            ->where('m.chatroom_id', $chatroomId)
            ->where('c.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.chatroom_id',
                'm.user_id',
                'm.body',
                'm.created_at',
                'm.updated_at',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
            ]);

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('m.id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('m.id');

        $messages = $query->limit($limit + 1)->get();
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages->pop();
        }

        $items = $messages->map(fn ($m) => [
            'id'         => (int) $m->id,
            'body'       => $m->body,
            'user_id'    => (int) $m->user_id,
            'author'     => [
                'id'         => (int) $m->user_id,
                'name'       => trim(($m->first_name ?? '') . ' ' . ($m->last_name ?? '')),
                'avatar_url' => $m->avatar_url,
            ],
            'created_at' => $m->created_at,
            'updated_at' => $m->updated_at,
        ])->all();

        return [
            'items'    => $items,
            'cursor'   => $hasMore && $messages->isNotEmpty() ? base64_encode((string) $messages->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Post a message to a chatroom.
     */
    public function postMessage(int $chatroomId, int $userId, string $body): ?int
    {
        $this->errors = [];

        $body = trim($body);
        if (empty($body)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Message body is required', 'field' => 'body'];
            return null;
        }

        $tenantId = TenantContext::getId();

        // Verify chatroom exists
        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Chatroom not found'];
            return null;
        }

        // Verify user is an active member of the group
        $isMember = DB::table('group_members')
            ->where('group_id', $chatroom->group_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a group member to post messages'];
            return null;
        }

        $id = DB::table('group_chatroom_messages')->insertGetId([
            'chatroom_id' => $chatroomId,
            'user_id'     => $userId,
            'body'        => $body,
            'created_at'  => now(),
        ]);

        // Broadcast via Pusher
        try {
            event(new \App\Events\GroupChatroomMessagePosted(
                $tenantId,
                (int) $chatroom->group_id,
                $chatroomId,
                [
                    'id' => (int) $id,
                    'chatroom_id' => $chatroomId,
                    'user_id' => $userId,
                    'body' => $body,
                    'created_at' => now()->toIso8601String(),
                ]
            ));
        } catch (\Throwable $e) {
            // Non-critical — message is saved, broadcast failed
        }

        return (int) $id;
    }

    /**
     * Delete a chatroom message.
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $message = DB::table('group_chatroom_messages as m')
            ->join('group_chatrooms as c', 'm.chatroom_id', '=', 'c.id')
            ->where('m.id', $messageId)
            ->where('c.tenant_id', $tenantId)
            ->select(['m.id', 'm.user_id', 'm.chatroom_id', 'c.group_id'])
            ->first();

        if (! $message) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found'];
            return false;
        }

        // Allow deletion by author or group admin/owner
        $isAuthor = (int) $message->user_id === $userId;

        if (! $isAuthor) {
            $membership = DB::table('group_members')
                ->where('group_id', $message->group_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            $isAdmin = $membership && in_array($membership->role, ['owner', 'admin']);

            if (! $isAdmin) {
                $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the message author or a group admin can delete this message'];
                return false;
            }
        }

        DB::table('group_chatroom_messages')
            ->where('id', $messageId)
            ->delete();

        return true;
    }

    /**
     * Check whether a user can access a chatroom (private-channel visibility).
     * Returns the chatroom row on success, null on failure (with errors set).
     */
    public function assertAccess(int $chatroomId, ?int $userId): ?object
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Chatroom not found'];
            return null;
        }

        if ($chatroom->is_private && $userId !== null) {
            $isMember = DB::table('group_members')
                ->where('group_id', $chatroom->group_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'This channel is private'];
                return null;
            }
        }

        return $chatroom;
    }

    /**
     * Pin a message in a chatroom. Only group admins/owners may pin.
     */
    public function pinMessage(int $chatroomId, int $messageId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        // Verify chatroom
        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Chatroom not found'];
            return false;
        }

        // Verify message belongs to this chatroom
        $message = DB::table('group_chatroom_messages')
            ->where('id', $messageId)
            ->where('chatroom_id', $chatroomId)
            ->first();

        if (! $message) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Message not found in this chatroom'];
            return false;
        }

        // Require group admin/owner
        $membership = DB::table('group_members')
            ->where('group_id', $chatroom->group_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (! $membership || ! in_array($membership->role, ['owner', 'admin'])) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can pin messages'];
            return false;
        }

        // Check if already pinned
        $exists = DB::table('group_chatroom_pinned_messages')
            ->where('chatroom_id', $chatroomId)
            ->where('message_id', $messageId)
            ->exists();

        if ($exists) {
            // Idempotent — already pinned
            return true;
        }

        DB::table('group_chatroom_pinned_messages')->insert([
            'chatroom_id' => $chatroomId,
            'message_id'  => $messageId,
            'pinned_by'   => $userId,
            'tenant_id'   => $tenantId,
            'created_at'  => now(),
        ]);

        return true;
    }

    /**
     * Unpin a message from a chatroom. Only group admins/owners may unpin.
     */
    public function unpinMessage(int $chatroomId, int $messageId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        // Verify chatroom
        $chatroom = DB::table('group_chatrooms')
            ->where('id', $chatroomId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $chatroom) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Chatroom not found'];
            return false;
        }

        // Require group admin/owner
        $membership = DB::table('group_members')
            ->where('group_id', $chatroom->group_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (! $membership || ! in_array($membership->role, ['owner', 'admin'])) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can unpin messages'];
            return false;
        }

        $deleted = DB::table('group_chatroom_pinned_messages')
            ->where('chatroom_id', $chatroomId)
            ->where('message_id', $messageId)
            ->delete();

        if ($deleted === 0) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Pinned message not found'];
            return false;
        }

        return true;
    }

    /**
     * Get all pinned messages for a chatroom.
     */
    public function getPinnedMessages(int $chatroomId): array
    {
        $tenantId = TenantContext::getId();

        $pinned = DB::table('group_chatroom_pinned_messages as p')
            ->join('group_chatroom_messages as m', 'p.message_id', '=', 'm.id')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->join('group_chatrooms as c', 'p.chatroom_id', '=', 'c.id')
            ->where('p.chatroom_id', $chatroomId)
            ->where('c.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.body',
                'm.user_id',
                'm.created_at as message_created_at',
                'u.first_name',
                'u.last_name',
                'u.avatar_url',
                'p.pinned_by',
                'p.created_at as pinned_at',
            ])
            ->orderByDesc('p.created_at')
            ->get();

        return $pinned->map(fn ($row) => [
            'id'         => (int) $row->id,
            'body'       => $row->body,
            'user_id'    => (int) $row->user_id,
            'author'     => [
                'id'         => (int) $row->user_id,
                'name'       => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'avatar_url' => $row->avatar_url,
            ],
            'created_at' => $row->message_created_at,
            'pinned_by'  => (int) $row->pinned_by,
            'pinned_at'  => $row->pinned_at,
        ])->all();
    }
}
