<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * HoursReportService
 *
 * Aggregated hours reporting by category, member, and time period.
 * Provides chart-ready data for the React admin dashboard.
 *
 * All methods are tenant-scoped.
 */
class HoursReportService
{
    /**
     * Get hours grouped by category
     *
     * Uses transaction_categories and the category_id on transactions.
     * Falls back to transaction_type if no categories are set.
     *
     * @param int $tenantId
     * @param array $dateRange ['from' => 'Y-m-d', 'to' => 'Y-m-d']
     * @return array
     */
    public static function getHoursByCategory(int $tenantId, array $dateRange = []): array
    {
        $from = $dateRange['from'] ?? date('Y-m-d', strtotime('-12 months'));
        $to = $dateRange['to'] ?? date('Y-m-d');

        // Try category_id join first
        $byCategory = [];
        try {
            $stmt = Database::query(
                "SELECT
                    COALESCE(tc.name, 'Uncategorized') as category_name,
                    COALESCE(tc.slug, 'uncategorized') as category_slug,
                    COALESCE(tc.color, '#6366f1') as category_color,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(t.amount), 0) as total_hours,
                    COUNT(DISTINCT t.sender_id) as unique_givers,
                    COUNT(DISTINCT t.receiver_id) as unique_receivers
                 FROM transactions t
                 LEFT JOIN transaction_categories tc ON t.category_id = tc.id AND tc.tenant_id = ?
                 WHERE t.tenant_id = ?
                   AND t.created_at >= ?
                   AND t.created_at <= ?
                   AND t.status = 'completed'
                 GROUP BY COALESCE(tc.name, 'Uncategorized'), COALESCE(tc.slug, 'uncategorized'), COALESCE(tc.color, '#6366f1')
                 ORDER BY total_hours DESC",
                [$tenantId, $tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
            );

            while ($row = $stmt->fetch()) {
                $byCategory[] = [
                    'category' => $row['category_name'],
                    'slug' => $row['category_slug'],
                    'color' => $row['category_color'],
                    'transaction_count' => (int) $row['transaction_count'],
                    'total_hours' => round((float) $row['total_hours'], 1),
                    'unique_givers' => (int) $row['unique_givers'],
                    'unique_receivers' => (int) $row['unique_receivers'],
                ];
            }
        } catch (\Exception $e) {
            // Fallback: group by transaction_type
            $stmt = Database::query(
                "SELECT
                    COALESCE(t.transaction_type, 'transfer') as category_name,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(t.amount), 0) as total_hours,
                    COUNT(DISTINCT t.sender_id) as unique_givers,
                    COUNT(DISTINCT t.receiver_id) as unique_receivers
                 FROM transactions t
                 WHERE t.tenant_id = ?
                   AND t.created_at >= ?
                   AND t.created_at <= ?
                   AND t.status = 'completed'
                 GROUP BY COALESCE(t.transaction_type, 'transfer')
                 ORDER BY total_hours DESC",
                [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
            );

            while ($row = $stmt->fetch()) {
                $byCategory[] = [
                    'category' => ucfirst(str_replace('_', ' ', $row['category_name'])),
                    'slug' => $row['category_name'],
                    'color' => '#6366f1',
                    'transaction_count' => (int) $row['transaction_count'],
                    'total_hours' => round((float) $row['total_hours'], 1),
                    'unique_givers' => (int) $row['unique_givers'],
                    'unique_receivers' => (int) $row['unique_receivers'],
                ];
            }
        }

        return $byCategory;
    }

