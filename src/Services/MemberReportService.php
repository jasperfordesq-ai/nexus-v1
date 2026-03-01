<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MemberReportService
 *
 * Comprehensive member reporting for admin dashboards.
 * Provides active member tracking, registration trends, retention analysis,
 * engagement metrics, and contributor rankings.
 *
 * All methods are tenant-scoped.
 */
class MemberReportService
{
    /**
     * Get active members (logged in within N days)
     *
     * @param int $tenantId
     * @param int $days Number of days to consider "active"
     * @param int $limit
     * @param int $offset
     * @return array ['members' => [...], 'total' => int, 'active_count' => int]
     */
    public static function getActiveMembers(int $tenantId, int $days = 30, int $limit = 50, int $offset = 0): array
    {
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active' AND last_login_at >= ?",
            [$tenantId, $cutoff]
        )->fetchColumn();

        $stmt = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.last_login_at, u.created_at,
                    u.avatar_url,
                    (SELECT COUNT(*) FROM transactions t WHERE (t.sender_id = u.id OR t.receiver_id = u.id) AND t.tenant_id = ? AND t.status = 'completed') as transaction_count,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.sender_id = u.id AND t.tenant_id = ? AND t.status = 'completed') as hours_given,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.receiver_id = u.id AND t.tenant_id = ? AND t.status = 'completed') as hours_received
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active' AND u.last_login_at >= ?
             ORDER BY u.last_login_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $tenantId, $tenantId, $tenantId, $cutoff, $limit, $offset]
        );

        $members = [];
        while ($row = $stmt->fetch()) {
            $members[] = [
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'last_login_at' => $row['last_login_at'],
                'created_at' => $row['created_at'],
                'profile_image_url' => $row['avatar_url'],
                'transaction_count' => (int) $row['transaction_count'],
                'hours_given' => round((float) $row['hours_given'], 1),
                'hours_received' => round((float) $row['hours_received'], 1),
            ];
        }

        return [
            'members' => $members,
            'total' => $total,
            'period_days' => $days,
        ];
    }

    /**
     * Get new registrations by period (day, week, month)
     *
     * @param int $tenantId
     * @param string $period 'daily', 'weekly', 'monthly'
     * @param int $months Number of months to look back
     * @return array
     */
    public static function getNewRegistrations(int $tenantId, string $period = 'monthly', int $months = 12): array
    {
        $from = date('Y-m-d', strtotime("-{$months} months"));

        switch ($period) {
            case 'daily':
                $groupBy = "DATE(created_at)";
                $selectAs = "DATE(created_at) as period_key";
                break;
            case 'weekly':
                $groupBy = "YEARWEEK(created_at, 1)";
                $selectAs = "CONCAT(YEAR(created_at), '-W', LPAD(WEEK(created_at, 1), 2, '0')) as period_key";
                break;
            case 'monthly':
            default:
                $groupBy = "DATE_FORMAT(created_at, '%Y-%m')";
                $selectAs = "DATE_FORMAT(created_at, '%Y-%m') as period_key";
                break;
        }

        $stmt = Database::query(
            "SELECT {$selectAs},
                    COUNT(*) as registrations
             FROM users
             WHERE tenant_id = ? AND created_at >= ?
             GROUP BY {$groupBy}
             ORDER BY period_key ASC",
            [$tenantId, $from . ' 00:00:00']
        );

        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = [
                'period' => $row['period_key'],
                'registrations' => (int) $row['registrations'],
            ];
        }

        // Total registrations in the period
        $totalStmt = Database::query(
            "SELECT COUNT(*) as total FROM users WHERE tenant_id = ? AND created_at >= ?",
            [$tenantId, $from . ' 00:00:00']
        );
        $total = (int) $totalStmt->fetch()['total'];

        return [
            'period_type' => $period,
            'months_back' => $months,
            'total_registrations' => $total,
            'data' => $data,
        ];
    }

    /**
     * Get member retention metrics
     *
     * Measures what percentage of members who joined N months ago
     * are still active (logged in within last 30 days).
     *
     * @param int $tenantId
     * @param int $months Number of cohort months to analyze
     * @return array
     */
    public static function getMemberRetention(int $tenantId, int $months = 12): array
    {
        $cohorts = [];
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        for ($i = $months; $i >= 1; $i--) {
            $cohortStart = date('Y-m-01', strtotime("-{$i} months"));
            $cohortEnd = date('Y-m-t', strtotime("-{$i} months"));
            $cohortLabel = date('M Y', strtotime("-{$i} months"));

            // Members who joined in this month
            $joinedStmt = Database::query(
                "SELECT COUNT(*) as joined FROM users
                 WHERE tenant_id = ? AND created_at >= ? AND created_at <= ?",
                [$tenantId, $cohortStart . ' 00:00:00', $cohortEnd . ' 23:59:59']
            );
            $joined = (int) $joinedStmt->fetch()['joined'];

            // Of those, how many logged in within last 30 days
            $retainedStmt = Database::query(
                "SELECT COUNT(*) as retained FROM users
                 WHERE tenant_id = ? AND created_at >= ? AND created_at <= ?
                   AND last_login_at >= ? AND status = 'active'",
                [$tenantId, $cohortStart . ' 00:00:00', $cohortEnd . ' 23:59:59', $thirtyDaysAgo]
            );
            $retained = (int) $retainedStmt->fetch()['retained'];

            $cohorts[] = [
                'cohort' => $cohortLabel,
                'cohort_month' => date('Y-m', strtotime("-{$i} months")),
                'joined' => $joined,
                'retained' => $retained,
                'retention_rate' => $joined > 0 ? round($retained / $joined, 3) : 0,
            ];
        }

        // Overall retention rate
        $totalJoined = array_sum(array_column($cohorts, 'joined'));
        $totalRetained = array_sum(array_column($cohorts, 'retained'));

        return [
            'cohorts' => $cohorts,
            'overall' => [
                'total_joined' => $totalJoined,
                'total_retained' => $totalRetained,
                'overall_retention_rate' => $totalJoined > 0 ? round($totalRetained / $totalJoined, 3) : 0,
            ],
        ];
    }

    /**
     * Get engagement metrics overview
     *
     * @param int $tenantId
     * @param int $days Period in days
     * @return array
     */
    public static function getEngagementMetrics(int $tenantId, int $days = 30): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totalUsers = (int) Database::query(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        )->fetchColumn();

        // Active users (logged in)
        $loggedIn = (int) Database::query(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active' AND last_login_at >= ?",
            [$tenantId, $cutoff]
        )->fetchColumn();

        // Users who made transactions
        $traders = (int) Database::query(
            "SELECT COUNT(DISTINCT user_id) FROM (
                SELECT sender_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= ? AND status = 'completed'
                UNION
                SELECT receiver_id as user_id FROM transactions WHERE tenant_id = ? AND created_at >= ? AND status = 'completed'
            ) t",
            [$tenantId, $cutoff, $tenantId, $cutoff]
        )->fetchColumn();

        // Posts created
        $posts = 0;
        try {
            $posts = (int) Database::query(
                "SELECT COUNT(*) FROM feed_posts WHERE tenant_id = ? AND created_at >= ?",
                [$tenantId, $cutoff]
            )->fetchColumn();
        } catch (\Exception $e) {
            // feed_posts table may not exist
        }

        // Comments created
        $comments = 0;
        try {
            $comments = (int) Database::query(
                "SELECT COUNT(*) FROM comments WHERE tenant_id = ? AND created_at >= ?",
                [$tenantId, $cutoff]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        // Events RSVPs
        $rsvps = 0;
        try {
            $rsvps = (int) Database::query(
                "SELECT COUNT(*) FROM event_rsvps WHERE tenant_id = ? AND created_at >= ? AND status = 'going'",
                [$tenantId, $cutoff]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        // New connections
        $connections = 0;
        try {
            $connections = (int) Database::query(
                "SELECT COUNT(*) FROM connections WHERE tenant_id = ? AND created_at >= ? AND status = 'accepted'",
                [$tenantId, $cutoff]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        return [
            'period_days' => $days,
            'total_users' => $totalUsers,
            'active_users' => $loggedIn,
            'login_rate' => $totalUsers > 0 ? round($loggedIn / $totalUsers, 3) : 0,
            'trading_users' => $traders,
            'trading_rate' => $totalUsers > 0 ? round($traders / $totalUsers, 3) : 0,
            'posts_created' => $posts,
            'comments_created' => $comments,
            'event_rsvps' => $rsvps,
            'new_connections' => $connections,
        ];
    }

    /**
     * Get top contributors
     *
     * @param int $tenantId
     * @param int $days Period
     * @param int $limit Number of top contributors
     * @return array
     */
    public static function getTopContributors(int $tenantId, int $days = 30, int $limit = 20): array
    {
        $limit = min(100, max(1, $limit));
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stmt = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url,
                    COALESCE(
                        (SELECT SUM(t.amount) FROM transactions t WHERE t.sender_id = u.id AND t.tenant_id = ? AND t.status = 'completed' AND t.created_at >= ?),
                        0
                    ) as hours_given,
                    COALESCE(
                        (SELECT SUM(t.amount) FROM transactions t WHERE t.receiver_id = u.id AND t.tenant_id = ? AND t.status = 'completed' AND t.created_at >= ?),
                        0
                    ) as hours_received,
                    COALESCE(
                        (SELECT COUNT(*) FROM transactions t WHERE (t.sender_id = u.id OR t.receiver_id = u.id) AND t.tenant_id = ? AND t.status = 'completed' AND t.created_at >= ?),
                        0
                    ) as transaction_count
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'
             HAVING (hours_given + hours_received) > 0
             ORDER BY (hours_given + hours_received) DESC
             LIMIT ?",
            [$tenantId, $cutoff, $tenantId, $cutoff, $tenantId, $cutoff, $tenantId, $limit]
        );

        $contributors = [];
        while ($row = $stmt->fetch()) {
            $contributors[] = [
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'profile_image_url' => $row['avatar_url'],
                'hours_given' => round((float) $row['hours_given'], 1),
                'hours_received' => round((float) $row['hours_received'], 1),
                'total_hours' => round((float) $row['hours_given'] + (float) $row['hours_received'], 1),
                'transaction_count' => (int) $row['transaction_count'],
            ];
        }

        return $contributors;
    }

    /**
     * Get least active members
     *
     * @param int $tenantId
     * @param int $days Inactivity threshold in days
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getLeastActiveMembers(int $tenantId, int $days = 90, int $limit = 50, int $offset = 0): array
    {
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM users
             WHERE tenant_id = ? AND status = 'active'
               AND (last_login_at IS NULL OR last_login_at < ?)",
            [$tenantId, $cutoff]
        )->fetchColumn();

        $stmt = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.last_login_at, u.created_at
             FROM users u
             WHERE u.tenant_id = ? AND u.status = 'active'
               AND (u.last_login_at IS NULL OR u.last_login_at < ?)
             ORDER BY u.last_login_at ASC, u.created_at ASC
             LIMIT ? OFFSET ?",
            [$tenantId, $cutoff, $limit, $offset]
        );

        $members = [];
        while ($row = $stmt->fetch()) {
            $members[] = [
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => $row['email'],
                'last_login_at' => $row['last_login_at'],
                'created_at' => $row['created_at'],
                'days_inactive' => $row['last_login_at']
                    ? (int) ((time() - strtotime($row['last_login_at'])) / 86400)
                    : null,
            ];
        }

        return [
            'members' => $members,
            'total' => $total,
            'threshold_days' => $days,
        ];
    }
}
