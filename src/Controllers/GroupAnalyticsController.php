<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Group;
use Nexus\Models\GroupMember;

/**
 * GroupAnalyticsController
 *
 * Provides detailed analytics dashboards for group owners and admins.
 * Empowers community leaders with actionable insights about their groups.
 */
class GroupAnalyticsController
{
    /**
     * Main analytics dashboard for a specific group
     */
    public function index($groupId)
    {
        $this->requireAuth();
        $userId = $_SESSION['user_id'];
        $tenantId = TenantContext::getId();

        // Get group and verify access
        $group = Group::findById($groupId);
        if (!$group || $group['tenant_id'] != $tenantId) {
            $this->notFound();
            return;
        }

        // Check if user is owner/admin of group or site admin
        $isSiteAdmin = $this->isSiteAdmin($userId);
        $isGroupOwner = ($group['owner_id'] == $userId);
        $isGroupAdmin = GroupMember::isAdmin($groupId, $userId);

        if (!$isSiteAdmin && !$isGroupOwner && !$isGroupAdmin) {
            $this->forbidden('You must be a group owner or admin to view analytics');
            return;
        }

        // Gather analytics data
        $analytics = [
            // Overview Stats
            'overview' => $this->getOverviewStats($groupId),

            // Growth Metrics
            'member_growth' => $this->getMemberGrowth($groupId, 90),
            'new_this_week' => $this->getNewMembersThisWeek($groupId),
            'new_this_month' => $this->getNewMembersThisMonth($groupId),

            // Engagement Metrics
            'discussion_stats' => $this->getDiscussionStats($groupId),
            'post_stats' => $this->getPostStats($groupId),
            'most_active_members' => $this->getMostActiveMembers($groupId, 10),

            // Discovery Metrics
            'profile_views' => $this->getGroupViews($groupId, 30),
            'view_trend' => $this->getViewTrend($groupId, 30),

            // Content Performance
            'top_posts' => $this->getTopPosts($groupId, 5),
            'top_discussions' => $this->getTopDiscussions($groupId, 5),

            // Retention & Health
            'retention_30_day' => $this->getRetentionRate($groupId, 30),
            'churn_rate' => $this->getChurnRate($groupId, 30),
            'activity_distribution' => $this->getActivityDistribution($groupId),
        ];

        // Determine which view to render based on user role
        if ($isSiteAdmin) {
            // Admin users get the Gold Standard admin interface
            View::render('admin/groups/owner-analytics', [
                'pageTitle' => $group['name'] . ' - Analytics',
                'group' => $group,
                'analytics' => $analytics,
                'isOwner' => $isGroupOwner,
                'isAdmin' => $isGroupAdmin,
                'isSiteAdmin' => $isSiteAdmin,
            ]);
        } else {
            // Group owners/admins get the simplified public-facing view
            View::render('groups/analytics', [
                'pageTitle' => $group['name'] . ' - Analytics',
                'group' => $group,
                'analytics' => $analytics,
                'isOwner' => $isGroupOwner,
                'isAdmin' => $isGroupAdmin,
                'isSiteAdmin' => $isSiteAdmin,
            ]);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($groupId)
    {
        $tenantId = TenantContext::getId();

        // Total members
        $totalMembers = Database::query(
            "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'active'",
            [$groupId]
        )->fetchColumn();

        // Pending requests
        $pendingRequests = Database::query(
            "SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'pending'",
            [$groupId]
        )->fetchColumn();

        // Total discussions
        $totalDiscussions = 0;
        try {
            $totalDiscussions = Database::query(
                "SELECT COUNT(*) FROM group_discussions WHERE group_id = ?",
                [$groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Total posts in group feed
        $totalPosts = 0;
        try {
            $totalPosts = Database::query(
                "SELECT COUNT(*) FROM feed_posts WHERE group_id = ?",
                [$groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Average members per week (last 8 weeks)
        $avgMembersPerWeek = Database::query(
            "SELECT AVG(weekly_count) as avg_count FROM (
                SELECT COUNT(*) as weekly_count
                FROM group_members
                WHERE group_id = ?
                AND status = 'active'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                GROUP BY WEEK(created_at)
            ) as weekly_stats",
            [$groupId]
        )->fetchColumn() ?? 0;

        return [
            'total_members' => $totalMembers,
            'pending_requests' => $pendingRequests,
            'total_discussions' => $totalDiscussions,
            'total_posts' => $totalPosts,
            'avg_members_per_week' => round($avgMembersPerWeek, 1),
        ];
    }

    /**
     * Get member growth over time
     *
     * @param int $groupId
     * @param int $days Number of days to look back
     * @return array Daily member join counts
     */
    private function getMemberGrowth($groupId, $days = 90)
    {
        $growth = Database::query(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM group_members
             WHERE group_id = ?
             AND status = 'active'
             AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$groupId, $days]
        )->fetchAll();

        // Fill gaps with zero counts
        $result = [];
        $currentDate = new \DateTime("-{$days} days");
        $endDate = new \DateTime();

        $growthByDate = [];
        foreach ($growth as $row) {
            $growthByDate[$row['date']] = $row['count'];
        }

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $result[] = [
                'date' => $dateStr,
                'count' => $growthByDate[$dateStr] ?? 0
            ];
            $currentDate->modify('+1 day');
        }

        return $result;
    }

    /**
     * Get new members this week
     */
    private function getNewMembersThisWeek($groupId)
    {
        return Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND status = 'active'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$groupId]
        )->fetchColumn();
    }

    /**
     * Get new members this month
     */
    private function getNewMembersThisMonth($groupId)
    {
        return Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND status = 'active'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$groupId]
        )->fetchColumn();
    }

    /**
     * Get discussion statistics
     */
    private function getDiscussionStats($groupId)
    {
        try {
            // Total discussions
            $total = Database::query(
                "SELECT COUNT(*) FROM group_discussions WHERE group_id = ?",
                [$groupId]
            )->fetchColumn();

            // Discussions this week
            $thisWeek = Database::query(
                "SELECT COUNT(*) FROM group_discussions
                 WHERE group_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$groupId]
            )->fetchColumn();

            // Average messages per discussion
            $avgMessages = Database::query(
                "SELECT AVG(message_count) as avg_count FROM (
                    SELECT COUNT(*) as message_count
                    FROM group_discussion_messages
                    WHERE discussion_id IN (
                        SELECT id FROM group_discussions WHERE group_id = ?
                    )
                    GROUP BY discussion_id
                ) as discussion_stats",
                [$groupId]
            )->fetchColumn() ?? 0;

            return [
                'total' => $total,
                'this_week' => $thisWeek,
                'avg_messages_per_discussion' => round($avgMessages, 1),
            ];
        } catch (\Exception $e) {
            // Tables may not exist
            return [
                'total' => 0,
                'this_week' => 0,
                'avg_messages_per_discussion' => 0,
            ];
        }
    }

    /**
     * Get post statistics
     */
    private function getPostStats($groupId)
    {
        try {
            // Total posts
            $total = Database::query(
                "SELECT COUNT(*) FROM feed_posts WHERE group_id = ?",
                [$groupId]
            )->fetchColumn();

            // Posts this week
            $thisWeek = Database::query(
                "SELECT COUNT(*) FROM feed_posts
                 WHERE group_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$groupId]
            )->fetchColumn();

            // Average comments per post
            $avgComments = Database::query(
                "SELECT AVG(comment_count) as avg_count FROM (
                    SELECT COUNT(*) as comment_count
                    FROM comments
                    WHERE content_id IN (
                        SELECT id FROM feed_posts WHERE group_id = ?
                    )
                    AND content_type = 'post'
                    GROUP BY content_id
                ) as post_stats",
                [$groupId]
            )->fetchColumn() ?? 0;

            return [
                'total' => $total,
                'this_week' => $thisWeek,
                'avg_comments_per_post' => round($avgComments, 1),
            ];
        } catch (\Exception $e) {
            // Tables may not exist
            return [
                'total' => 0,
                'this_week' => 0,
                'avg_comments_per_post' => 0,
            ];
        }
    }

    /**
     * Get most active members
     *
     * @param int $groupId
     * @param int $limit
     * @return array Top active members with contribution counts
     */
    private function getMostActiveMembers($groupId, $limit = 10)
    {
        try {
            return Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.profile_image_url,
                        CONCAT(u.first_name, ' ', u.last_name) as name,
                        (
                            SELECT COUNT(*) FROM feed_posts
                            WHERE user_id = u.id AND group_id = ?
                        ) as post_count,
                        (
                            SELECT COUNT(*) FROM comments
                            WHERE user_id = u.id
                            AND content_type = 'post'
                            AND content_id IN (
                                SELECT id FROM feed_posts WHERE group_id = ?
                            )
                        ) as comment_count,
                        (
                            SELECT COUNT(*) FROM group_discussion_messages
                            WHERE user_id = u.id
                            AND discussion_id IN (
                                SELECT id FROM group_discussions WHERE group_id = ?
                            )
                        ) as discussion_message_count
                 FROM users u
                 JOIN group_members gm ON u.id = gm.user_id
                 WHERE gm.group_id = ?
                 AND gm.status = 'active'
                 HAVING (post_count + comment_count + discussion_message_count) > 0
                 ORDER BY (post_count + comment_count + discussion_message_count) DESC
                 LIMIT ?",
                [$groupId, $groupId, $groupId, $groupId, $limit]
            )->fetchAll();
        } catch (\Exception $e) {
            // Simplified fallback if tables don't exist
            return Database::query(
                "SELECT u.id, u.first_name, u.last_name, u.profile_image_url,
                        CONCAT(u.first_name, ' ', u.last_name) as name,
                        gm.created_at as joined_at
                 FROM users u
                 JOIN group_members gm ON u.id = gm.user_id
                 WHERE gm.group_id = ?
                 AND gm.status = 'active'
                 ORDER BY gm.created_at ASC
                 LIMIT ?",
                [$groupId, $limit]
            )->fetchAll();
        }
    }

    /**
     * Get group profile views (if tracking is implemented)
     */
    private function getGroupViews($groupId, $days = 30)
    {
        try {
            return Database::query(
                "SELECT COUNT(*) FROM group_views
                 WHERE group_id = ?
                 AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$groupId, $days]
            )->fetchColumn();
        } catch (\Exception $e) {
            // Table doesn't exist yet - return 0
            return 0;
        }
    }

    /**
     * Get view trend over time
     */
    private function getViewTrend($groupId, $days = 30)
    {
        try {
            return Database::query(
                "SELECT DATE(viewed_at) as date, COUNT(*) as count
                 FROM group_views
                 WHERE group_id = ?
                 AND viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(viewed_at)
                 ORDER BY date ASC",
                [$groupId, $days]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top posts by engagement
     */
    private function getTopPosts($groupId, $limit = 5)
    {
        try {
            return Database::query(
                "SELECT fp.id, fp.content, fp.created_at,
                        u.first_name, u.last_name, u.profile_image_url,
                        CONCAT(u.first_name, ' ', u.last_name) as author_name,
                        (
                            SELECT COUNT(*) FROM comments
                            WHERE content_id = fp.id AND content_type = 'post'
                        ) as comment_count,
                        (
                            SELECT COUNT(*) FROM reactions
                            WHERE content_id = fp.id AND content_type = 'post'
                        ) as reaction_count
                 FROM feed_posts fp
                 JOIN users u ON fp.user_id = u.id
                 WHERE fp.group_id = ?
                 AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ORDER BY (comment_count + reaction_count) DESC
                 LIMIT ?",
                [$groupId, $limit]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top discussions by message count
     */
    private function getTopDiscussions($groupId, $limit = 5)
    {
        try {
            return Database::query(
                "SELECT gd.id, gd.title, gd.created_at,
                        u.first_name, u.last_name, u.profile_image_url,
                        CONCAT(u.first_name, ' ', u.last_name) as author_name,
                        (
                            SELECT COUNT(*) FROM group_discussion_messages
                            WHERE discussion_id = gd.id
                        ) as message_count
                 FROM group_discussions gd
                 JOIN users u ON gd.created_by = u.id
                 WHERE gd.group_id = ?
                 AND gd.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 ORDER BY message_count DESC
                 LIMIT ?",
                [$groupId, $limit]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calculate 30-day retention rate
     *
     * Retention = % of members who joined 30 days ago and are still active
     */
    private function getRetentionRate($groupId, $days = 30)
    {
        // Members who joined exactly $days ago
        $joinedThen = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL ? DAY)",
            [$groupId, $days]
        )->fetchColumn();

        if ($joinedThen == 0) {
            return null; // Not enough data
        }

        // Of those, how many are still active (not left)
        $stillActive = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL ? DAY)
             AND status = 'active'",
            [$groupId, $days]
        )->fetchColumn();

        return round(($stillActive / $joinedThen) * 100, 1);
    }

    /**
     * Calculate churn rate (members who left in last X days)
     */
    private function getChurnRate($groupId, $days = 30)
    {
        // Total active members at start of period
        $membersAtStart = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             AND status = 'active'",
            [$groupId, $days]
        )->fetchColumn();

        if ($membersAtStart == 0) {
            return 0;
        }

        // Members who left during the period
        // Note: This requires tracking when members leave (updated_at on status change)
        // For now, we'll use a simplified approach
        $currentMembers = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ?
             AND status = 'active'",
            [$groupId]
        )->fetchColumn();

        $membersLost = max(0, $membersAtStart - $currentMembers);

        return round(($membersLost / $membersAtStart) * 100, 1);
    }

    /**
     * Get activity distribution (how many members by activity level)
     */
    private function getActivityDistribution($groupId)
    {
        // Define activity levels based on contributions in last 30 days
        $distribution = [
            'very_active' => 0,  // 10+ contributions
            'active' => 0,       // 3-9 contributions
            'moderate' => 0,     // 1-2 contributions
            'inactive' => 0,     // 0 contributions
        ];

        try {
            $members = Database::query(
                "SELECT u.id,
                        (
                            SELECT COUNT(*) FROM feed_posts
                            WHERE user_id = u.id
                            AND group_id = ?
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ) as post_count,
                        (
                            SELECT COUNT(*) FROM comments
                            WHERE user_id = u.id
                            AND content_type = 'post'
                            AND content_id IN (SELECT id FROM feed_posts WHERE group_id = ?)
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ) as comment_count
                 FROM users u
                 JOIN group_members gm ON u.id = gm.user_id
                 WHERE gm.group_id = ?
                 AND gm.status = 'active'",
                [$groupId, $groupId, $groupId]
            )->fetchAll();

            foreach ($members as $member) {
                $total = $member['post_count'] + $member['comment_count'];

                if ($total >= 10) {
                    $distribution['very_active']++;
                } elseif ($total >= 3) {
                    $distribution['active']++;
                } elseif ($total >= 1) {
                    $distribution['moderate']++;
                } else {
                    $distribution['inactive']++;
                }
            }
        } catch (\Exception $e) {
            // Fallback if tables don't exist
        }

        return $distribution;
    }

    /**
     * API endpoint: Get analytics data as JSON
     */
    public function apiData($groupId)
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            return;
        }

        $userId = $_SESSION['user_id'];
        $group = Group::findById($groupId);

        if (!$group) {
            http_response_code(404);
            echo json_encode(['error' => 'Group not found']);
            return;
        }

        // Check access
        $isSiteAdmin = $this->isSiteAdmin($userId);
        $isGroupOwner = ($group['owner_id'] == $userId);
        $isGroupAdmin = GroupMember::isAdmin($groupId, $userId);

        if (!$isSiteAdmin && !$isGroupOwner && !$isGroupAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        $data = [
            'overview' => $this->getOverviewStats($groupId),
            'member_growth' => $this->getMemberGrowth($groupId, 90),
            'discussion_stats' => $this->getDiscussionStats($groupId),
            'post_stats' => $this->getPostStats($groupId),
        ];

        echo json_encode($data);
    }

    /**
     * Helper: Require authentication
     */
    private function requireAuth()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    /**
     * Helper: Check if user is site admin
     */
    private function isSiteAdmin($userId)
    {
        $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user) {
            return false;
        }
        return in_array($user['role'] ?? '', ['super_admin', 'admin', 'tenant_admin']);
    }

    /**
     * Helper: 404 Not Found
     */
    private function notFound()
    {
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        exit;
    }

    /**
     * Helper: 403 Forbidden
     */
    private function forbidden($message = 'Access denied')
    {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>$message</p>";
        exit;
    }
}
