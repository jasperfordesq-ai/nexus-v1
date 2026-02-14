<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GamificationService;
use Nexus\Services\AchievementCampaignService;

/**
 * AdminGamificationApiController - V2 API for React admin gamification module
 *
 * Provides gamification stats, badge management, campaign CRUD, and bulk operations.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/gamification/stats        - Aggregate gamification stats
 * - GET    /api/v2/admin/gamification/badges        - List all available badges
 * - POST   /api/v2/admin/gamification/badges        - Create custom badge
 * - DELETE  /api/v2/admin/gamification/badges/{id}   - Delete custom badge
 * - GET    /api/v2/admin/gamification/campaigns      - List campaigns
 * - POST   /api/v2/admin/gamification/campaigns      - Create campaign
 * - PUT    /api/v2/admin/gamification/campaigns/{id} - Update campaign
 * - DELETE  /api/v2/admin/gamification/campaigns/{id} - Delete campaign
 * - POST   /api/v2/admin/gamification/recheck-all    - Trigger badge recheck for all users
 * - POST   /api/v2/admin/gamification/bulk-award      - Bulk award badge to users
 */
class AdminGamificationApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/gamification/stats
     *
     * Returns aggregate gamification stats for the admin dashboard.
     */
    public function stats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $totalBadgesAwarded = 0;
        $activeUsers = 0;
        $totalXp = 0;
        $badgeDistribution = [];

        try {
            $totalBadgesAwarded = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM user_badges ub
                 JOIN users u ON ub.user_id = u.id
                 WHERE u.tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // Table may not exist
        }

        try {
            $activeUsers = (int) Database::query(
                "SELECT COUNT(DISTINCT ub.user_id) as cnt FROM user_badges ub
                 JOIN users u ON ub.user_id = u.id
                 WHERE u.tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // Table may not exist
        }

        try {
            $totalXp = (int) Database::query(
                "SELECT COALESCE(SUM(xp), 0) as total FROM users WHERE tenant_id = ?",
                [$tenantId]
            )->fetch()['total'];
        } catch (\Throwable $e) {
            // Column may not exist
        }

        // Active campaigns count
        $activeCampaigns = 0;
        try {
            $activeCampaigns = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM achievement_campaigns WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Badge distribution (top 10)
        try {
            $rows = Database::query(
                "SELECT ub.name as badge_name, COUNT(*) as count
                 FROM user_badges ub
                 JOIN users u ON ub.user_id = u.id
                 WHERE u.tenant_id = ?
                 GROUP BY ub.badge_key, ub.name
                 ORDER BY count DESC
                 LIMIT 10",
                [$tenantId]
            )->fetchAll();

            foreach ($rows as $row) {
                $badgeDistribution[] = [
                    'badge_name' => $row['badge_name'],
                    'count' => (int) $row['count'],
                ];
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        $this->respondWithData([
            'total_badges_awarded' => $totalBadgesAwarded,
            'active_users' => $activeUsers,
            'total_xp_awarded' => $totalXp,
            'active_campaigns' => $activeCampaigns,
            'badge_distribution' => $badgeDistribution,
        ]);
    }

    /**
     * GET /api/v2/admin/gamification/badges
     *
     * Returns all badge definitions (built-in + custom).
     */
    public function badges(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $badges = [];

        // Built-in badge definitions from GamificationService
        try {
            $definitions = GamificationService::getBadgeDefinitions();
            foreach ($definitions as $key => $def) {
                $badges[] = [
                    'id' => null,
                    'key' => $key,
                    'name' => $def['name'] ?? $key,
                    'description' => $def['description'] ?? '',
                    'icon' => $def['icon'] ?? 'award',
                    'type' => 'built_in',
                    'awarded_count' => 0,
                ];
            }
        } catch (\Throwable $e) {
            // Service may fail
        }

        // Custom badges from database
        try {
            $customBadges = Database::query(
                "SELECT * FROM custom_badges WHERE tenant_id = ? ORDER BY created_at DESC",
                [$tenantId]
            )->fetchAll();

            foreach ($customBadges as $cb) {
                $badges[] = [
                    'id' => (int) $cb['id'],
                    'key' => 'custom_' . $cb['id'],
                    'name' => $cb['name'],
                    'description' => $cb['description'] ?? '',
                    'icon' => $cb['icon'] ?? 'award',
                    'type' => 'custom',
                    'awarded_count' => 0,
                ];
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Populate awarded counts
        try {
            $counts = Database::query(
                "SELECT ub.badge_key, COUNT(*) as cnt
                 FROM user_badges ub
                 JOIN users u ON ub.user_id = u.id
                 WHERE u.tenant_id = ?
                 GROUP BY ub.badge_key",
                [$tenantId]
            )->fetchAll();

            $countMap = [];
            foreach ($counts as $row) {
                $countMap[$row['badge_key']] = (int) $row['cnt'];
            }

            foreach ($badges as &$badge) {
                $badge['awarded_count'] = $countMap[$badge['key']] ?? 0;
            }
            unset($badge);
        } catch (\Throwable $e) {
            // Table may not exist
        }

        $this->respondWithData($badges);
    }

    /**
     * POST /api/v2/admin/gamification/badges
     *
     * Create a new custom badge.
     */
    public function createBadge(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $name = trim($this->input('name', ''));
        $description = trim($this->input('description', ''));
        $icon = trim($this->input('icon', 'award'));
        $slug = trim($this->input('slug', ''));

        if (empty($name)) {
            $this->respondWithError('VALIDATION_ERROR', 'Badge name is required', 'name');
        }

        // Auto-generate slug if not provided
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
            $slug = trim($slug, '_');
        }

        try {
            Database::query(
                "INSERT INTO custom_badges (tenant_id, name, description, icon, xp, category, is_active, created_at)
                 VALUES (?, ?, ?, ?, 0, 'custom', 1, NOW())",
                [$tenantId, $name, $description, $icon]
            );

            $id = (int) Database::getInstance()->lastInsertId();

            $this->respondWithData([
                'id' => $id,
                'key' => 'custom_' . $id,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'slug' => $slug,
                'type' => 'custom',
            ], null, 201);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to create badge. The custom_badges table may not exist.', null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/gamification/badges/{id}
     *
     * Delete a custom badge.
     */
    public function deleteBadge(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify badge exists and belongs to tenant
            $badge = Database::query(
                "SELECT * FROM custom_badges WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$badge) {
                $this->respondWithError('NOT_FOUND', 'Badge not found', null, 404);
            }

            // Remove user_badges referencing this custom badge
            try {
                Database::query(
                    "DELETE FROM user_badges WHERE badge_key = ? AND user_id IN (SELECT id FROM users WHERE tenant_id = ?)",
                    ['custom_' . $id, $tenantId]
                );
            } catch (\Throwable $e) {
                // user_badges cleanup may fail, continue
            }

            Database::query(
                "DELETE FROM custom_badges WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            $this->respondWithData(['deleted' => true]);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to delete badge', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/gamification/campaigns
     *
     * List all gamification campaigns.
     */
    public function campaigns(): void
    {
        $this->requireAdmin();

        try {
            $campaigns = AchievementCampaignService::getCampaigns();

            $formatted = array_map(function ($c) {
                return [
                    'id' => (int) $c['id'],
                    'name' => $c['name'] ?? '',
                    'description' => $c['description'] ?? '',
                    'status' => $c['status'] ?? 'draft',
                    'badge_key' => $c['badge_key'] ?? null,
                    'badge_name' => $c['badge_key'] ?? '',
                    'target_audience' => $c['target_audience'] ?? 'all_users',
                    'start_date' => $c['activated_at'] ?? null,
                    'end_date' => null,
                    'total_awards' => (int) ($c['total_awards'] ?? 0),
                    'created_at' => $c['created_at'] ?? '',
                ];
            }, $campaigns);

            $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            // Table may not exist
            $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/gamification/campaigns
     *
     * Create a new campaign.
     */
    public function createCampaign(): void
    {
        $this->requireAdmin();

        $name = trim($this->input('name', ''));
        if (empty($name)) {
            $this->respondWithError('VALIDATION_ERROR', 'Campaign name is required', 'name');
        }

        $data = [
            'name' => $name,
            'description' => trim($this->input('description', '')),
            'type' => $this->input('type', 'one_time'),
            'badge_key' => $this->input('badge_key', ''),
            'xp_amount' => (int) $this->input('xp_amount', 0),
            'target_audience' => $this->input('target_audience', 'all_users'),
            'audience_config' => $this->input('audience_config', []),
            'schedule' => $this->input('schedule'),
        ];

        try {
            $id = AchievementCampaignService::createCampaign($data);

            $this->respondWithData([
                'id' => (int) $id,
                'name' => $data['name'],
                'status' => 'draft',
            ], null, 201);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to create campaign: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/gamification/campaigns/{id}
     *
     * Update a campaign.
     */
    public function updateCampaign(int $id): void
    {
        $this->requireAdmin();

        // Verify campaign exists
        $campaign = AchievementCampaignService::getCampaign($id);
        if (!$campaign) {
            $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        }

        $data = [
            'name' => trim($this->input('name', $campaign['name'])),
            'description' => trim($this->input('description', $campaign['description'] ?? '')),
            'type' => $this->input('type', $campaign['type'] ?? 'one_time'),
            'badge_key' => $this->input('badge_key', $campaign['badge_key'] ?? ''),
            'xp_amount' => (int) $this->input('xp_amount', $campaign['xp_amount'] ?? 0),
            'target_audience' => $this->input('target_audience', $campaign['target_audience'] ?? 'all_users'),
            'audience_config' => $this->input('audience_config', json_decode($campaign['audience_config'] ?? '{}', true)),
            'schedule' => $this->input('schedule', $campaign['schedule'] ?? null),
        ];

        // Handle status change
        $newStatus = $this->input('status');
        if ($newStatus && $newStatus !== $campaign['status']) {
            if ($newStatus === 'active') {
                AchievementCampaignService::activateCampaign($id);
            } elseif ($newStatus === 'paused') {
                AchievementCampaignService::pauseCampaign($id);
            }
        }

        try {
            AchievementCampaignService::updateCampaign($id, $data);
            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to update campaign: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/v2/admin/gamification/campaigns/{id}
     *
     * Delete a campaign.
     */
    public function deleteCampaign(int $id): void
    {
        $this->requireAdmin();

        $campaign = AchievementCampaignService::getCampaign($id);
        if (!$campaign) {
            $this->respondWithError('NOT_FOUND', 'Campaign not found', null, 404);
        }

        try {
            AchievementCampaignService::deleteCampaign($id);
            $this->respondWithData(['deleted' => true]);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Failed to delete campaign', null, 500);
        }
    }

    /**
     * POST /api/v2/admin/gamification/recheck-all
     *
     * Trigger badge recheck for all active users.
     */
    public function recheckAll(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $users = Database::query(
                "SELECT id FROM users WHERE tenant_id = ? AND is_approved = 1",
                [$tenantId]
            )->fetchAll();

            $checked = 0;
            foreach ($users as $user) {
                GamificationService::runAllBadgeChecks((int) $user['id']);
                $checked++;
            }

            $this->respondWithData([
                'users_checked' => $checked,
                'message' => "Badge recheck completed for {$checked} users",
            ]);
        } catch (\Throwable $e) {
            $this->respondWithError('SERVER_ERROR', 'Badge recheck failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/v2/admin/gamification/bulk-award
     *
     * Bulk award a badge to multiple users.
     */
    public function bulkAward(): void
    {
        $this->requireAdmin();

        $badgeSlug = trim($this->input('badge_slug', ''));
        $userIds = $this->input('user_ids', []);

        if (empty($badgeSlug)) {
            $this->respondWithError('VALIDATION_ERROR', 'Badge slug is required', 'badge_slug');
        }

        if (empty($userIds) || !is_array($userIds)) {
            $this->respondWithError('VALIDATION_ERROR', 'User IDs array is required', 'user_ids');
        }

        $awarded = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            try {
                GamificationService::awardBadgeByKey((int) $userId, $badgeSlug);
                $awarded++;
            } catch (\Throwable $e) {
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        $this->respondWithData([
            'awarded' => $awarded,
            'total_requested' => count($userIds),
            'errors' => $errors,
        ]);
    }
}
