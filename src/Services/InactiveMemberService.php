<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * InactiveMemberService
 *
 * Detects and flags members who have been inactive across all engagement
 * dimensions: login, transactions, posts, and event attendance.
 *
 * Uses the `member_activity_flags` table to track and persist inactivity data.
 *
 * Flag types:
 * - inactive: No activity for N days (configurable threshold)
 * - dormant: No activity for 2x threshold (seriously disengaged)
 * - at_risk: Recently decreased activity (was active, now slowing)
 *
 * All methods are tenant-scoped.
 */
class InactiveMemberService
{
    /**
     * Detect inactive members and update flags
     *
     * Scans all active users, determines their last activity across multiple
     * dimensions, and creates/updates flags in member_activity_flags.
     *
     * @param int $tenantId
     * @param int $thresholdDays Number of days of inactivity to flag
     * @return array Summary of detection results
     */
    public static function detectInactive(int $tenantId, int $thresholdDays = 90): array
    {
        self::ensureTableExists();

        $cutoffInactive = date('Y-m-d H:i:s', strtotime("-{$thresholdDays} days"));
        $cutoffDormant = date('Y-m-d H:i:s', strtotime("-" . ($thresholdDays * 2) . " days"));

        // Get all active users with their last activity timestamps
        $stmt = Database::query(
            "SELECT u.id as user_id,
                    u.last_login_at,
                    u.created_at as member_since,
                    (SELECT MAX(t.created_at) FROM transactions t
                     WHERE (t.sender_id = u.id OR t.receiver_id = u.id) AND t.tenant_id = ? AND t.status = 'completed') as last_transaction_at,
                    (SELECT MAX(fp.created_at) FROM feed_posts fp
                     WHERE fp.user_id = u.id AND fp.tenant_id = ?) as last_post_at,
                    (SELECT MAX(er.created_at) FROM event_rsvps er
                     WHERE er.user_id = u.id AND er.tenant_id = ? AND er.status = 'going') as last_event_at
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'",
            [$tenantId, $tenantId, $tenantId, $tenantId]
        );

        $flagged = 0;
        $dormant = 0;
        $resolved = 0;

        while ($user = $stmt->fetch()) {
            $userId = (int) $user['user_id'];

            // Determine last activity across all dimensions
            $lastActivities = array_filter([
                $user['last_login_at'],
                $user['last_transaction_at'],
                $user['last_post_at'],
                $user['last_event_at'],
            ]);

            $lastActivity = !empty($lastActivities) ? max($lastActivities) : $user['member_since'];

            // Determine flag type
            if ($lastActivity < $cutoffDormant) {
                $flagType = 'dormant';
                $dormant++;
                $flagged++;
            } elseif ($lastActivity < $cutoffInactive) {
                $flagType = 'inactive';
                $flagged++;
            } else {
                // User is active — resolve any existing flag
                self::resolveFlag($userId, $tenantId);
                $resolved++;
                continue;
            }

            // Upsert flag
            self::upsertFlag($userId, $tenantId, [
                'last_activity_at' => $lastActivity,
                'last_login_at' => $user['last_login_at'],
                'last_transaction_at' => $user['last_transaction_at'],
                'last_post_at' => $user['last_post_at'],
                'last_event_at' => $user['last_event_at'],
                'flag_type' => $flagType,
            ]);
        }

        return [
            'tenant_id' => $tenantId,
            'threshold_days' => $thresholdDays,
            'flagged_inactive' => $flagged - $dormant,
            'flagged_dormant' => $dormant,
            'total_flagged' => $flagged,
            'resolved' => $resolved,
            'run_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get inactive members list with filtering
     *
     * @param int $tenantId
     * @param int $days Minimum days of inactivity
     * @param string|null $flagType Filter by flag type: 'inactive', 'dormant', 'at_risk', or null for all
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getInactiveMembers(int $tenantId, int $days = 90, ?string $flagType = null, int $limit = 50, int $offset = 0): array
    {
        self::ensureTableExists();

        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Build conditions
        $conditions = ['f.tenant_id = ?', 'f.resolved_at IS NULL'];
        $params = [$tenantId];

        if ($flagType && in_array($flagType, ['inactive', 'dormant', 'at_risk'], true)) {
            $conditions[] = 'f.flag_type = ?';
            $params[] = $flagType;
        }

        if ($days > 0) {
            $conditions[] = '(f.last_activity_at IS NULL OR f.last_activity_at < ?)';
            $params[] = $cutoff;
        }

        $where = implode(' AND ', $conditions);

        // Count
        $total = (int) Database::query(
            "SELECT COUNT(*) FROM member_activity_flags f WHERE {$where}",
            $params
        )->fetchColumn();

        // Fetch with user details
        $queryParams = array_merge($params, [$limit, $offset]);
        $stmt = Database::query(
            "SELECT f.*, u.first_name, u.last_name, u.email, u.avatar_url, u.created_at as member_since
             FROM member_activity_flags f
             JOIN users u ON f.user_id = u.id
             WHERE {$where}
             ORDER BY f.last_activity_at ASC
             LIMIT ? OFFSET ?",
            $queryParams
        );

        $members = [];
        while ($row = $stmt->fetch()) {
            $members[] = [
                'id' => (int) $row['user_id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'profile_image_url' => $row['avatar_url'],
                'flag_type' => $row['flag_type'],
                'last_activity_at' => $row['last_activity_at'],
                'last_login_at' => $row['last_login_at'],
                'last_transaction_at' => $row['last_transaction_at'],
                'last_post_at' => $row['last_post_at'],
                'last_event_at' => $row['last_event_at'],
                'flagged_at' => $row['flagged_at'],
                'notified_at' => $row['notified_at'],
                'member_since' => $row['member_since'],
                'days_inactive' => $row['last_activity_at']
                    ? (int) ((time() - strtotime($row['last_activity_at'])) / 86400)
                    : null,
            ];
        }

        return [
            'members' => $members,
            'total' => $total,
            'threshold_days' => $days,
        ];
    }

    /**
     * Get inactivity summary statistics
     *
     * @param int $tenantId
     * @return array
     */
    public static function getInactivityStats(int $tenantId): array
    {
        self::ensureTableExists();

        $stats = Database::query(
            "SELECT
                COUNT(*) as total_flagged,
                SUM(CASE WHEN flag_type = 'inactive' THEN 1 ELSE 0 END) as inactive_count,
                SUM(CASE WHEN flag_type = 'dormant' THEN 1 ELSE 0 END) as dormant_count,
                SUM(CASE WHEN flag_type = 'at_risk' THEN 1 ELSE 0 END) as at_risk_count,
                SUM(CASE WHEN notified_at IS NOT NULL THEN 1 ELSE 0 END) as notified_count
             FROM member_activity_flags
             WHERE tenant_id = ? AND resolved_at IS NULL",
            [$tenantId]
        )->fetch();

        $totalActive = (int) Database::query(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        )->fetchColumn();

        $totalFlagged = (int) ($stats['total_flagged'] ?? 0);

        return [
            'total_active_members' => $totalActive,
            'total_flagged' => $totalFlagged,
            'inactive_count' => (int) ($stats['inactive_count'] ?? 0),
            'dormant_count' => (int) ($stats['dormant_count'] ?? 0),
            'at_risk_count' => (int) ($stats['at_risk_count'] ?? 0),
            'notified_count' => (int) ($stats['notified_count'] ?? 0),
            'inactivity_rate' => $totalActive > 0 ? round($totalFlagged / $totalActive, 3) : 0,
        ];
    }

    /**
     * Mark inactive members as notified
     *
     * @param int $tenantId
     * @param array $userIds
     * @return int Number of flags updated
     */
    public static function markNotified(int $tenantId, array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge([$tenantId], $userIds);

        $stmt = Database::query(
            "UPDATE member_activity_flags SET notified_at = NOW()
             WHERE tenant_id = ? AND user_id IN ({$placeholders}) AND resolved_at IS NULL",
            $params
        );

        return $stmt->rowCount();
    }

    /**
     * Upsert a member activity flag
     */
    private static function upsertFlag(int $userId, int $tenantId, array $data): void
    {
        try {
            Database::query(
                "INSERT INTO member_activity_flags
                    (user_id, tenant_id, last_activity_at, last_login_at, last_transaction_at, last_post_at, last_event_at, flag_type, flagged_at, resolved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)
                 ON DUPLICATE KEY UPDATE
                    last_activity_at = VALUES(last_activity_at),
                    last_login_at = VALUES(last_login_at),
                    last_transaction_at = VALUES(last_transaction_at),
                    last_post_at = VALUES(last_post_at),
                    last_event_at = VALUES(last_event_at),
                    flag_type = VALUES(flag_type),
                    flagged_at = CASE WHEN resolved_at IS NOT NULL THEN NOW() ELSE flagged_at END,
                    resolved_at = NULL",
                [
                    $userId,
                    $tenantId,
                    $data['last_activity_at'],
                    $data['last_login_at'],
                    $data['last_transaction_at'],
                    $data['last_post_at'],
                    $data['last_event_at'],
                    $data['flag_type'],
                ]
            );
        } catch (\Exception $e) {
            error_log("InactiveMemberService::upsertFlag failed for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Resolve a flag (user became active again)
     */
    private static function resolveFlag(int $userId, int $tenantId): void
    {
        try {
            Database::query(
                "UPDATE member_activity_flags SET resolved_at = NOW()
                 WHERE user_id = ? AND tenant_id = ? AND resolved_at IS NULL",
                [$userId, $tenantId]
            );
        } catch (\Exception $e) {
            // Table may not exist yet; silently ignore
        }
    }

    /**
     * Ensure the member_activity_flags table exists
     */
    private static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            Database::query("SELECT 1 FROM member_activity_flags LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS `member_activity_flags` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `tenant_id` INT NOT NULL,
                    `last_activity_at` TIMESTAMP NULL DEFAULT NULL,
                    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
                    `last_transaction_at` TIMESTAMP NULL DEFAULT NULL,
                    `last_post_at` TIMESTAMP NULL DEFAULT NULL,
                    `last_event_at` TIMESTAMP NULL DEFAULT NULL,
                    `flag_type` ENUM('inactive','dormant','at_risk') NOT NULL DEFAULT 'inactive',
                    `flagged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `notified_at` TIMESTAMP NULL DEFAULT NULL,
                    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
                    UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
                    INDEX `idx_tenant_flag` (`tenant_id`, `flag_type`),
                    INDEX `idx_tenant_last_activity` (`tenant_id`, `last_activity_at`),
                    INDEX `idx_flagged_at` (`tenant_id`, `flagged_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