    /**
     * Get hours grouped by member
     *
     * @param int $tenantId
     * @param array $dateRange
     * @param string $sortBy 'total', 'given', 'received'
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getHoursByMember(int $tenantId, array $dateRange = [], string $sortBy = 'total', int $limit = 50, int $offset = 0): array
    {
        $from = $dateRange['from'] ?? date('Y-m-d', strtotime('-12 months'));
        $to = $dateRange['to'] ?? date('Y-m-d');
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);

        $orderClause = match ($sortBy) {
            'given' => 'hours_given DESC',
            'received' => 'hours_received DESC',
            default => '(hours_given + hours_received) DESC',
        };

        $stmt = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url,
                    COALESCE(
                        (SELECT SUM(amount) FROM transactions WHERE sender_id = u.id AND tenant_id = ? AND status = 'completed' AND created_at >= ? AND created_at <= ?),
                        0
                    ) as hours_given,
                    COALESCE(
                        (SELECT SUM(amount) FROM transactions WHERE receiver_id = u.id AND tenant_id = ? AND status = 'completed' AND created_at >= ? AND created_at <= ?),
                        0
                    ) as hours_received
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'
             HAVING (hours_given + hours_received) > 0
             ORDER BY {$orderClause}
             LIMIT ? OFFSET ?",
            [
                $tenantId, $from . ' 00:00:00', $to . ' 23:59:59',
                $tenantId, $from . ' 00:00:00', $to . ' 23:59:59',
                $tenantId,
                $limit, $offset,
            ]
        );

        $members = [];
        while ($row = $stmt->fetch()) {
            $given = round((float) $row['hours_given'], 1);
            $received = round((float) $row['hours_received'], 1);
            $members[] = [
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'profile_image_url' => $row['avatar_url'],
                'hours_given' => $given,
                'hours_received' => $received,
                'total_hours' => round($given + $received, 1),
                'balance' => round($received - $given, 1),
            ];
        }

        return $members;
    }

    /**
     * Get hours by time period (monthly breakdown)
     *
     * @param int $tenantId
     * @param array $dateRange
     * @return array
     */
    public static function getHoursByPeriod(int $tenantId, array $dateRange = []): array
    {
        $from = $dateRange['from'] ?? date('Y-m-d', strtotime('-12 months'));
        $to = $dateRange['to'] ?? date('Y-m-d');

        $stmt = Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COALESCE(SUM(amount), 0) as total_hours,
                COUNT(*) as transaction_count,
                COUNT(DISTINCT sender_id) as unique_givers,
                COUNT(DISTINCT receiver_id) as unique_receivers
             FROM transactions
             WHERE tenant_id = ?
               AND created_at >= ?
               AND created_at <= ?
               AND status = 'completed'
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );

        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'month' => $row['month'],
                'total_hours' => round((float) $row['total_hours'], 1),
                'transaction_count' => (int) $row['transaction_count'],
                'unique_givers' => (int) $row['unique_givers'],
                'unique_receivers' => (int) $row['unique_receivers'],
            ];
        }

        return $data;
    }

    /**
     * Get overall hours summary (totals + balance metrics)
     *
     * @param int $tenantId
     * @param array $dateRange
     * @return array
     */
    public static function getHoursSummary(int $tenantId, array $dateRange = []): array
    {
        $from = $dateRange['from'] ?? date('Y-m-d', strtotime('-12 months'));
        $to = $dateRange['to'] ?? date('Y-m-d');

        $stmt = Database::query(
            "SELECT
                COALESCE(SUM(amount), 0) as total_hours,
                COUNT(*) as total_transactions,
                COUNT(DISTINCT sender_id) as unique_givers,
                COUNT(DISTINCT receiver_id) as unique_receivers,
                COALESCE(AVG(amount), 0) as avg_amount,
                COALESCE(MIN(amount), 0) as min_amount,
                COALESCE(MAX(amount), 0) as max_amount
             FROM transactions
             WHERE tenant_id = ?
               AND created_at >= ?
               AND created_at <= ?
               AND status = 'completed'",
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59']
        );
        $data = $stmt->fetch();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'total_hours' => round((float) $data['total_hours'], 1),
            'total_transactions' => (int) $data['total_transactions'],
            'unique_givers' => (int) $data['unique_givers'],
            'unique_receivers' => (int) $data['unique_receivers'],
            'avg_hours_per_transaction' => round((float) $data['avg_amount'], 2),
            'min_hours' => round((float) $data['min_amount'], 2),
            'max_hours' => round((float) $data['max_amount'], 2),
        ];
    }
}
