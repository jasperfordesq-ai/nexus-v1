<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MemberActivityService - Comprehensive activity dashboard data
 *
 * Provides aggregated dashboard data:
 * - Recent activity timeline
 * - Hours given/received breakdown
 * - Skills offered/requested breakdown
 * - Connection stats
 * - Engagement metrics
 */
class MemberActivityService
{
    /**
     * Get comprehensive dashboard data for a user
     *
     * @param int $userId
     * @return array
     */
    public static function getDashboardData(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return [
            'timeline' => self::getRecentTimeline($userId, $tenantId),
            'hours_summary' => self::getHoursSummary($userId, $tenantId),
            'skills_breakdown' => self::getSkillsBreakdown($userId, $tenantId),
            'connection_stats' => self::getConnectionStats($userId, $tenantId),
            'engagement' => self::getEngagementMetrics($userId, $tenantId),
            'monthly_hours' => self::getMonthlyHours($userId, $tenantId),
        ];
    }

    /**
     * Get recent activity timeline (last 30 items)
     */
    public static function getRecentTimeline(int $userId, ?int $tenantId = null, int $limit = 30): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $items = [];

        // Posts
        try {
            $posts = Database::query(
                "SELECT id, 'post' as activity_type, content as description, created_at
                 FROM feed_posts
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC LIMIT ?",
                [$userId, $tenantId, $limit]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_merge($items, $posts);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Transactions (giving/receiving)
        try {
            $transactions = Database::query(
                "SELECT t.id,
                        CASE WHEN t.sender_id = ? THEN 'gave_hours' ELSE 'received_hours' END as activity_type,
                        CONCAT(
                            CASE WHEN t.sender_id = ? THEN 'Gave ' ELSE 'Received ' END,
                            t.amount, ' hour(s)',
                            CASE WHEN t.sender_id = ? THEN CONCAT(' to ', COALESCE(r.first_name, ''), ' ', COALESCE(r.last_name, ''))
                                 ELSE CONCAT(' from ', COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))
                            END
                        ) as description,
                        t.created_at
                 FROM transactions t
                 LEFT JOIN users s ON t.sender_id = s.id
                 LEFT JOIN users r ON t.receiver_id = r.id
                 WHERE (t.sender_id = ? OR t.receiver_id = ?) AND t.tenant_id = ? AND t.status = 'completed'
                 ORDER BY t.created_at DESC LIMIT ?",
                [$userId, $userId, $userId, $userId, $userId, $tenantId, $limit]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_merge($items, $transactions);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Comments
        try {
            $comments = Database::query(
                "SELECT id, 'comment' as activity_type, SUBSTRING(content, 1, 100) as description, created_at
                 FROM comments
                 WHERE user_id = ? AND tenant_id = ?
                 ORDER BY created_at DESC LIMIT ?",
                [$userId, $tenantId, $limit]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_merge($items, $comments);
        } catch (\Exception $e) {
        }

        // Connections
        try {
            $connections = Database::query(
                "SELECT c.id, 'connection' as activity_type,
                        CONCAT('Connected with ',
                            CASE WHEN c.requester_id = ? THEN CONCAT(u2.first_name, ' ', u2.last_name)
                                 ELSE CONCAT(u1.first_name, ' ', u1.last_name)
                            END
                        ) as description,
                        c.updated_at as created_at
                 FROM connections c
                 LEFT JOIN users u1 ON c.requester_id = u1.id
                 LEFT JOIN users u2 ON c.receiver_id = u2.id
                 WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.tenant_id = ? AND c.status = 'accepted'
                 ORDER BY c.updated_at DESC LIMIT ?",
                [$userId, $userId, $userId, $tenantId, $limit]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_merge($items, $connections);
        } catch (\Exception $e) {
        }

        // Event RSVPs
        try {
            $events = Database::query(
                "SELECT r.id, 'event_rsvp' as activity_type,
                        CONCAT('RSVP to ', e.title) as description,
                        r.created_at
                 FROM event_rsvps r
                 JOIN events e ON r.event_id = e.id
                 WHERE r.user_id = ? AND r.tenant_id = ? AND r.status = 'going'
                 ORDER BY r.created_at DESC LIMIT ?",
                [$userId, $tenantId, $limit]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $items = array_merge($items, $events);
        } catch (\Exception $e) {
        }

        // Sort by created_at descending
        usort($items, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * Get hours given/received summary
     */
    public static function getHoursSummary(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $given = Database::query(
                "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
                 FROM transactions
                 WHERE sender_id = ? AND tenant_id = ? AND status = 'completed'",
                [$userId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            $received = Database::query(
                "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count
                 FROM transactions
                 WHERE receiver_id = ? AND tenant_id = ? AND status = 'completed'",
                [$userId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return [
                'hours_given' => round((float)($given['total'] ?? 0), 1),
                'hours_received' => round((float)($received['total'] ?? 0), 1),
                'transactions_given' => (int)($given['count'] ?? 0),
                'transactions_received' => (int)($received['count'] ?? 0),
                'net_balance' => round((float)($received['total'] ?? 0) - (float)($given['total'] ?? 0), 1),
            ];
        } catch (\Exception $e) {
            return [
                'hours_given' => 0,
                'hours_received' => 0,
                'transactions_given' => 0,
                'transactions_received' => 0,
                'net_balance' => 0,
            ];
        }
    }

    /**
     * Get skills offered vs requested breakdown
     */
    public static function getSkillsBreakdown(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            // From user_skills table (M1 taxonomy)
            $skills = Database::query(
                "SELECT skill_name, is_offering, is_requesting, proficiency,
                        (SELECT COUNT(*) FROM skill_endorsements se WHERE se.endorsed_id = ? AND se.skill_name = us.skill_name AND se.tenant_id = ?) as endorsements
                 FROM user_skills us
                 WHERE us.user_id = ? AND us.tenant_id = ?
                 ORDER BY skill_name ASC",
                [$userId, $tenantId, $userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($skills)) {
                return [
                    'skills' => $skills,
                    'offering_count' => count(array_filter($skills, fn($s) => $s['is_offering'])),
                    'requesting_count' => count(array_filter($skills, fn($s) => $s['is_requesting'])),
                ];
            }
        } catch (\Exception $e) {
            // user_skills table may not exist yet
        }

        // Fallback: parse legacy skills CSV from users table
        try {
            $user = Database::query(
                "SELECT skills FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            $skillsList = [];
            if (!empty($user['skills'])) {
                $skillsList = array_map('trim', explode(',', $user['skills']));
            }

            // Count from listings
            $offers = Database::query(
                "SELECT COUNT(*) as cnt FROM listings WHERE user_id = ? AND tenant_id = ? AND type = 'offer' AND status = 'active'",
                [$userId, $tenantId]
            )->fetchColumn();

            $requests = Database::query(
                "SELECT COUNT(*) as cnt FROM listings WHERE user_id = ? AND tenant_id = ? AND type = 'request' AND status = 'active'",
                [$userId, $tenantId]
            )->fetchColumn();

            return [
                'skills' => array_map(fn($s) => ['skill_name' => $s, 'is_offering' => true, 'is_requesting' => false, 'proficiency' => null, 'endorsements' => 0], $skillsList),
                'offering_count' => (int)$offers,
                'requesting_count' => (int)$requests,
            ];
        } catch (\Exception $e) {
            return ['skills' => [], 'offering_count' => 0, 'requesting_count' => 0];
        }
    }

    /**
     * Get connection statistics
     */
    public static function getConnectionStats(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $total = Database::query(
                "SELECT COUNT(*) as cnt FROM connections
                 WHERE (requester_id = ? OR receiver_id = ?) AND tenant_id = ? AND status = 'accepted'",
                [$userId, $userId, $tenantId]
            )->fetchColumn();

            $pending = Database::query(
                "SELECT COUNT(*) as cnt FROM connections
                 WHERE receiver_id = ? AND tenant_id = ? AND status = 'pending'",
                [$userId, $tenantId]
            )->fetchColumn();

            $groups = Database::query(
                "SELECT COUNT(*) as cnt FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.user_id = ? AND g.tenant_id = ? AND gm.status = 'active'",
                [$userId, $tenantId]
            )->fetchColumn();

            return [
                'total_connections' => (int)$total,
                'pending_requests' => (int)$pending,
                'groups_joined' => (int)$groups,
            ];
        } catch (\Exception $e) {
            return ['total_connections' => 0, 'pending_requests' => 0, 'groups_joined' => 0];
        }
    }

    /**
     * Get engagement metrics (posts, comments, likes in last 30 days)
     */
    public static function getEngagementMetrics(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

        $metrics = [
            'posts_count' => 0,
            'comments_count' => 0,
            'likes_given' => 0,
            'likes_received' => 0,
            'period' => 'last_30_days',
        ];

        try {
            $metrics['posts_count'] = (int)Database::query(
                "SELECT COUNT(*) FROM feed_posts WHERE user_id = ? AND tenant_id = ? AND created_at >= ?",
                [$userId, $tenantId, $thirtyDaysAgo]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        try {
            $metrics['comments_count'] = (int)Database::query(
                "SELECT COUNT(*) FROM comments WHERE user_id = ? AND tenant_id = ? AND created_at >= ?",
                [$userId, $tenantId, $thirtyDaysAgo]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        try {
            $metrics['likes_given'] = (int)Database::query(
                "SELECT COUNT(*) FROM likes WHERE user_id = ? AND tenant_id = ? AND created_at >= ?",
                [$userId, $tenantId, $thirtyDaysAgo]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        try {
            $metrics['likes_received'] = (int)Database::query(
                "SELECT COUNT(*) FROM likes l
                 JOIN feed_posts p ON l.target_id = p.id AND l.target_type = 'post'
                 WHERE p.user_id = ? AND l.tenant_id = ? AND l.created_at >= ?",
                [$userId, $tenantId, $thirtyDaysAgo]
            )->fetchColumn();
        } catch (\Exception $e) {
        }

        return $metrics;
    }

    /**
     * Get monthly hours given/received for charting (last 12 months)
     */
    public static function getMonthlyHours(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $months = [];

        try {
            // Given per month
            $given = Database::query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE sender_id = ? AND tenant_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month
                 ORDER BY month ASC",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Received per month
            $received = Database::query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(amount), 0) as total
                 FROM transactions
                 WHERE receiver_id = ? AND tenant_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month
                 ORDER BY month ASC",
                [$userId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $givenMap = array_column($given, 'total', 'month');
            $receivedMap = array_column($received, 'total', 'month');

            // Build 12-month chart data
            for ($i = 11; $i >= 0; $i--) {
                $monthKey = date('Y-m', strtotime("-{$i} months"));
                $months[] = [
                    'month' => $monthKey,
                    'label' => date('M Y', strtotime("-{$i} months")),
                    'given' => round((float)($givenMap[$monthKey] ?? 0), 1),
                    'received' => round((float)($receivedMap[$monthKey] ?? 0), 1),
                ];
            }
        } catch (\Exception $e) {
            // Return empty 12-month structure
            for ($i = 11; $i >= 0; $i--) {
                $months[] = [
                    'month' => date('Y-m', strtotime("-{$i} months")),
                    'label' => date('M Y', strtotime("-{$i} months")),
                    'given' => 0,
                    'received' => 0,
                ];
            }
        }

        return $months;
    }
}
