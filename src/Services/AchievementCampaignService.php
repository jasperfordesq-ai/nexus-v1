<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class AchievementCampaignService
{
    /**
     * Campaign types
     */
    public const TYPES = [
        'one_time' => 'One Time - Award once to qualifying users',
        'recurring' => 'Recurring - Award on schedule (daily/weekly/monthly)',
        'triggered' => 'Triggered - Award when user meets conditions',
    ];

    /**
     * Target audience options
     */
    public const AUDIENCES = [
        'all_users' => 'All Active Users',
        'new_users' => 'New Users (joined in last 30 days)',
        'active_users' => 'Active Users (logged in this week)',
        'inactive_users' => 'Inactive Users (no login in 30+ days)',
        'level_range' => 'Users at specific level range',
        'badge_holders' => 'Users with specific badge',
        'custom' => 'Custom filter (SQL)',
    ];

    /**
     * Get all campaigns
     */
    public static function getCampaigns($status = null)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM achievement_campaigns WHERE tenant_id = ?";
        $params = [$tenantId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $campaigns = Database::query($sql, $params)->fetchAll();

        // Add 'type' field mapped from campaign_type for view compatibility
        foreach ($campaigns as &$campaign) {
            $campaign['type'] = self::mapCampaignTypeToType($campaign['campaign_type'] ?? 'badge_award');
        }

        return $campaigns;
    }

    /**
     * Get a single campaign
     */
    public static function getCampaign($id)
    {
        $tenantId = TenantContext::getId();

        $campaign = Database::query(
            "SELECT * FROM achievement_campaigns WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if ($campaign) {
            // Add 'type' field mapped from campaign_type for view compatibility
            $campaign['type'] = self::mapCampaignTypeToType($campaign['campaign_type'] ?? 'badge_award');
        }

        return $campaign;
    }

    /**
     * Create a new campaign
     */
    public static function createCampaign($data)
    {
        $tenantId = TenantContext::getId();

        // Map form type to database campaign_type
        $campaignType = self::mapTypeToCampaignType($data['type'] ?? 'one_time');

        Database::query(
            "INSERT INTO achievement_campaigns
             (tenant_id, name, description, campaign_type, badge_key, xp_amount, target_audience, audience_config, schedule, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())",
            [
                $tenantId,
                $data['name'],
                $data['description'] ?? '',
                $campaignType,
                $data['badge_key'] ?: null,
                $data['xp_amount'] ?? 0,
                $data['target_audience'] ?? 'all_users',
                json_encode($data['audience_config'] ?? []),
                $data['schedule'] ?? null,
            ]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Map form type to database campaign_type enum
     */
    private static function mapTypeToCampaignType($type)
    {
        $map = [
            'one_time' => 'badge_award',
            'recurring' => 'xp_bonus',
            'triggered' => 'challenge',
        ];
        return $map[$type] ?? 'badge_award';
    }

    /**
     * Map database campaign_type to form type
     */
    private static function mapCampaignTypeToType($campaignType)
    {
        $map = [
            'badge_award' => 'one_time',
            'xp_bonus' => 'recurring',
            'challenge' => 'triggered',
        ];
        return $map[$campaignType] ?? 'one_time';
    }

    /**
     * Update a campaign
     */
    public static function updateCampaign($id, $data)
    {
        $tenantId = TenantContext::getId();

        // Map form type to database campaign_type
        $campaignType = self::mapTypeToCampaignType($data['type'] ?? 'one_time');

        Database::query(
            "UPDATE achievement_campaigns SET
             name = ?, description = ?, campaign_type = ?, badge_key = ?, xp_amount = ?,
             target_audience = ?, audience_config = ?, schedule = ?
             WHERE id = ? AND tenant_id = ?",
            [
                $data['name'],
                $data['description'] ?? '',
                $campaignType,
                $data['badge_key'] ?: null,
                $data['xp_amount'] ?? 0,
                $data['target_audience'] ?? 'all_users',
                json_encode($data['audience_config'] ?? []),
                $data['schedule'] ?? null,
                $id,
                $tenantId,
            ]
        );
    }

    /**
     * Activate a campaign
     */
    public static function activateCampaign($id)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE achievement_campaigns SET status = 'active', activated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    /**
     * Pause a campaign
     */
    public static function pauseCampaign($id)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE achievement_campaigns SET status = 'paused' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    /**
     * Delete a campaign
     */
    public static function deleteCampaign($id)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "DELETE FROM achievement_campaigns WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
    }

    /**
     * Run a campaign (for cron or manual execution)
     */
    public static function runCampaign($id)
    {
        $campaign = self::getCampaign($id);

        if (!$campaign || $campaign['status'] !== 'active') {
            return ['success' => false, 'error' => 'Campaign not active'];
        }

        // Get target users
        $users = self::getTargetUsers($campaign);
        $awarded = 0;

        foreach ($users as $user) {
            $userId = $user['id'];

            // Check if already awarded in this campaign
            if (self::hasBeenAwarded($id, $userId)) {
                continue;
            }

            // Award badge if specified
            if (!empty($campaign['badge_key'])) {
                $badge = GamificationService::getBadgeByKey($campaign['badge_key']);
                if ($badge) {
                    GamificationService::awardBadge($userId, $campaign['badge_key']);
                }
            }

            // Award XP if specified
            if ($campaign['xp_amount'] > 0) {
                GamificationService::awardXP(
                    $userId,
                    $campaign['xp_amount'],
                    'campaign',
                    "Campaign: {$campaign['name']}"
                );
            }

            // Record the award
            self::recordAward($id, $userId);
            $awarded++;
        }

        // Update last run time
        Database::query(
            "UPDATE achievement_campaigns SET last_run_at = NOW(), total_awards = total_awards + ? WHERE id = ?",
            [$awarded, $id]
        );

        // If one-time campaign, mark as completed
        if ($campaign['type'] === 'one_time') {
            Database::query(
                "UPDATE achievement_campaigns SET status = 'completed' WHERE id = ?",
                [$id]
            );
        }

        return ['success' => true, 'awarded' => $awarded];
    }

    /**
     * Get users matching campaign criteria
     */
    public static function getTargetUsers($campaign)
    {
        $tenantId = TenantContext::getId();
        $audience = $campaign['target_audience'];
        $config = json_decode($campaign['audience_config'] ?? '{}', true);

        switch ($audience) {
            case 'all_users':
                return Database::query(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1",
                    [$tenantId]
                )->fetchAll();

            case 'new_users':
                return Database::query(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                    [$tenantId]
                )->fetchAll();

            case 'active_users':
                return Database::query(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                    [$tenantId]
                )->fetchAll();

            case 'inactive_users':
                return Database::query(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1 AND (last_login IS NULL OR last_login < DATE_SUB(NOW(), INTERVAL 30 DAY))",
                    [$tenantId]
                )->fetchAll();

            case 'level_range':
                $minLevel = $config['min_level'] ?? 1;
                $maxLevel = $config['max_level'] ?? 100;
                return Database::query(
                    "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1 AND level >= ? AND level <= ?",
                    [$tenantId, $minLevel, $maxLevel]
                )->fetchAll();

            case 'badge_holders':
                $badgeKey = $config['badge_key'] ?? '';
                return Database::query(
                    "SELECT u.id FROM users u
                     JOIN user_badges ub ON u.id = ub.user_id
                     WHERE u.tenant_id = ? AND u.is_approved = 1 AND ub.badge_key = ?",
                    [$tenantId, $badgeKey]
                )->fetchAll();

            default:
                return [];
        }
    }

    /**
     * Check if user has already been awarded in this campaign
     */
    public static function hasBeenAwarded($campaignId, $userId)
    {
        $result = Database::query(
            "SELECT id FROM campaign_awards WHERE campaign_id = ? AND user_id = ?",
            [$campaignId, $userId]
        )->fetch();

        return (bool)$result;
    }

    /**
     * Record that a user was awarded in a campaign
     */
    public static function recordAward($campaignId, $userId)
    {
        Database::query(
            "INSERT INTO campaign_awards (campaign_id, user_id, awarded_at) VALUES (?, ?, NOW())",
            [$campaignId, $userId]
        );
    }

    /**
     * Get campaign statistics
     */
    public static function getCampaignStats($campaignId)
    {
        $campaign = self::getCampaign($campaignId);

        if (!$campaign) {
            return null;
        }

        $targetCount = count(self::getTargetUsers($campaign));

        $awardedCount = Database::query(
            "SELECT COUNT(*) as count FROM campaign_awards WHERE campaign_id = ?",
            [$campaignId]
        )->fetch()['count'] ?? 0;

        return [
            'campaign' => $campaign,
            'target_count' => $targetCount,
            'awarded_count' => $awardedCount,
            'remaining' => max(0, $targetCount - $awardedCount),
        ];
    }

    /**
     * Process all due recurring campaigns (for cron)
     */
    public static function processRecurringCampaigns()
    {
        $tenantId = TenantContext::getId();

        // Get active recurring campaigns that are due
        $campaigns = Database::query(
            "SELECT * FROM achievement_campaigns
             WHERE tenant_id = ? AND status = 'active' AND campaign_type = 'recurring'
             AND (last_run_at IS NULL OR
                  (schedule = 'daily' AND last_run_at < DATE_SUB(NOW(), INTERVAL 1 DAY)) OR
                  (schedule = 'weekly' AND last_run_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)) OR
                  (schedule = 'monthly' AND last_run_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)))",
            [$tenantId]
        )->fetchAll();

        $results = [];

        foreach ($campaigns as $campaign) {
            $result = self::runCampaign($campaign['id']);
            $results[$campaign['id']] = $result;
        }

        return $results;
    }
}
