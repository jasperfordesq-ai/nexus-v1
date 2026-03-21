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
     */
    public function getChatrooms(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $chatrooms = DB::table('group_chatrooms')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $chatrooms->map(fn ($c) => [
            'id'          => (int) $c->id,
            'group_id'    => (int) $c->group_id,
            'name'        => $c->name,
            'description' => $c->description,
            'is_default'  => (bool) $c->is_default,
            'created_by'  => (int) $c->created_by,
            'created_at'  => $c->created_at,
        ])->all();
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
            'is_default'  => (bool) $chatroom->is_default,
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

        $id = DB::table('group_chatrooms')->insertGetId([
            'group_id'    => $groupId,
            'tenant_id'   => $tenantId,
            'name'        => $name,
            'description' => trim($data['description'] ?? '') ?: null,
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

        $query = DB::table('group_chatroom_messages as m')
            ->join('users as u', 'm.user_id', '=', 'u.id')
            ->where('m.chatroom_id', $chatroomId)
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
}
