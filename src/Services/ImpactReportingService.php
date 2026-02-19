<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ImpactReportingService
 *
 * Calculates Social Return on Investment (SROI), community health metrics,
 * and impact timelines for tenant communities. All methods are tenant-scoped.
 *
 * SROI methodology follows Timebanking UK standards:
 * - Default hourly value: GBP 15.00 per hour
 * - Default social multiplier: 3.5x (accounts for secondary social benefits)
 */
class ImpactReportingService
{
    // Default values following Timebanking UK standards
    private const DEFAULT_HOURLY_VALUE = 15.00;  // GBP per hour
    private const DEFAULT_SOCIAL_MULTIPLIER = 3.5;

    /**
     * Calculate Social Return on Investment (SROI)
     *
     * @param array $config ['hourly_value' => float, 'social_multiplier' => float, 'months' => int]
     * @return array SROI metrics including total hours, monetary value, social value, and ratio
     */
    public static function calculateSROI(array $config = []): array
    {
        $tenantId = TenantContext::getId();
        $months = $config['months'] ?? 12;
        $hourlyValue = $config['hourly_value'] ?? self::DEFAULT_HOURLY_VALUE;
        $socialMultiplier = $config['social_multiplier'] ?? self::DEFAULT_SOCIAL_MULTIPLIER;

        $stmt = Database::query(
            "SELECT
                COALESCE(SUM(amount), 0) as total_hours,
                COUNT(*) as total_transactions,
                COUNT(DISTINCT sender_id) as unique_givers,
                COUNT(DISTINCT receiver_id) as unique_receivers
             FROM transactions
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)",
            [$tenantId, $months]
        );
        $data = $stmt->fetch();

        $totalHours = (float) $data['total_hours'];
        $monetaryValue = $totalHours * $hourlyValue;
        $socialValue = $monetaryValue * $socialMultiplier;

