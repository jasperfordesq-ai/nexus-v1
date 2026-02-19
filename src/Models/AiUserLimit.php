<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AI User Limit Model
 *
 * Manages per-user AI usage limits.
 */
class AiUserLimit
{
    /**
     * Get or create limit record for a user
     */
    public static function getOrCreate(int $userId): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("SELECT * FROM ai_user_limits WHERE tenant_id = ? AND user_id = ?");
        $stmt->execute([$tenantId, $userId]);
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($record) {
            // Check if we need to reset counters
            $record = self::checkAndResetCounters($record);
            return $record;
        }

        // Create new record with default limits
        $limitsConfig = AIServiceFactory::getLimitsConfig();

        $stmt = $db->prepare("
            INSERT INTO ai_user_limits
            (tenant_id, user_id, daily_limit, monthly_limit, daily_used, monthly_used, last_reset_daily, last_reset_monthly, created_at)
            VALUES (?, ?, ?, ?, 0, 0, CURDATE(), CURDATE(), NOW())
        ");

        $stmt->execute([
            $tenantId,
            $userId,
            $limitsConfig['daily_limit'] ?? 50,
            $limitsConfig['monthly_limit'] ?? 1000,
        ]);

        return self::getOrCreate($userId);
    }

    /**
     * Check if user can make a request (within limits)
     */
    public static function canMakeRequest(int $userId): array
    {
        $limits = self::getOrCreate($userId);

        $canMake = true;
        $reason = null;

        if ($limits['daily_used'] >= $limits['daily_limit']) {
            $canMake = false;
            $reason = 'daily_limit_reached';
        } elseif ($limits['monthly_used'] >= $limits['monthly_limit']) {
            $canMake = false;
            $reason = 'monthly_limit_reached';
        }

        return [
            'allowed' => $canMake,
            'reason' => $reason,
            'daily_used' => $limits['daily_used'],
            'daily_limit' => $limits['daily_limit'],
            'daily_remaining' => max(0, $limits['daily_limit'] - $limits['daily_used']),
            'monthly_used' => $limits['monthly_used'],
            'monthly_limit' => $limits['monthly_limit'],
            'monthly_remaining' => max(0, $limits['monthly_limit'] - $limits['monthly_used']),
        ];
    }

    /**
     * Increment usage counters
     */
    public static function incrementUsage(int $userId, int $amount = 1): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Ensure record exists
        self::getOrCreate($userId);

        $stmt = $db->prepare("
            UPDATE ai_user_limits
            SET daily_used = daily_used + ?,
                monthly_used = monthly_used + ?,
                updated_at = NOW()
            WHERE tenant_id = ? AND user_id = ?
        ");

        return $stmt->execute([$amount, $amount, $tenantId, $userId]);
    }

    /**
     * Update user limits
     */
    public static function updateLimits(int $userId, int $dailyLimit, int $monthlyLimit): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Ensure record exists
        self::getOrCreate($userId);

        $stmt = $db->prepare("
            UPDATE ai_user_limits
            SET daily_limit = ?, monthly_limit = ?, updated_at = NOW()
            WHERE tenant_id = ? AND user_id = ?
        ");

        return $stmt->execute([$dailyLimit, $monthlyLimit, $tenantId, $userId]);
    }

    /**
     * Reset daily counter for a user
     */
    public static function resetDaily(int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            UPDATE ai_user_limits
            SET daily_used = 0, last_reset_daily = CURDATE(), updated_at = NOW()
            WHERE tenant_id = ? AND user_id = ?
        ");

        return $stmt->execute([$tenantId, $userId]);
    }

    /**
     * Reset monthly counter for a user
     */
    public static function resetMonthly(int $userId): bool
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            UPDATE ai_user_limits
            SET monthly_used = 0, last_reset_monthly = CURDATE(), updated_at = NOW()
            WHERE tenant_id = ? AND user_id = ?
        ");

        return $stmt->execute([$tenantId, $userId]);
    }

    /**
     * Check and reset counters if needed
     */
    private static function checkAndResetCounters(array $record): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');
        $currentMonth = date('Y-m');

        $needsUpdate = false;
        $updates = [];

        // Check daily reset
        if ($record['last_reset_daily'] !== $today) {
            $record['daily_used'] = 0;
            $record['last_reset_daily'] = $today;
            $updates[] = "daily_used = 0";
            $updates[] = "last_reset_daily = CURDATE()";
            $needsUpdate = true;
        }

        // Check monthly reset
        $lastResetMonth = substr($record['last_reset_monthly'] ?? '', 0, 7);
        if ($lastResetMonth !== $currentMonth) {
            $record['monthly_used'] = 0;
            $record['last_reset_monthly'] = $today;
            $updates[] = "monthly_used = 0";
            $updates[] = "last_reset_monthly = CURDATE()";
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $sql = "UPDATE ai_user_limits SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE tenant_id = ? AND user_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$tenantId, $record['user_id']]);
        }

        return $record;
    }

    /**
     * Get usage stats for admin dashboard
     */
    public static function getUsageStats(): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_users,
                SUM(daily_used) as total_daily_usage,
                SUM(monthly_used) as total_monthly_usage,
                AVG(daily_used) as avg_daily_usage,
                AVG(monthly_used) as avg_monthly_usage,
                MAX(daily_used) as max_daily_usage,
                MAX(monthly_used) as max_monthly_usage
            FROM ai_user_limits
            WHERE tenant_id = ?
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get top users by usage
     */
    public static function getTopUsers(int $limit = 10): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = (int) $limit;
        $stmt = $db->prepare("
            SELECT l.*, u.name, u.email, u.avatar_url
            FROM ai_user_limits l
            JOIN users u ON u.id = l.user_id
            WHERE l.tenant_id = ?
            ORDER BY l.monthly_used DESC
            LIMIT {$limit}
        ");

        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
