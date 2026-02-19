<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupReportingService
 *
 * Advanced reporting and scheduled email reports for group owners and admins
 */
class GroupReportingService
{
    /**
     * Generate weekly digest email for group owners
     *
     * @param int $groupId
     * @param int $ownerId
     * @return array Email data ready for sending
     */
    public static function generateWeeklyDigest($groupId, $ownerId)
    {
        $group = Database::query(
            "SELECT * FROM `groups` WHERE id = ?",
            [$groupId]
        )->fetch();

        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        // Get stats for the last 7 days
        $stats = self::getWeeklyStats($groupId);

        // Get top contributors
        $topContributors = self::getTopContributors($groupId, 7, 5);

        // Get pending items requiring action
        $pendingActions = self::getPendingActions($groupId);

        // Generate HTML email
        $emailHtml = self::renderWeeklyDigestEmail([
            'group' => $group,
            'stats' => $stats,
            'topContributors' => $topContributors,
            'pendingActions' => $pendingActions,
        ]);

        return [
            'success' => true,
            'to' => self::getOwnerEmail($ownerId),
            'subject' => "📊 Weekly Report: {$group['name']}",
            'html' => $emailHtml,
            'stats' => $stats,
        ];
    }

    /**
     * Get weekly statistics for a group
     */
    private static function getWeeklyStats($groupId)
    {
        $stats = [];

        // New members this week
        $stats['new_members'] = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ? AND status = 'active'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$groupId]
        )->fetchColumn();

        // New posts this week
        try {
            $stats['new_posts'] = Database::query(
                "SELECT COUNT(*) FROM feed_posts
                 WHERE group_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            $stats['new_posts'] = 0;
        }

        // New discussions this week
        try {
            $stats['new_discussions'] = Database::query(
                "SELECT COUNT(*) FROM group_discussions
                 WHERE group_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            $stats['new_discussions'] = 0;
        }

        // Total engagement (comments + reactions)
        try {
            $stats['total_engagement'] = Database::query(
                "SELECT (
                    (SELECT COUNT(*) FROM comments WHERE content_type = 'post'
                     AND content_id IN (SELECT id FROM feed_posts WHERE group_id = ?)
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                    +
                    (SELECT COUNT(*) FROM reactions WHERE content_type = 'post'
                     AND content_id IN (SELECT id FROM feed_posts WHERE group_id = ?)
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
                ) as total",
                [$groupId, $groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            $stats['total_engagement'] = 0;
        }

        // Pending join requests
        $stats['pending_requests'] = Database::query(
            "SELECT COUNT(*) FROM group_members
             WHERE group_id = ? AND status = 'pending'",
            [$groupId]
        )->fetchColumn();

        return $stats;
    }

    /**
     * Get top contributors for the week
     */
    private static function getTopContributors($groupId, $days = 7, $limit = 5)
    {
        try {
            return Database::query("
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.profile_image_url,
                    (
                        SELECT COUNT(*) FROM feed_posts
                        WHERE user_id = u.id AND group_id = ?
                        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) as post_count,
                    (
                        SELECT COUNT(*) FROM comments
                        WHERE user_id = u.id AND content_type = 'post'
                        AND content_id IN (SELECT id FROM feed_posts WHERE group_id = ?)
                        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    ) as comment_count
                FROM users u
                JOIN group_members gm ON u.id = gm.user_id
                WHERE gm.group_id = ? AND gm.status = 'active'
                HAVING (post_count + comment_count) > 0
                ORDER BY (post_count + comment_count) DESC
                LIMIT ?
            ", [$groupId, $days, $groupId, $days, $groupId, $limit])->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get pending actions requiring owner attention
     */
    private static function getPendingActions($groupId)
    {
        $actions = [];

        // Pending member requests
        $pendingMembers = Database::query(
            "SELECT COUNT(*) as count FROM group_members
             WHERE group_id = ? AND status = 'pending'",
            [$groupId]
        )->fetch();

        if ($pendingMembers['count'] > 0) {
            $actions[] = [
                'type' => 'member_requests',
                'count' => $pendingMembers['count'],
                'message' => "{$pendingMembers['count']} member request(s) awaiting approval",
                'url' => "/groups/{$groupId}/members",
            ];
        }

        return $actions;
    }

    /**
     * Render weekly digest email HTML
     */
    private static function renderWeeklyDigestEmail($data)
    {
        $basePath = TenantContext::getBasePath();
        $group = $data['group'];
        $stats = $data['stats'];
        $contributors = $data['topContributors'];
        $actions = $data['pendingActions'];

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                .stat-card { background: #f9fafb; border-radius: 8px; padding: 20px; text-align: center; }
                .stat-value { font-size: 32px; font-weight: bold; color: #6366f1; margin-bottom: 5px; }
                .stat-label { font-size: 14px; color: #6b7280; }
                .section { margin: 30px 0; }
                .section-title { font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; }
                .contributor { display: flex; align-items: center; padding: 12px; background: #f9fafb; border-radius: 6px; margin-bottom: 10px; }
                .contributor-avatar { width: 40px; height: 40px; border-radius: 50%; background: #e5e7eb; margin-right: 12px; }
                .contributor-name { font-weight: 600; color: #111827; }
                .contributor-stats { font-size: 13px; color: #6b7280; }
                .action-item { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 6px; margin-bottom: 10px; }
                .action-item strong { color: #92400e; }
                .cta-button { display: inline-block; background: #6366f1; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin: 20px 0; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 13px; color: #6b7280; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📊 Weekly Group Report</h1>
                    <p style="margin: 5px 0 0 0; opacity: 0.9;"><?= htmlspecialchars($group['name']) ?></p>
                </div>

                <div class="content">
                    <p>Hi there! Here's what happened in your group this week:</p>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['new_members'] ?></div>
                            <div class="stat-label">New Members</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['new_posts'] ?></div>
                            <div class="stat-label">New Posts</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['new_discussions'] ?></div>
                            <div class="stat-label">New Discussions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $stats['total_engagement'] ?></div>
                            <div class="stat-label">Total Engagement</div>
                        </div>
                    </div>

                    <?php if (!empty($contributors)): ?>
                    <div class="section">
                        <div class="section-title">🏆 Top Contributors</div>
                        <?php foreach ($contributors as $contributor): ?>
                        <div class="contributor">
                            <div class="contributor-avatar"></div>
                            <div>
                                <div class="contributor-name">
                                    <?= htmlspecialchars($contributor['first_name'] . ' ' . $contributor['last_name']) ?>
                                </div>
                                <div class="contributor-stats">
                                    <?= $contributor['post_count'] ?> posts, <?= $contributor['comment_count'] ?> comments
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($actions)): ?>
                    <div class="section">
                        <div class="section-title">⚠️ Actions Needed</div>
                        <?php foreach ($actions as $action): ?>
                        <div class="action-item">
                            <strong><?= $action['message'] ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <center>
                        <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>/analytics" class="cta-button">
                            View Full Analytics →
                        </a>
                    </center>
                </div>

                <div class="footer">
                    <p>You're receiving this because you're an owner/admin of <?= htmlspecialchars($group['name']) ?>.</p>
                    <p><a href="<?= $basePath ?>/settings/notifications">Manage email preferences</a></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get owner email address
     */
    private static function getOwnerEmail($userId)
    {
        $user = Database::query(
            "SELECT email FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        return $user['email'] ?? null;
    }

    /**
     * Generate custom date range report
     *
     * @param int $groupId
     * @param string $startDate Y-m-d format
     * @param string $endDate Y-m-d format
     * @return array Report data
     */
    public static function generateCustomReport($groupId, $startDate, $endDate)
    {
        $group = Database::query("SELECT * FROM `groups` WHERE id = ?", [$groupId])->fetch();

        if (!$group) {
            return ['success' => false, 'error' => 'Group not found'];
        }

        $report = [
            'group' => $group,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'metrics' => [],
        ];

        // Member growth
        $report['metrics']['member_growth'] = Database::query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM group_members
            WHERE group_id = ?
            AND status = 'active'
            AND created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$groupId, $startDate, $endDate])->fetchAll();

        // Activity summary
        try {
            $report['metrics']['posts'] = Database::query("
                SELECT COUNT(*) as count FROM feed_posts
                WHERE group_id = ?
                AND created_at BETWEEN ? AND ?
            ", [$groupId, $startDate, $endDate])->fetchColumn();
        } catch (\Exception $e) {
            $report['metrics']['posts'] = 0;
        }

        try {
            $report['metrics']['discussions'] = Database::query("
                SELECT COUNT(*) as count FROM group_discussions
                WHERE group_id = ?
                AND created_at BETWEEN ? AND ?
            ", [$groupId, $startDate, $endDate])->fetchColumn();
        } catch (\Exception $e) {
            $report['metrics']['discussions'] = 0;
        }

        // Top contributors in period
        $report['metrics']['top_contributors'] = self::getTopContributorsInPeriod(
            $groupId,
            $startDate,
            $endDate,
            10
        );

        return ['success' => true, 'report' => $report];
    }

    /**
     * Get top contributors in a specific date range
     */
    private static function getTopContributorsInPeriod($groupId, $startDate, $endDate, $limit = 10)
    {
        try {
            return Database::query("
                SELECT
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    (SELECT COUNT(*) FROM feed_posts
                     WHERE user_id = u.id AND group_id = ?
                     AND created_at BETWEEN ? AND ?) as posts,
                    (SELECT COUNT(*) FROM comments
                     WHERE user_id = u.id AND content_type = 'post'
                     AND content_id IN (SELECT id FROM feed_posts WHERE group_id = ?)
                     AND created_at BETWEEN ? AND ?) as comments
                FROM users u
                JOIN group_members gm ON u.id = gm.user_id
                WHERE gm.group_id = ? AND gm.status = 'active'
                HAVING (posts + comments) > 0
                ORDER BY (posts + comments) DESC
                LIMIT ?
            ", [$groupId, $startDate, $endDate, $groupId, $startDate, $endDate, $groupId, $limit])->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Send weekly digests to all group owners (cron job)
     *
     * @return array Stats about sent emails
     */
    public static function sendAllWeeklyDigests()
    {
        $tenantId = TenantContext::getId();

        // Get all active groups with their owners
        $groups = Database::query("
            SELECT DISTINCT g.id, g.owner_id
            FROM `groups` g
            WHERE g.tenant_id = ?
            AND g.owner_id IS NOT NULL
        ", [$tenantId])->fetchAll();

        $sent = 0;
        $failed = 0;

        foreach ($groups as $group) {
            $digest = self::generateWeeklyDigest($group['id'], $group['owner_id']);

            if ($digest['success'] && !empty($digest['to'])) {
                // Send email (integrate with your email service)
                $emailSent = self::sendEmail($digest['to'], $digest['subject'], $digest['html']);

                if ($emailSent) {
                    $sent++;
                } else {
                    $failed++;
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total_groups' => count($groups),
        ];
    }

    /**
     * Send email (integrate with your email service)
     */
    private static function sendEmail($to, $subject, $html)
    {
        // TODO: Integrate with your email service (SMTP, SendGrid, etc.)
        // For now, return true to simulate success
        return true;
    }
}
