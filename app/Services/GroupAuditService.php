<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * GroupAuditService — Comprehensive audit logging for group actions.
 *
 * Tracks group lifecycle events (create, update, delete, feature),
 * member management (join, leave, kick, ban), and content moderation
 * (discussions, posts). All operations are tenant-scoped.
 *
 * Uses the legacy Database class (PDO wrapper) for direct SQL queries.
 */
class GroupAuditService
{
    // ─── Group action constants ─────────────────────────────────────
    public const ACTION_GROUP_CREATED = 'group_created';
    public const ACTION_GROUP_UPDATED = 'group_updated';
    public const ACTION_GROUP_DELETED = 'group_deleted';
    public const ACTION_GROUP_FEATURED = 'group_featured';

    // ─── Member action constants ────────────────────────────────────
    public const ACTION_MEMBER_JOINED = 'member_joined';
    public const ACTION_MEMBER_LEFT = 'member_left';
    public const ACTION_MEMBER_KICKED = 'member_kicked';
    public const ACTION_MEMBER_BANNED = 'member_banned';

    // ─── Content action constants ───────────────────────────────────
    public const ACTION_DISCUSSION_CREATED = 'discussion_created';
    public const ACTION_POST_CREATED = 'post_created';
    public const ACTION_POST_MODERATED = 'post_moderated';

    /**
     * Log an audit entry for a group action.
     *
     * Inserts a row into group_audit_log with the current tenant context,
     * optional JSON details, and the client IP address.
     *
     * @param string $action  One of the ACTION_* constants
     * @param int    $groupId The group this action relates to
     * @param int    $userId  The user who performed the action
     * @param array  $details Optional key-value details (stored as JSON)
     *
     * @return int|string The inserted row ID (via PDO::lastInsertId)
     */
    public static function log(string $action, int $groupId, int $userId, array $details = []): int|string
    {
        $tenantId = TenantContext::getId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $detailsJson = !empty($details) ? json_encode($details) : null;

        Database::query(
            "INSERT INTO group_audit_log (tenant_id, group_id, user_id, action, details, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, $groupId, $userId, $action, $detailsJson, $ipAddress]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Get the audit log for a specific group.
     *
     * Returns all audit entries for the group within the current tenant,
     * optionally filtered by action type. Results are ordered newest-first.
     *
     * @param int   $groupId The group to retrieve logs for
     * @param array $filters Optional filters: ['action' => 'group_updated']
     *
     * @return array Array of associative arrays (one per log entry)
     */
    public static function getGroupLog(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $params = [$groupId, $tenantId];

        $sql = "SELECT * FROM group_audit_log WHERE group_id = ? AND tenant_id = ?";

        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = Database::query($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get a specific user's activity within a group.
     *
     * Returns all audit entries for the given user in the given group,
     * scoped to the current tenant. Results are ordered newest-first.
     *
     * @param int $groupId The group to query
     * @param int $userId  The user whose activity to retrieve
     *
     * @return array Array of associative arrays (one per log entry)
     */
    public static function getUserGroupActivity(int $groupId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM group_audit_log
             WHERE group_id = ? AND user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$groupId, $userId, $tenantId]
        );

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get an activity summary for a group.
     *
     * Returns aggregate statistics including total action count,
     * a breakdown of actions by type, and the top 5 most active users.
     *
     * @param int $groupId The group to summarize
     *
     * @return array{total_actions: int, actions_by_type: array, most_active_users: array}
     */
    public static function getActivitySummary(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        // Total actions
        $stmt = Database::query(
            "SELECT COUNT(*) as total FROM group_audit_log WHERE group_id = ? AND tenant_id = ?",
            [$groupId, $tenantId]
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $totalActions = (int) ($row['total'] ?? 0);

        // Actions by type
        $stmt = Database::query(
            "SELECT action, COUNT(*) as count FROM group_audit_log
             WHERE group_id = ? AND tenant_id = ?
             GROUP BY action
             ORDER BY count DESC",
            [$groupId, $tenantId]
        );
        $actionsByType = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Most active users (top 5)
        $stmt = Database::query(
            "SELECT user_id, COUNT(*) as action_count FROM group_audit_log
             WHERE group_id = ? AND tenant_id = ?
             GROUP BY user_id
             ORDER BY action_count DESC
             LIMIT 5",
            [$groupId, $tenantId]
        );
        $mostActiveUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'total_actions'     => $totalActions,
            'actions_by_type'   => $actionsByType,
            'most_active_users' => $mostActiveUsers,
        ];
    }
}
