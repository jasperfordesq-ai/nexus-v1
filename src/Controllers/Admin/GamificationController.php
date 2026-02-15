<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Models\User;
use Nexus\Models\UserBadge;
use Nexus\Services\GamificationService;
use Nexus\Services\AchievementAnalyticsService;
use Nexus\Services\AchievementCampaignService;

class GamificationController
{
    private function requireAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']);
        $isSuper = !empty($_SESSION['is_super_admin']);
        $isAdminSession = !empty($_SESSION['is_admin']);

        if (!$isAdmin && !$isSuper && !$isAdminSession) {
            header('HTTP/1.0 403 Forbidden');
            echo "<h1>403 Forbidden</h1><p>Access Denied.</p>";
            exit;
        }
    }

    /**
     * Admin gamification dashboard
     */
    public function index()
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();

        // Get stats
        $totalUsers = Database::query(
            "SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND is_approved = 1",
            [$tenantId]
        )->fetch()['count'] ?? 0;

        $totalBadgesAwarded = Database::query(
            "SELECT COUNT(*) as count FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?",
            [$tenantId]
        )->fetch()['count'] ?? 0;

        $usersWithBadges = Database::query(
            "SELECT COUNT(DISTINCT ub.user_id) as count FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?",
            [$tenantId]
        )->fetch()['count'] ?? 0;

        // Get badge distribution
        $badgeStats = Database::query(
            "SELECT ub.badge_key, ub.name, ub.icon, COUNT(*) as count
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?
             GROUP BY ub.badge_key, ub.name, ub.icon
             ORDER BY count DESC
             LIMIT 20",
            [$tenantId]
        )->fetchAll();

        // Get recent badge awards
        $recentAwards = Database::query(
            "SELECT ub.*, u.first_name, u.last_name, u.avatar_url
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?
             ORDER BY ub.awarded_at DESC
             LIMIT 10",
            [$tenantId]
        )->fetchAll();

        // Get XP leaderboard
        $xpLeaders = Database::query(
            "SELECT id, first_name, last_name, avatar_url, xp, level
             FROM users
             WHERE tenant_id = ? AND is_approved = 1
             ORDER BY xp DESC
             LIMIT 10",
            [$tenantId]
        )->fetchAll();

        // Get all badge definitions
        $allBadges = GamificationService::getBadgeDefinitions();

        // Get all users for bulk operations
        $allUsers = User::getAll();

        View::render('admin/gamification/index', [
            'pageTitle' => 'Gamification Admin',
            'totalUsers' => $totalUsers,
            'totalBadgesAwarded' => $totalBadgesAwarded,
            'usersWithBadges' => $usersWithBadges,
            'badgeStats' => $badgeStats,
            'recentAwards' => $recentAwards,
            'xpLeaders' => $xpLeaders,
            'allBadges' => $allBadges,
            'allUsers' => $allUsers,
        ]);
    }

    /**
     * Recheck badges for all users
     */
    public function recheckAll()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $users = User::getAll();
        $processed = 0;
        $badgesAwarded = 0;

        foreach ($users as $user) {
            try {
                $beforeCount = count(UserBadge::getForUser($user['id']));
                GamificationService::runAllBadgeChecks($user['id']);
                $afterCount = count(UserBadge::getForUser($user['id']));
                $badgesAwarded += ($afterCount - $beforeCount);
                $processed++;
            } catch (\Throwable $e) {
                error_log("Badge recheck failed for user {$user['id']}: " . $e->getMessage());
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?rechecked=' . $processed . '&awarded=' . $badgesAwarded);
        exit;
    }

    /**
     * Bulk award badge to selected users
     */
    public function bulkAward()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userIds = $_POST['user_ids'] ?? [];
        $badgeKey = $_POST['badge_key'] ?? '';

        if (empty($userIds) || empty($badgeKey)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?error=missing_data');
            exit;
        }

        $badge = GamificationService::getBadgeByKey($badgeKey);
        if (!$badge) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?error=invalid_badge');
            exit;
        }

        $awarded = 0;
        foreach ($userIds as $userId) {
            if (!UserBadge::hasBadge($userId, $badgeKey)) {
                UserBadge::award($userId, $badgeKey, $badge['name'], $badge['icon']);

                $basePath = TenantContext::getBasePath();
                \Nexus\Models\Notification::create(
                    $userId,
                    "You were awarded the '{$badge['name']}' badge! {$badge['icon']}",
                    "{$basePath}/achievements/badges",
                    "achievement"
                );
                $awarded++;
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?bulk_awarded=' . $awarded);
        exit;
    }

    /**
     * Award badge to all users
     */
    public function awardToAll()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $badgeKey = $_POST['badge_key'] ?? '';

        if (empty($badgeKey)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?error=missing_badge');
            exit;
        }

        $badge = GamificationService::getBadgeByKey($badgeKey);
        if (!$badge) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?error=invalid_badge');
            exit;
        }

        $users = User::getAll();
        $awarded = 0;

        foreach ($users as $user) {
            if (!UserBadge::hasBadge($user['id'], $badgeKey)) {
                UserBadge::award($user['id'], $badgeKey, $badge['name'], $badge['icon']);

                $basePath = TenantContext::getBasePath();
                \Nexus\Models\Notification::create(
                    $user['id'],
                    "You were awarded the '{$badge['name']}' badge! {$badge['icon']}",
                    "{$basePath}/achievements/badges",
                    "achievement"
                );
                $awarded++;
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?all_awarded=' . $awarded);
        exit;
    }

    /**
     * Reset XP for a user
     */
    public function resetXp()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userId = $_POST['user_id'] ?? null;

        if ($userId) {
            Database::query("UPDATE users SET xp = 0, level = 1 WHERE id = ?", [$userId]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?xp_reset=1');
        exit;
    }

    /**
     * Remove all badges from a user
     */
    public function clearBadges()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userId = $_POST['user_id'] ?? null;

        if ($userId) {
            Database::query("DELETE FROM user_badges WHERE user_id = ?", [$userId]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification?badges_cleared=1');
        exit;
    }

    /**
     * Achievement analytics dashboard
     */
    public function analytics()
    {
        $this->requireAdmin();

        $data = AchievementAnalyticsService::getDashboardData();

        View::render('admin/gamification/analytics', [
            'pageTitle' => 'Achievement Analytics',
            'data' => $data,
        ]);
    }

    /**
     * Campaign management page
     */
    public function campaigns()
    {
        $this->requireAdmin();

        $campaigns = AchievementCampaignService::getCampaigns();
        $badges = GamificationService::getBadgeDefinitions();

        View::render('admin/gamification/campaigns', [
            'pageTitle' => 'Achievement Campaigns',
            'campaigns' => $campaigns,
            'badges' => $badges,
        ]);
    }

    /**
     * Create campaign page
     */
    public function createCampaign()
    {
        $this->requireAdmin();

        $badges = GamificationService::getBadgeDefinitions();

        View::render('admin/gamification/campaign-form', [
            'pageTitle' => 'Create Campaign',
            'campaign' => null,
            'badges' => $badges,
        ]);
    }

    /**
     * Edit campaign page
     */
    public function editCampaign($id)
    {
        $this->requireAdmin();

        $campaign = AchievementCampaignService::getCampaign($id);
        if (!$campaign) {
            header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns');
            exit;
        }

        $badges = GamificationService::getBadgeDefinitions();

        View::render('admin/gamification/campaign-form', [
            'pageTitle' => 'Edit Campaign',
            'campaign' => $campaign,
            'badges' => $badges,
        ]);
    }

    /**
     * Save campaign (create or update)
     */
    public function saveCampaign()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $campaignId = $_POST['campaign_id'] ?? null;
        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'type' => $_POST['type'] ?? 'one_time',
            'badge_key' => $_POST['badge_key'] ?? null,
            'xp_amount' => (int)($_POST['xp_amount'] ?? 0),
            'target_audience' => $_POST['target_audience'] ?? 'all_users',
            'audience_config' => $_POST['audience_config'] ?? [],
            'schedule' => $_POST['schedule'] ?? null,
        ];

        if ($campaignId) {
            AchievementCampaignService::updateCampaign($campaignId, $data);
        } else {
            AchievementCampaignService::createCampaign($data);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns?saved=1');
        exit;
    }

    /**
     * Activate a campaign
     */
    public function activateCampaign()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $campaignId = $_POST['campaign_id'] ?? null;
        if ($campaignId) {
            AchievementCampaignService::activateCampaign($campaignId);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns?activated=1');
        exit;
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $campaignId = $_POST['campaign_id'] ?? null;
        if ($campaignId) {
            AchievementCampaignService::pauseCampaign($campaignId);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns?paused=1');
        exit;
    }

    /**
     * Delete a campaign
     */
    public function deleteCampaign()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $campaignId = $_POST['campaign_id'] ?? null;
        if ($campaignId) {
            AchievementCampaignService::deleteCampaign($campaignId);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns?deleted=1');
        exit;
    }

    /**
     * Run a campaign manually
     */
    public function runCampaign()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $campaignId = $_POST['campaign_id'] ?? null;
        $result = ['awarded' => 0];

        if ($campaignId) {
            $result = AchievementCampaignService::runCampaign($campaignId);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin-legacy/gamification/campaigns?run=1&awarded=' . ($result['awarded'] ?? 0));
        exit;
    }

    /**
     * Preview audience count for campaign
     */
    public function previewAudience()
    {
        $this->requireAdmin();

        $campaign = [
            'target_audience' => $_POST['target_audience'] ?? 'all_users',
            'audience_config' => json_encode($_POST['audience_config'] ?? []),
        ];

        $users = AchievementCampaignService::getTargetUsers($campaign);

        header('Content-Type: application/json');
        echo json_encode(['count' => count($users)]);
        exit;
    }
}