        return [
            'total_hours' => round($totalHours, 1),
            'total_transactions' => (int) $data['total_transactions'],
            'unique_givers' => (int) $data['unique_givers'],
            'unique_receivers' => (int) $data['unique_receivers'],
            'hourly_value' => $hourlyValue,
            'monetary_value' => round($monetaryValue, 2),
            'social_multiplier' => $socialMultiplier,
            'social_value' => round($socialValue, 2),
            'sroi_ratio' => $monetaryValue > 0 ? round($socialValue / $monetaryValue, 1) : 0,
            'period_months' => $months,
        ];
    }

    /**
     * Get community health metrics
     *
     * Calculates engagement, retention, reciprocity, activation, and network density
     * metrics for the current tenant community.
     *
     * @return array Community health metrics
     */
    public static function getCommunityHealthMetrics(): array
    {
        $tenantId = TenantContext::getId();

        // Total and active users
        $userStats = Database::query(
            "SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as active_90d,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_30d
             FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        )->fetch();

        $totalUsers = (int) $userStats['total_users'];
        $active90d = (int) $userStats['active_90d'];
        $new30d = (int) $userStats['new_30d'];

        // Engagement: users who traded in last 30 days / total
        $traders = Database::query(
            "SELECT COUNT(DISTINCT user_id) as count FROM (
                SELECT sender_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                UNION
                SELECT receiver_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ) t",
            [$tenantId, $tenantId]
        )->fetch();
        $activeTraders = (int) $traders['count'];

        // Reciprocity: how balanced individual users are (1.0 = perfect balance)
        $balanceStats = Database::query(
            "SELECT
                AVG(ABS(given - received) / GREATEST(given + received, 1)) as imbalance_ratio
             FROM (
                SELECT u.id,
                    COALESCE((SELECT SUM(amount) FROM transactions WHERE sender_id = u.id AND tenant_id = ?), 0) as given,
                    COALESCE((SELECT SUM(amount) FROM transactions WHERE receiver_id = u.id AND tenant_id = ?), 0) as received
                FROM users u WHERE u.tenant_id = ? AND u.status = 'active'
                HAVING given > 0 OR received > 0
             ) user_balance",
            [$tenantId, $tenantId, $tenantId]
        )->fetch();

        $imbalanceRatio = (float) ($balanceStats['imbalance_ratio'] ?? 0);
        $reciprocityScore = round(1 - $imbalanceRatio, 2); // 1.0 = perfect balance

        // New member activation: new users (30d) who made at least one transaction
        $activated = 0;
        if ($new30d > 0) {
            $activatedResult = Database::query(
                "SELECT COUNT(DISTINCT u.id) as count
                 FROM users u
                 WHERE u.tenant_id = ? AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND (
                    EXISTS (SELECT 1 FROM transactions t WHERE t.sender_id = u.id AND t.tenant_id = ?)
                    OR EXISTS (SELECT 1 FROM transactions t WHERE t.receiver_id = u.id AND t.tenant_id = ?)
                 )",
                [$tenantId, $tenantId, $tenantId]
            )->fetch();
            $activated = (int) $activatedResult['count'];
        }

        // Connections density
        $connectionStats = Database::query(
            "SELECT COUNT(*) as total_connections FROM connections WHERE tenant_id = ? AND status = 'accepted'",
            [$tenantId]
        )->fetch();
        $totalConnections = (int) $connectionStats['total_connections'];
        $possibleConnections = max($totalUsers * ($totalUsers - 1) / 2, 1);

        return [
            'total_users' => $totalUsers,
            'active_users_90d' => $active90d,
            'new_users_30d' => $new30d,
            'active_traders_30d' => $activeTraders,
            'engagement_rate' => $totalUsers > 0 ? round($activeTraders / $totalUsers, 3) : 0,
            'retention_rate' => $totalUsers > 0 ? round($active90d / $totalUsers, 3) : 0,
            'reciprocity_score' => $reciprocityScore,
            'activation_rate' => $new30d > 0 ? round($activated / $new30d, 3) : 0,
            'network_density' => round($totalConnections / $possibleConnections, 4),
            'total_connections' => $totalConnections,
        ];
    }

    /**
     * Get impact timeline (monthly breakdown)
     *
     * Returns monthly aggregations of hours exchanged, transaction count,
     * and new user signups for the specified period.
     *
     * @param int $months Number of months to include in the timeline
     * @return array Monthly breakdown with hours_exchanged, transactions, new_users
     */
    public static function getImpactTimeline(int $months = 12): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COALESCE(SUM(amount), 0) as hours_exchanged,
                COUNT(*) as transactions
             FROM transactions
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month",
            [$tenantId, $months]
        );

        $timeline = [];
        while ($row = $stmt->fetch()) {
            $timeline[] = [
                'month' => $row['month'],
                'hours_exchanged' => round((float) $row['hours_exchanged'], 1),
                'transactions' => (int) $row['transactions'],
            ];
        }

        // Also get monthly new users
        $userTimeline = Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_users
             FROM users
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month",
            [$tenantId, $months]
        );

        $usersByMonth = [];
        while ($row = $userTimeline->fetch()) {
            $usersByMonth[$row['month']] = (int) $row['new_users'];
        }

        // Merge user data into timeline
        foreach ($timeline as &$entry) {
            $entry['new_users'] = $usersByMonth[$entry['month']] ?? 0;
        }

        return $timeline;
    }

    /**
     * Get tenant report configuration
     *
     * Returns tenant branding info and configurable SROI parameters
     * (hourly value and social multiplier) from tenant settings.
     *
     * @return array Tenant name, slug, logo URL, and SROI config values
     */
    public static function getReportConfig(): array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT name, slug, configuration FROM tenants WHERE id = ?",
            [$tenantId]
        );
        $tenant = $stmt->fetch();

        $config = json_decode($tenant['configuration'] ?? '{}', true) ?: [];
        $settings = $config['settings'] ?? [];

        return [
            'tenant_name' => $tenant['name'] ?? 'Community',
            'tenant_slug' => $tenant['slug'] ?? '',
            'logo_url' => $config['logo_url'] ?? null,
            'hourly_value' => (float) ($settings['impact_hourly_value'] ?? self::DEFAULT_HOURLY_VALUE),
            'social_multiplier' => (float) ($settings['impact_social_multiplier'] ?? self::DEFAULT_SOCIAL_MULTIPLIER),
        ];
    }
}
