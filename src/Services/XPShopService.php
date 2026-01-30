<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

class XPShopService
{
    /**
     * Get all available shop items
     */
    public static function getAvailableItems()
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT * FROM xp_shop_items
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY display_order ASC, xp_cost ASC",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Get items with user purchase status
     */
    public static function getItemsWithUserStatus($userId)
    {
        $tenantId = TenantContext::getId();

        $items = self::getAvailableItems();

        // Get user's purchases
        $purchases = Database::query(
            "SELECT item_id, COUNT(*) as purchase_count FROM user_xp_purchases
             WHERE tenant_id = ? AND user_id = ?
             GROUP BY item_id",
            [$tenantId, $userId]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Get user's current XP
        $user = Database::query("SELECT xp FROM users WHERE id = ?", [$userId])->fetch();
        $userXP = (int)($user['xp'] ?? 0);

        foreach ($items as &$item) {
            $item['user_purchases'] = $purchases[$item['id']] ?? 0;
            $item['can_purchase'] = self::canPurchase($userId, $item, $userXP);
            $item['reason'] = self::getPurchaseBlockReason($userId, $item, $userXP);
        }

        return [
            'items' => $items,
            'user_xp' => $userXP
        ];
    }

    /**
     * Check if user can purchase an item
     */
    public static function canPurchase($userId, $item, $userXP = null)
    {
        if ($userXP === null) {
            $user = Database::query("SELECT xp FROM users WHERE id = ?", [$userId])->fetch();
            $userXP = (int)($user['xp'] ?? 0);
        }

        // Check XP
        if ($userXP < $item['xp_cost']) {
            return false;
        }

        // Check stock limit
        if ($item['stock_limit'] !== null) {
            $totalPurchases = Database::query(
                "SELECT COUNT(*) as count FROM user_xp_purchases WHERE item_id = ?",
                [$item['id']]
            )->fetch()['count'];

            if ($totalPurchases >= $item['stock_limit']) {
                return false;
            }
        }

        // Check per-user limit
        if ($item['per_user_limit'] !== null) {
            $userPurchases = Database::query(
                "SELECT COUNT(*) as count FROM user_xp_purchases WHERE user_id = ? AND item_id = ?",
                [$userId, $item['id']]
            )->fetch()['count'];

            if ($userPurchases >= $item['per_user_limit']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get reason why purchase is blocked
     */
    private static function getPurchaseBlockReason($userId, $item, $userXP)
    {
        if ($userXP < $item['xp_cost']) {
            return 'Not enough XP';
        }

        if ($item['stock_limit'] !== null) {
            $totalPurchases = Database::query(
                "SELECT COUNT(*) as count FROM user_xp_purchases WHERE item_id = ?",
                [$item['id']]
            )->fetch()['count'];

            if ($totalPurchases >= $item['stock_limit']) {
                return 'Out of stock';
            }
        }

        if ($item['per_user_limit'] !== null) {
            $userPurchases = Database::query(
                "SELECT COUNT(*) as count FROM user_xp_purchases WHERE user_id = ? AND item_id = ?",
                [$userId, $item['id']]
            )->fetch()['count'];

            if ($userPurchases >= $item['per_user_limit']) {
                return 'Already owned';
            }
        }

        return null;
    }

    /**
     * Purchase an item
     */
    public static function purchase($userId, $itemId)
    {
        $tenantId = TenantContext::getId();

        // Get item
        $item = Database::query(
            "SELECT * FROM xp_shop_items WHERE id = ? AND tenant_id = ? AND is_active = 1",
            [$itemId, $tenantId]
        )->fetch();

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        // Check if can purchase
        if (!self::canPurchase($userId, $item)) {
            $reason = self::getPurchaseBlockReason($userId, $item, null);
            return ['success' => false, 'error' => $reason ?? 'Cannot purchase'];
        }

        // Use transaction to ensure atomicity of XP deduction and purchase recording
        Database::beginTransaction();

        try {
            // Deduct XP
            Database::query(
                "UPDATE users SET xp = xp - ? WHERE id = ? AND xp >= ?",
                [$item['xp_cost'], $userId, $item['xp_cost']]
            );

            // Check if XP was actually deducted
            $affected = Database::getInstance()->rowCount ?? 1; // Fallback
            if ($affected == 0) {
                Database::rollback();
                return ['success' => false, 'error' => 'Not enough XP'];
            }

            // Record purchase
            $expiresAt = null;
            if ($item['item_type'] === 'perk') {
                // Perks expire in 30 days by default
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            }

            Database::query(
                "INSERT INTO user_xp_purchases (tenant_id, user_id, item_id, xp_spent, expires_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$tenantId, $userId, $itemId, $item['xp_cost'], $expiresAt]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("XPShopService::purchase error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Purchase failed'];
        }

        // Apply item effect (outside transaction - non-critical)
        self::applyItemEffect($userId, $item);

        // Send notification (outside transaction - non-critical)
        $basePath = TenantContext::getBasePath();
        Notification::create(
            $userId,
            "Purchase successful! You bought '{$item['name']}' for {$item['xp_cost']} XP. {$item['icon']}",
            "{$basePath}/achievements",
            'achievement'
        );

        return [
            'success' => true,
            'item' => $item,
            'xp_spent' => $item['xp_cost']
        ];
    }

    /**
     * Apply item effect after purchase
     */
    private static function applyItemEffect($userId, $item)
    {
        switch ($item['item_type']) {
            case 'badge':
                // Award special badge
                if (!empty($item['item_key'])) {
                    $badgeDef = [
                        'key' => $item['item_key'],
                        'name' => $item['name'],
                        'icon' => $item['icon'],
                        'type' => 'special',
                        'msg' => 'purchasing from the XP Shop'
                    ];
                    GamificationService::awardBadge($userId, $badgeDef);
                }
                break;

            case 'feature':
                // Enable feature for user (handled by feature flags)
                break;

            case 'cosmetic':
                // Apply cosmetic (profile themes, etc.)
                break;

            case 'perk':
                // Perks are time-limited benefits
                break;
        }
    }

    /**
     * Get user's active perks
     */
    public static function getUserActivePerks($userId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT xsi.*, uxp.purchased_at, uxp.expires_at
             FROM user_xp_purchases uxp
             JOIN xp_shop_items xsi ON uxp.item_id = xsi.id
             WHERE uxp.tenant_id = ? AND uxp.user_id = ? AND uxp.is_active = 1
             AND (uxp.expires_at IS NULL OR uxp.expires_at > NOW())",
            [$tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Check if user has a specific perk
     */
    public static function hasPerk($userId, $itemKey)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT uxp.id FROM user_xp_purchases uxp
             JOIN xp_shop_items xsi ON uxp.item_id = xsi.id
             WHERE uxp.tenant_id = ? AND uxp.user_id = ? AND xsi.item_key = ?
             AND uxp.is_active = 1
             AND (uxp.expires_at IS NULL OR uxp.expires_at > NOW())",
            [$tenantId, $userId, $itemKey]
        )->fetch();

        return (bool)$result;
    }

    /**
     * Create shop item (admin)
     */
    public static function createItem($data)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO xp_shop_items (tenant_id, item_key, name, description, icon, item_type, xp_cost, stock_limit, per_user_limit, display_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $data['item_key'],
                $data['name'],
                $data['description'] ?? '',
                $data['icon'] ?? null,
                $data['item_type'] ?? 'perk',
                $data['xp_cost'],
                $data['stock_limit'] ?? null,
                $data['per_user_limit'] ?? 1,
                $data['display_order'] ?? 0
            ]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Initialize default shop items
     */
    public static function initializeDefaultItems($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $items = [
            [
                'key' => 'featured_listing',
                'name' => 'Featured Listing',
                'description' => 'Your next listing will be featured at the top for 7 days',
                'icon' => '⭐',
                'type' => 'perk',
                'cost' => 500,
                'limit' => null
            ],
            [
                'key' => 'profile_badge_gold',
                'name' => 'Gold Profile Badge',
                'description' => 'A special gold badge displayed on your profile',
                'icon' => '🥇',
                'type' => 'badge',
                'cost' => 1000,
                'limit' => 1
            ],
            [
                'key' => 'streak_freeze',
                'name' => 'Streak Freeze',
                'description' => 'Protect your streak for one missed day',
                'icon' => '🧊',
                'type' => 'perk',
                'cost' => 200,
                'limit' => 3
            ],
            [
                'key' => 'xp_boost',
                'name' => 'XP Boost (24h)',
                'description' => 'Earn double XP for the next 24 hours',
                'icon' => '🚀',
                'type' => 'perk',
                'cost' => 300,
                'limit' => null
            ],
        ];

        foreach ($items as $item) {
            $existing = Database::query(
                "SELECT id FROM xp_shop_items WHERE tenant_id = ? AND item_key = ?",
                [$tenantId, $item['key']]
            )->fetch();

            if (!$existing) {
                Database::query(
                    "INSERT INTO xp_shop_items (tenant_id, item_key, name, description, icon, item_type, xp_cost, per_user_limit)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$tenantId, $item['key'], $item['name'], $item['description'], $item['icon'], $item['type'], $item['cost'], $item['limit']]
                );
            }
        }
    }
}
