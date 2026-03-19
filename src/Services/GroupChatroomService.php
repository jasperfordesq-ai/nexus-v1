<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * GroupChatroomService - Topic-specific chat channels within groups
 *
 * Extends groups with multiple discussion channels (chatrooms). Each group
 * can have a "General" default channel plus additional topic-specific channels.
 *
 * @package Nexus\Services
 */
class GroupChatroomService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Get all chatrooms for a group
     */
    public static function getChatrooms(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT gc.*, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM group_chatroom_messages gcm WHERE gcm.chatroom_id = gc.id) AS message_count
             FROM group_chatrooms gc
             LEFT JOIN users u ON gc.created_by = u.id
             WHERE gc.group_id = ? AND gc.tenant_id = ?
             ORDER BY gc.is_default DESC, gc.created_at ASC",
            [$groupId, $tenantId]
        )->fetchAll();
    }

    /**
     * Get a single chatroom by ID
     */
    public static function getById(int $chatroomId): ?array
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT gc.*, u.first_name, u.last_name
             FROM group_chatrooms gc
             LEFT JOIN users u ON gc.created_by = u.id
             WHERE gc.id = ? AND gc.tenant_id = ?",
            [$chatroomId, $tenantId]
        )->fetch();

        return $row ?: null;
    }

    /**
     * Create a chatroom in a group
     *
     * @return int|null Chatroom ID
     */
    public static function create(int $groupId, int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify user is a member of the group
        if (!self::isGroupMember($groupId, $userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You must be a group member to create chatrooms');
            return null;
        }

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Chatroom name is required', 'name');
            return null;
        }

        if (mb_strlen($name) > 100) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Name must be 100 characters or fewer', 'name');
            return null;
        }

        $description = !empty($data['description']) ? trim($data['description']) : null;

        try {
            Database::query(
                "INSERT INTO group_chatrooms (group_id, tenant_id, name, description, created_by) VALUES (?, ?, ?, ?, ?)",
                [$groupId, $tenantId, $name, $description, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Chatroom creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create chatroom');
            return null;
        }
    }

    /**
     * Create the default "General" chatroom for a group (idempotent)
     */
    public static function ensureDefaultChatroom(int $groupId, int $userId): ?int
    {
        $tenantId = TenantContext::getId();

        $existing = Database::query(
            "SELECT id FROM group_chatrooms WHERE group_id = ? AND tenant_id = ? AND is_default = 1",
            [$groupId, $tenantId]
        )->fetch();

        if ($existing) {
            return (int)$existing['id'];
        }

        try {
            Database::query(
                "INSERT INTO group_chatrooms (group_id, tenant_id, name, description, created_by, is_default) VALUES (?, ?, 'General', 'Default discussion channel', ?, 1)",
                [$groupId, $tenantId, $userId]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Default chatroom creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a chatroom (not the default one)
     */
    public static function delete(int $chatroomId, int $userId): bool
    {
        self::clearErrors();

        $chatroom = self::getById($chatroomId);
        if (!$chatroom) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Chatroom not found');
            return false;
        }

        if ((int)($chatroom['is_default'] ?? 0) === 1) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'Cannot delete the default chatroom');
            return false;
        }

        // Must be group admin/owner or chatroom creator
        $isCreator = (int)$chatroom['created_by'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isCreator && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the creator or an admin can delete chatrooms');
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            // FK cascade deletes messages
            Database::query(
                "DELETE FROM group_chatrooms WHERE id = ? AND tenant_id = ?",
                [$chatroomId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Chatroom deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete chatroom');
            return false;
        }
    }

    /**
     * Get messages in a chatroom with cursor-based pagination
     */
    public static function getMessages(int $chatroomId, array $filters = []): array
    {
        $limit = $filters['limit'] ?? 50;
        $cursor = $filters['cursor'] ?? null;

        $chatroom = self::getById($chatroomId);
        if (!$chatroom) {
            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        $params = [$chatroomId];
        $where = ["gcm.chatroom_id = ?"];

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "gcm.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $items = Database::query(
            "SELECT gcm.*, u.first_name, u.last_name, u.avatar_url
             FROM group_chatroom_messages gcm
             LEFT JOIN users u ON gcm.user_id = u.id
             WHERE {$whereClause}
             ORDER BY gcm.created_at DESC, gcm.id DESC
             LIMIT ?",
            $params
        )->fetchAll();

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format
        foreach ($items as &$item) {
            $item['author'] = [
                'id' => (int)$item['user_id'],
                'name' => trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')),
                'avatar_url' => $item['avatar_url'] ?? null,
            ];
            unset($item['first_name'], $item['last_name'], $item['avatar_url']);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Post a message to a chatroom
     *
     * @return int|null Message ID
     */
    public static function postMessage(int $chatroomId, int $userId, string $body): ?int
    {
        self::clearErrors();

        $body = trim($body);
        if (empty($body)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Message body is required', 'body');
            return null;
        }

        $chatroom = self::getById($chatroomId);
        if (!$chatroom) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Chatroom not found');
            return null;
        }

        // User must be group member
        $groupId = (int)$chatroom['group_id'];
        if (!self::isGroupMember($groupId, $userId)) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You must be a group member to post messages');
            return null;
        }

        try {
            Database::query(
                "INSERT INTO group_chatroom_messages (chatroom_id, user_id, body) VALUES (?, ?, ?)",
                [$chatroomId, $userId, $body]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("Chatroom message creation failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to post message');
            return null;
        }
    }

    /**
     * Delete a message
     */
    public static function deleteMessage(int $messageId, int $userId): bool
    {
        self::clearErrors();

        $message = Database::query(
            "SELECT gcm.*, gc.tenant_id
             FROM group_chatroom_messages gcm
             INNER JOIN group_chatrooms gc ON gcm.chatroom_id = gc.id
             WHERE gcm.id = ?",
            [$messageId]
        )->fetch();

        if (!$message) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Message not found');
            return false;
        }

        $tenantId = TenantContext::getId();
        if ((int)($message['tenant_id'] ?? 0) !== $tenantId) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Message not found');
            return false;
        }

        $isOwner = (int)$message['user_id'] === $userId;
        $isAdmin = self::isAdmin($userId);

        if (!$isOwner && !$isAdmin) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the author or an admin can delete messages');
            return false;
        }

        try {
            Database::query("DELETE FROM group_chatroom_messages WHERE id = ? AND tenant_id = ?", [$messageId, $tenantId]);
            return true;
        } catch (\Throwable $e) {
            error_log("Chatroom message deletion failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete message');
            return false;
        }
    }

    private static function isGroupMember(int $groupId, int $userId): bool
    {
        $result = Database::query(
            "SELECT id FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $userId]
        )->fetch();

        return !empty($result);
    }

    private static function isAdmin(int $userId): bool
    {
        $user = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        )->fetch();

        return $user && in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin']);
    }
}
