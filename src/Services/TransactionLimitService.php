<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * TransactionLimitService
 *
 * Enforces transaction limits for organization wallets:
 * - Daily limits per user
 * - Weekly limits per user
 * - Monthly limits per user
 * - Single transaction maximums
 * - Organization-level daily limits
 */
class TransactionLimitService
{
    // Default limits (can be overridden per tenant or org)
    const DEFAULT_SINGLE_TRANSACTION_MAX = 500;
    const DEFAULT_DAILY_LIMIT = 1000;
    const DEFAULT_WEEKLY_LIMIT = 3000;
    const DEFAULT_MONTHLY_LIMIT = 10000;
    const DEFAULT_ORG_DAILY_LIMIT = 5000;

    /**
     * Check if a transaction is within limits
     *
     * @param int $organizationId Organization making the transfer
     * @param int $userId User receiving or making the transfer
     * @param float $amount Amount to transfer
     * @param string $direction 'outgoing' (from org) or 'incoming' (to org)
     * @return array ['allowed' => bool, 'reason' => string|null, 'limits' => array]
     */
    public static function checkLimits($organizationId, $userId, $amount, $direction = 'outgoing')
    {
        $limits = self::getLimits($organizationId);
        $usage = self::getUsage($organizationId, $userId, $direction);

        // Check single transaction max
        if ($amount > $limits['single_max']) {
            return [
                'allowed' => false,
                'reason' => "Amount exceeds single transaction limit of {$limits['single_max']} credits",
                'limits' => $limits,
                'usage' => $usage
            ];
        }

        // Check daily limit for user
        if (($usage['daily'] + $amount) > $limits['daily']) {
            $remaining = max(0, $limits['daily'] - $usage['daily']);
            return [
                'allowed' => false,
                'reason' => "Daily limit reached. You have {$remaining} credits remaining today.",
                'limits' => $limits,
                'usage' => $usage
            ];
        }

        // Check weekly limit for user
        if (($usage['weekly'] + $amount) > $limits['weekly']) {
            $remaining = max(0, $limits['weekly'] - $usage['weekly']);
            return [
                'allowed' => false,
                'reason' => "Weekly limit reached. You have {$remaining} credits remaining this week.",
                'limits' => $limits,
                'usage' => $usage
            ];
        }

        // Check monthly limit for user
        if (($usage['monthly'] + $amount) > $limits['monthly']) {
            $remaining = max(0, $limits['monthly'] - $usage['monthly']);
            return [
                'allowed' => false,
                'reason' => "Monthly limit reached. You have {$remaining} credits remaining this month.",
                'limits' => $limits,
                'usage' => $usage
            ];
        }

        // Check organization daily limit (for outgoing only)
        if ($direction === 'outgoing') {
            $orgUsage = self::getOrgDailyUsage($organizationId);
            if (($orgUsage + $amount) > $limits['org_daily']) {
                $remaining = max(0, $limits['org_daily'] - $orgUsage);
                return [
                    'allowed' => false,
                    'reason' => "Organization daily limit reached. {$remaining} credits remaining today.",
                    'limits' => $limits,
                    'usage' => $usage
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => null,
            'limits' => $limits,
            'usage' => $usage
        ];
    }

    /**
     * Get limits for an organization
     * Checks org-specific settings first, then tenant defaults, then system defaults
     */
    public static function getLimits($organizationId)
    {
        $tenantId = TenantContext::getId();

        // Check for org-specific limits
        try {
            $orgLimits = Database::query(
                "SELECT * FROM org_wallet_limits WHERE tenant_id = ? AND organization_id = ?",
                [$tenantId, $organizationId]
            )->fetch();

            if ($orgLimits) {
                return [
                    'single_max' => (float) $orgLimits['single_transaction_max'],
                    'daily' => (float) $orgLimits['daily_limit'],
                    'weekly' => (float) $orgLimits['weekly_limit'],
                    'monthly' => (float) $orgLimits['monthly_limit'],
                    'org_daily' => (float) $orgLimits['org_daily_limit'],
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Check for tenant-level defaults
        try {
            $tenantLimits = Database::query(
                "SELECT * FROM tenant_wallet_limits WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            if ($tenantLimits) {
                return [
                    'single_max' => (float) $tenantLimits['single_transaction_max'],
                    'daily' => (float) $tenantLimits['daily_limit'],
                    'weekly' => (float) $tenantLimits['weekly_limit'],
                    'monthly' => (float) $tenantLimits['monthly_limit'],
                    'org_daily' => (float) $tenantLimits['org_daily_limit'],
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Return system defaults
        return [
            'single_max' => self::DEFAULT_SINGLE_TRANSACTION_MAX,
            'daily' => self::DEFAULT_DAILY_LIMIT,
            'weekly' => self::DEFAULT_WEEKLY_LIMIT,
            'monthly' => self::DEFAULT_MONTHLY_LIMIT,
            'org_daily' => self::DEFAULT_ORG_DAILY_LIMIT,
        ];
    }

    /**
     * Get usage statistics for a user within an organization
     */
    public static function getUsage($organizationId, $userId, $direction = 'outgoing')
    {
        $tenantId = TenantContext::getId();

        // Determine which field to check based on direction
        if ($direction === 'outgoing') {
            // User receiving from org
            $whereClause = "receiver_type = 'user' AND receiver_id = ? AND sender_type = 'organization' AND sender_id = ?";
            $params = [$userId, $organizationId];
        } else {
            // User depositing to org
            $whereClause = "sender_type = 'user' AND sender_id = ? AND receiver_type = 'organization' AND receiver_id = ?";
            $params = [$userId, $organizationId];
        }

        // Daily usage (today)
        $daily = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ? AND $whereClause
             AND DATE(created_at) = CURDATE()",
            array_merge([$tenantId, $organizationId], $params)
        )->fetchColumn();

        // Weekly usage (last 7 days)
        $weekly = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ? AND $whereClause
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            array_merge([$tenantId, $organizationId], $params)
        )->fetchColumn();

        // Monthly usage (last 30 days)
        $monthly = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ? AND $whereClause
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            array_merge([$tenantId, $organizationId], $params)
        )->fetchColumn();

        return [
            'daily' => (float) $daily,
            'weekly' => (float) $weekly,
            'monthly' => (float) $monthly,
        ];
    }

    /**
     * Get organization's total outgoing transactions today
     */
    public static function getOrgDailyUsage($organizationId)
    {
        $tenantId = TenantContext::getId();

        return (float) Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?
             AND sender_type = 'organization' AND sender_id = ?
             AND DATE(created_at) = CURDATE()",
            [$tenantId, $organizationId, $organizationId]
        )->fetchColumn();
    }

    /**
     * Set custom limits for an organization
     */
    public static function setOrgLimits($organizationId, $limits)
    {
        $tenantId = TenantContext::getId();

        try {
            // Check if table exists
            Database::query("SELECT 1 FROM org_wallet_limits LIMIT 1");
        } catch (\Exception $e) {
            // Create table if it doesn't exist
            Database::query("
                CREATE TABLE IF NOT EXISTS org_wallet_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    organization_id INT NOT NULL,
                    single_transaction_max DECIMAL(10,2) DEFAULT 500,
                    daily_limit DECIMAL(10,2) DEFAULT 1000,
                    weekly_limit DECIMAL(10,2) DEFAULT 3000,
                    monthly_limit DECIMAL(10,2) DEFAULT 10000,
                    org_daily_limit DECIMAL(10,2) DEFAULT 5000,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_org_limits (tenant_id, organization_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        Database::query(
            "INSERT INTO org_wallet_limits
             (tenant_id, organization_id, single_transaction_max, daily_limit, weekly_limit, monthly_limit, org_daily_limit)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             single_transaction_max = VALUES(single_transaction_max),
             daily_limit = VALUES(daily_limit),
             weekly_limit = VALUES(weekly_limit),
             monthly_limit = VALUES(monthly_limit),
             org_daily_limit = VALUES(org_daily_limit),
             updated_at = NOW()",
            [
                $tenantId,
                $organizationId,
                $limits['single_max'] ?? self::DEFAULT_SINGLE_TRANSACTION_MAX,
                $limits['daily'] ?? self::DEFAULT_DAILY_LIMIT,
                $limits['weekly'] ?? self::DEFAULT_WEEKLY_LIMIT,
                $limits['monthly'] ?? self::DEFAULT_MONTHLY_LIMIT,
                $limits['org_daily'] ?? self::DEFAULT_ORG_DAILY_LIMIT,
            ]
        );

        return true;
    }

    /**
     * Get remaining limits for display
     */
    public static function getRemainingLimits($organizationId, $userId, $direction = 'outgoing')
    {
        $limits = self::getLimits($organizationId);
        $usage = self::getUsage($organizationId, $userId, $direction);

        return [
            'single_max' => $limits['single_max'],
            'daily_remaining' => max(0, $limits['daily'] - $usage['daily']),
            'weekly_remaining' => max(0, $limits['weekly'] - $usage['weekly']),
            'monthly_remaining' => max(0, $limits['monthly'] - $usage['monthly']),
            'daily_used' => $usage['daily'],
            'weekly_used' => $usage['weekly'],
            'monthly_used' => $usage['monthly'],
        ];
    }
}
