<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;

/**
 * GroupAnnouncementService - Announcements within groups
 *
 * Group admins can post announcements visible to all members.
 * Announcements can be pinned (appear at top) and auto-expire.
 *
 * Operations:
 * - CRUD for announcements (admin-only create/update/delete)
 * - Pin/unpin announcements
 * - Auto-expire old announcements via expires_at
 * - List with pagination (pinned first, then by priority/date)
 */
class GroupAnnouncementService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * List announcements for a group (members can view)
     *
     * @param int $groupId
     * @param int $userId Must be a member
     * @param array $filters ['cursor', 'limit', 'include_expired']
     * @return array|null Paginated announcements or null on error
     */
    public static function list(int $groupId, int $userId, array $filters = []): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view announcements'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $cursor = $filters['cursor'] ?? null;
        $includeExpired = $filters['include_expired'] ?? false;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        $sql = "
            SELECT ga.*, u.name as author_name, u.avatar_url as author_avatar
            FROM group_announcements ga
            JOIN users u ON ga.created_by = u.id
            WHERE ga.group_id = ? AND ga.tenant_id = ?
        ";
        $params = [$groupId, $tenantId];

        if (!$includeExpired) {
            $sql .= " AND (ga.expires_at IS NULL OR ga.expires_at > NOW())";
        }

        if ($cursorId) {
            $sql .= " AND ga.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY ga.is_pinned DESC, ga.priority DESC, ga.created_at DESC, ga.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $announcements = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($announcements) > $limit;
        if ($hasMore) {
            array_pop($announcements);
        }

        $items = [];
        $lastId = null;

        foreach ($announcements as $a) {
            $lastId = $a['id'];
            $items[] = self::formatAnnouncement($a);
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single announcement
     */
    public static function getById(int $groupId, int $announcementId, int $userId): ?array
    {
        self::$errors = [];

        if (!Group::isMember($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a member to view announcements'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT ga.*, u.name as author_name, u.avatar_url as author_avatar
            FROM group_announcements ga
            JOIN users u ON ga.created_by = u.id
            WHERE ga.id = ? AND ga.group_id = ? AND ga.tenant_id = ?
        ");
        $stmt->execute([$announcementId, $groupId, $tenantId]);
        $announcement = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$announcement) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return null;
        }

        return self::formatAnnouncement($announcement);
    }

    /**
     * Create an announcement (admin only)
     */
    public static function create(int $groupId, int $userId, array $data): ?array
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can create announcements'];
            return null;
        }

        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');

        if (empty($title)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
            return null;
        }
        if (strlen($title) > 255) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title must be 255 characters or less', 'field' => 'title'];
            return null;
        }
        if (empty($content)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $isPinned = (bool)($data['is_pinned'] ?? false);
        $priority = max(0, (int)($data['priority'] ?? 0));
        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime($data['expires_at']));
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO group_announcements (group_id, tenant_id, title, content, is_pinned, priority, created_by, created_at, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$groupId, $tenantId, $title, $content, $isPinned ? 1 : 0, $priority, $userId, $expiresAt]);
            $id = (int)$db->lastInsertId();

            return self::getById($groupId, $id, $userId);
        } catch (\Exception $e) {
            error_log("GroupAnnouncementService::create error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create announcement'];
            return null;
        }
    }

    /**
     * Update an announcement (admin only)
     */
    public static function update(int $groupId, int $announcementId, int $userId, array $data): ?array
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can update announcements'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        // Verify announcement exists
        $stmt = $db->prepare("SELECT id FROM group_announcements WHERE id = ? AND group_id = ? AND tenant_id = ?");
        $stmt->execute([$announcementId, $groupId, $tenantId]);
        if (!$stmt->fetch()) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return null;
        }

        $updates = [];
        $params = [];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if (empty($title)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
                return null;
            }
            $updates[] = "title = ?";
            $params[] = $title;
        }
        if (array_key_exists('content', $data)) {
            $content = trim($data['content']);
            if (empty($content)) {
                self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content is required', 'field' => 'content'];
                return null;
            }
            $updates[] = "content = ?";
            $params[] = $content;
        }
        if (array_key_exists('is_pinned', $data)) {
            $updates[] = "is_pinned = ?";
            $params[] = (bool)$data['is_pinned'] ? 1 : 0;
        }
        if (array_key_exists('priority', $data)) {
            $updates[] = "priority = ?";
            $params[] = max(0, (int)$data['priority']);
        }
        if (array_key_exists('expires_at', $data)) {
            $updates[] = "expires_at = ?";
            $params[] = $data['expires_at'] ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : null;
        }

        if (empty($updates)) {
            return self::getById($groupId, $announcementId, $userId);
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $announcementId;
        $params[] = $tenantId;

        try {
            $db->prepare("UPDATE group_announcements SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?")->execute($params);
            return self::getById($groupId, $announcementId, $userId);
        } catch (\Exception $e) {
            error_log("GroupAnnouncementService::update error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to update announcement'];
            return null;
        }
    }

    /**
     * Delete an announcement (admin only)
     */
    public static function delete(int $groupId, int $announcementId, int $userId): bool
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can delete announcements'];
            return false;
        }

        $tenantId = TenantContext::getId();

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM group_announcements WHERE id = ? AND group_id = ? AND tenant_id = ?");
            $stmt->execute([$announcementId, $groupId, $tenantId]);

            if ($stmt->rowCount() === 0) {
                self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("GroupAnnouncementService::delete error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete announcement'];
            return false;
        }
    }

    /**
     * Toggle pin status of an announcement (admin only)
     */
    public static function togglePin(int $groupId, int $announcementId, int $userId): ?array
    {
        self::$errors = [];

        if (!Group::isAdmin($groupId, $userId)) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only group admins can pin/unpin announcements'];
            return null;
        }

        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT is_pinned FROM group_announcements WHERE id = ? AND group_id = ? AND tenant_id = ?");
        $stmt->execute([$announcementId, $groupId, $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Announcement not found'];
            return null;
        }

        $newPinned = (int)$row['is_pinned'] === 1 ? 0 : 1;

        try {
            $db->prepare("UPDATE group_announcements SET is_pinned = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?")
                ->execute([$newPinned, $announcementId, $tenantId]);

            return self::getById($groupId, $announcementId, $userId);
        } catch (\Exception $e) {
            error_log("GroupAnnouncementService::togglePin error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to toggle pin'];
            return null;
        }
    }

    /**
     * Clean up expired announcements for a tenant
     */
    public static function cleanupExpired(): int
    {
        $tenantId = TenantContext::getId();
        try {
            $stmt = Database::query(
                "DELETE FROM group_announcements WHERE tenant_id = ? AND expires_at IS NOT NULL AND expires_at < NOW()",
                [$tenantId]
            );
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("GroupAnnouncementService::cleanupExpired error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Format announcement for API response
     */
    private static function formatAnnouncement(array $a): array
    {
        $isExpired = !empty($a['expires_at']) && strtotime($a['expires_at']) < time();

        return [
            'id' => (int)$a['id'],
            'title' => $a['title'],
            'content' => $a['content'],
            'is_pinned' => (bool)$a['is_pinned'],
            'priority' => (int)$a['priority'],
            'is_expired' => $isExpired,
            'author' => [
                'id' => (int)$a['created_by'],
                'name' => $a['author_name'] ?? null,
                'avatar_url' => $a['author_avatar'] ?? null,
            ],
            'created_at' => $a['created_at'],
            'updated_at' => $a['updated_at'] ?? null,
            'expires_at' => $a['expires_at'] ?? null,
        ];
    }
}
