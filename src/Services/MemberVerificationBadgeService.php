<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * MemberVerificationBadgeService - Verification badge system
 *
 * Badge types:
 * - email_verified: Email address confirmed
 * - phone_verified: Phone number confirmed
 * - id_verified: Government ID verified
 * - dbs_checked: DBS/background check passed
 * - admin_verified: Manually verified by admin
 *
 * Provides:
 * - Grant/revoke badges (admin)
 * - Get badges for a user
 * - Batch get badges for multiple users
 * - Check specific badge type
 */
class MemberVerificationBadgeService
{
    private static array $errors = [];

    public const BADGE_TYPES = [
        'email_verified',
        'phone_verified',
        'id_verified',
        'dbs_checked',
        'admin_verified',
    ];

    public const BADGE_LABELS = [
        'email_verified' => 'Email Verified',
        'phone_verified' => 'Phone Verified',
        'id_verified' => 'ID Verified',
        'dbs_checked' => 'DBS Checked',
        'admin_verified' => 'Admin Verified',
    ];

    public const BADGE_ICONS = [
        'email_verified' => 'mail-check',
        'phone_verified' => 'phone-check',
        'id_verified' => 'shield-check',
        'dbs_checked' => 'badge-check',
        'admin_verified' => 'user-check',
    ];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Grant a verification badge to a user (admin action)
     *
     * @param int $userId User receiving the badge
     * @param string $badgeType One of BADGE_TYPES
     * @param int $adminId Admin granting the badge
     * @param string|null $note Verification note
     * @param string|null $expiresAt Optional expiry date (Y-m-d H:i:s)
     * @return int|null Badge ID
     */
    public static function grantBadge(int $userId, string $badgeType, int $adminId, ?string $note = null, ?string $expiresAt = null): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        if (!in_array($badgeType, self::BADGE_TYPES, true)) {
            self::$errors[] = ['code' => 'INVALID_TYPE', 'message' => 'Invalid badge type: ' . $badgeType, 'field' => 'badge_type'];
            return null;
        }

        // Check user exists in tenant
        $user = Database::query(
            "SELECT id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        // Upsert: if badge exists and was revoked, re-grant it
        $existing = Database::query(
            "SELECT id, revoked_at FROM member_verification_badges WHERE user_id = ? AND tenant_id = ? AND badge_type = ?",
            [$userId, $tenantId, $badgeType]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['revoked_at'] === null) {
                // Already active
                self::$errors[] = ['code' => 'ALREADY_GRANTED', 'message' => 'Badge already active'];
                return (int)$existing['id'];
            }

            // Re-grant
            Database::query(
                "UPDATE member_verification_badges
                 SET verified_by = ?, verification_note = ?, granted_at = NOW(), expires_at = ?, revoked_at = NULL
                 WHERE id = ? AND tenant_id = ?",
                [$adminId, $note, $expiresAt, $existing['id'], $tenantId]
            );

            $badgeId = (int)$existing['id'];
        } else {
            Database::query(
                "INSERT INTO member_verification_badges (user_id, tenant_id, badge_type, verified_by, verification_note, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $tenantId, $badgeType, $adminId, $note, $expiresAt]
            );

            $badgeId = (int)Database::lastInsertId();
        }

        // Send notification
        try {
            $label = self::BADGE_LABELS[$badgeType] ?? $badgeType;
            Notification::create(
                $userId,
                "You have been granted the '{$label}' verification badge",
                '/settings',
                'verification'
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        return $badgeId;
    }

    /**
     * Revoke a verification badge (admin action)
     */
    public static function revokeBadge(int $userId, string $badgeType, int $adminId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE member_verification_badges
             SET revoked_at = NOW()
             WHERE user_id = ? AND tenant_id = ? AND badge_type = ? AND revoked_at IS NULL",
            [$userId, $tenantId, $badgeType]
        );

        return true;
    }

    /**
     * Get all active verification badges for a user
     */
    public static function getUserBadges(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT badge_type, granted_at, expires_at,
                    CONCAT(u.first_name, ' ', u.last_name) as verified_by_name
             FROM member_verification_badges mvb
             LEFT JOIN users u ON mvb.verified_by = u.id
             WHERE mvb.user_id = ? AND mvb.tenant_id = ?
               AND mvb.revoked_at IS NULL
               AND (mvb.expires_at IS NULL OR mvb.expires_at > NOW())",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            $row['label'] = self::BADGE_LABELS[$row['badge_type']] ?? $row['badge_type'];
            $row['icon'] = self::BADGE_ICONS[$row['badge_type']] ?? 'badge';
            return $row;
        }, $rows);
    }

    /**
     * Batch get badges for multiple users (for profile cards)
     *
     * @param array $userIds
     * @return array Keyed by user_id => [badge_types]
     */
    public static function getBatchUserBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $rows = Database::query(
            "SELECT user_id, badge_type
             FROM member_verification_badges
             WHERE user_id IN ({$placeholders}) AND tenant_id = ?
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())",
            array_merge($userIds, [$tenantId])
        )->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($result[$uid])) {
                $result[$uid] = [];
            }
            $result[$uid][] = [
                'type' => $row['badge_type'],
                'label' => self::BADGE_LABELS[$row['badge_type']] ?? $row['badge_type'],
                'icon' => self::BADGE_ICONS[$row['badge_type']] ?? 'badge',
            ];
        }

        return $result;
    }

    /**
     * Check if user has a specific badge type
     */
    public static function hasBadge(int $userId, string $badgeType): bool
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT 1 FROM member_verification_badges
             WHERE user_id = ? AND tenant_id = ? AND badge_type = ?
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            [$userId, $tenantId, $badgeType]
        )->fetch();

        return (bool)$row;
    }

    /**
     * Get all badges for admin management (includes revoked)
     */
    public static function getAdminBadgeList(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $rows = Database::query(
            "SELECT mvb.*, CONCAT(u.first_name, ' ', u.last_name) as verified_by_name
             FROM member_verification_badges mvb
             LEFT JOIN users u ON mvb.verified_by = u.id
             WHERE mvb.user_id = ? AND mvb.tenant_id = ?
             ORDER BY mvb.granted_at DESC",
            [$userId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            $row['label'] = self::BADGE_LABELS[$row['badge_type']] ?? $row['badge_type'];
            $row['icon'] = self::BADGE_ICONS[$row['badge_type']] ?? 'badge';
            $row['is_active'] = $row['revoked_at'] === null && ($row['expires_at'] === null || strtotime($row['expires_at']) > time());
            return $row;
        }, $rows);
    }

    /**
     * Auto-grant email_verified badge when user verifies email
     */
    public static function autoGrantEmailVerified(int $userId): void
    {
        $tenantId = TenantContext::getId();

        try {
            $existing = Database::query(
                "SELECT id FROM member_verification_badges WHERE user_id = ? AND tenant_id = ? AND badge_type = 'email_verified' AND revoked_at IS NULL",
                [$userId, $tenantId]
            )->fetch();

            if (!$existing) {
                Database::query(
                    "INSERT INTO member_verification_badges (user_id, tenant_id, badge_type, verified_by, verification_note)
                     VALUES (?, ?, 'email_verified', NULL, 'Auto-granted on email verification')",
                    [$userId, $tenantId]
                );
            }
        } catch (\Exception $e) {
            error_log("Failed to auto-grant email_verified badge: " . $e->getMessage());
        }
    }
}
