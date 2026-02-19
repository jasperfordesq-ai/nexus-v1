<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class UserBadge
{
    public static function award($userId, $badgeKey, $name, $icon = null)
    {
        // Ignore if already has badge due to UNIQUE constraints, or check first
        $sql = "INSERT IGNORE INTO user_badges (user_id, badge_key, name, icon) VALUES (?, ?, ?, ?)";
        Database::query($sql, [$userId, $badgeKey, $name, $icon]);
    }

    public static function getForUser($userId)
    {
        return Database::query("SELECT * FROM user_badges WHERE user_id = ? ORDER BY awarded_at DESC", [$userId])->fetchAll();
    }

    public static function hasBadge($userId, $badgeKey)
    {
        $res = Database::query("SELECT id FROM user_badges WHERE user_id = ? AND badge_key = ?", [$userId, $badgeKey])->fetch();
        return (bool) $res;
    }

    /**
     * Get showcased/pinned badges for a user (up to 3)
     */
    public static function getShowcased($userId)
    {
        return Database::query(
            "SELECT * FROM user_badges WHERE user_id = ? AND is_showcased = 1 ORDER BY showcase_order ASC LIMIT 3",
            [$userId]
        )->fetchAll();
    }

    /**
     * Set a badge as showcased
     */
    public static function setShowcased($userId, $badgeKey, $order = 0)
    {
        Database::query(
            "UPDATE user_badges SET is_showcased = 1, showcase_order = ? WHERE user_id = ? AND badge_key = ?",
            [$order, $userId, $badgeKey]
        );
    }

    /**
     * Remove badge from showcase
     */
    public static function removeFromShowcase($userId, $badgeKey)
    {
        Database::query(
            "UPDATE user_badges SET is_showcased = 0, showcase_order = 0 WHERE user_id = ? AND badge_key = ?",
            [$userId, $badgeKey]
        );
    }

    /**
     * Update all showcased badges for a user
     */
    public static function updateShowcase($userId, $badgeKeys)
    {
        // Clear all showcased
        Database::query("UPDATE user_badges SET is_showcased = 0, showcase_order = 0 WHERE user_id = ?", [$userId]);

        // Set new showcased (max 3)
        $order = 0;
        foreach (array_slice($badgeKeys, 0, 3) as $key) {
            self::setShowcased($userId, $key, $order++);
        }
    }

    /**
     * Get badge rarity stats (percentage of users who have each badge)
     */
    public static function getBadgeRarityStats()
    {
        $tenantId = TenantContext::getId();

        // Get total approved users
        $totalUsers = Database::query(
            "SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND is_approved = 1",
            [$tenantId]
        )->fetch()['total'] ?? 1;

        if ($totalUsers < 1) $totalUsers = 1;

        // Get count per badge
        $badgeCounts = Database::query(
            "SELECT ub.badge_key, COUNT(DISTINCT ub.user_id) as count
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ? AND u.is_approved = 1
             GROUP BY ub.badge_key",
            [$tenantId]
        )->fetchAll();

        $rarity = [];
        foreach ($badgeCounts as $row) {
            $percent = round(($row['count'] / $totalUsers) * 100, 1);
            $rarity[$row['badge_key']] = [
                'count' => (int)$row['count'],
                'percent' => $percent,
                'label' => self::getRarityLabel($percent)
            ];
        }

        return $rarity;
    }

    /**
     * Get rarity label based on percentage
     */
    private static function getRarityLabel($percent)
    {
        if ($percent <= 1) return 'Legendary';
        if ($percent <= 5) return 'Epic';
        if ($percent <= 15) return 'Rare';
        if ($percent <= 40) return 'Uncommon';
        return 'Common';
    }
}
