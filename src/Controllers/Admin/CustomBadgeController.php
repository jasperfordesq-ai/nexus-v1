<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GamificationService;

class CustomBadgeController
{
    /**
     * List all custom badges
     */
    public function index()
    {
        if (!$this->isAdmin()) {
            header('Location: ' . TenantContext::getBasePath() . '/');
            exit;
        }

        $tenantId = TenantContext::getId();

        $badges = Database::query(
            "SELECT * FROM custom_badges WHERE tenant_id = ? ORDER BY created_at DESC",
            [$tenantId]
        )->fetchAll();

        // Get award counts for each badge
        foreach ($badges as &$badge) {
            $count = Database::query(
                "SELECT COUNT(*) as count FROM user_badges WHERE badge_key = ?",
                ['custom_' . $badge['id']]
            )->fetch();
            $badge['award_count'] = $count['count'] ?? 0;
        }

        View::render('admin/gamification/custom-badges', [
            'pageTitle' => 'Custom Badges',
            'badges' => $badges,
        ]);
    }

    /**
     * Show create badge form
     */
    public function create()
    {
        if (!$this->isAdmin()) {
            header('Location: ' . TenantContext::getBasePath() . '/');
            exit;
        }

        View::render('admin/gamification/custom-badge-form', [
            'pageTitle' => 'Create Custom Badge',
            'badge' => null,
            'isEdit' => false,
        ]);
    }

    /**
     * Store new custom badge
     */
    public function store()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏆');
        $xp = (int)($_POST['xp'] ?? 50);
        $category = trim($_POST['category'] ?? 'special');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name)) {
            $_SESSION['flash_error'] = 'Badge name is required';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges/create');
            exit;
        }

        Database::query(
            "INSERT INTO custom_badges (tenant_id, name, description, icon, xp, category, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [$tenantId, $name, $description, $icon, $xp, $category, $isActive]
        );

        $_SESSION['flash_success'] = 'Custom badge created successfully!';
        header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
        exit;
    }

    /**
     * Show edit badge form
     */
    public function edit($id)
    {
        if (!$this->isAdmin()) {
            header('Location: ' . TenantContext::getBasePath() . '/');
            exit;
        }

        $tenantId = TenantContext::getId();

        $badge = Database::query(
            "SELECT * FROM custom_badges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$badge) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        View::render('admin/gamification/custom-badge-form', [
            'pageTitle' => 'Edit Custom Badge',
            'badge' => $badge,
            'isEdit' => true,
        ]);
    }

    /**
     * Update custom badge
     */
    public function update()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $id = (int)($_POST['id'] ?? 0);

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏆');
        $xp = (int)($_POST['xp'] ?? 50);
        $category = trim($_POST['category'] ?? 'special');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || !$id) {
            $_SESSION['flash_error'] = 'Invalid badge data';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        Database::query(
            "UPDATE custom_badges SET name = ?, description = ?, icon = ?, xp = ?, category = ?, is_active = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$name, $description, $icon, $xp, $category, $isActive, $id, $tenantId]
        );

        $_SESSION['flash_success'] = 'Custom badge updated successfully!';
        header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
        exit;
    }

    /**
     * Delete custom badge
     */
    public function delete()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            $_SESSION['flash_error'] = 'Invalid badge ID';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        // Remove from users first
        Database::query(
            "DELETE FROM user_badges WHERE badge_key = ?",
            ['custom_' . $id]
        );

        // Delete the badge
        Database::query(
            "DELETE FROM custom_badges WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        $_SESSION['flash_success'] = 'Custom badge deleted successfully!';
        header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
        exit;
    }

    /**
     * Award custom badge to user(s)
     */
    public function award()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $tenantId = TenantContext::getId();
        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $userIds = $_POST['user_ids'] ?? [];

        if (!$badgeId || empty($userIds)) {
            $_SESSION['flash_error'] = 'Please select a badge and at least one user';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        // Get badge details
        $badge = Database::query(
            "SELECT * FROM custom_badges WHERE id = ? AND tenant_id = ?",
            [$badgeId, $tenantId]
        )->fetch();

        if (!$badge) {
            $_SESSION['flash_error'] = 'Badge not found';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        $awarded = 0;
        foreach ($userIds as $userId) {
            $userId = (int)$userId;

            // Check if already has badge
            $exists = Database::query(
                "SELECT id FROM user_badges WHERE user_id = ? AND badge_key = ?",
                [$userId, 'custom_' . $badgeId]
            )->fetch();

            if (!$exists) {
                // Award the badge
                Database::query(
                    "INSERT INTO user_badges (tenant_id, user_id, badge_key, earned_at)
                     VALUES (?, ?, ?, NOW())",
                    [$tenantId, $userId, 'custom_' . $badgeId]
                );

                // Award XP
                if ($badge['xp'] > 0) {
                    GamificationService::awardXP(
                        $userId,
                        $badge['xp'],
                        'custom_badge',
                        "Earned badge: {$badge['name']}"
                    );
                }

                // Notify user
                \Nexus\Models\Notification::create(
                    $userId,
                    "{$badge['icon']} You've been awarded the '{$badge['name']}' badge! +{$badge['xp']} XP"
                );

                $awarded++;
            }
        }

        $_SESSION['flash_success'] = "Badge awarded to {$awarded} user(s)!";
        header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
        exit;
    }

    /**
     * Revoke custom badge from user
     */
    public function revoke()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        \Nexus\Core\Csrf::verifyOrDie();

        $badgeId = (int)($_POST['badge_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);

        if (!$badgeId || !$userId) {
            $_SESSION['flash_error'] = 'Invalid parameters';
            header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
            exit;
        }

        Database::query(
            "DELETE FROM user_badges WHERE user_id = ? AND badge_key = ?",
            [$userId, 'custom_' . $badgeId]
        );

        $_SESSION['flash_success'] = 'Badge revoked successfully!';
        header('Location: ' . TenantContext::getBasePath() . '/admin/custom-badges');
        exit;
    }

    /**
     * Get users with a specific badge (AJAX)
     */
    public function getAwardees()
    {
        if (!$this->isAdmin()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        header('Content-Type: application/json');

        $badgeId = (int)($_GET['badge_id'] ?? 0);

        if (!$badgeId) {
            echo json_encode(['success' => false, 'error' => 'Invalid badge ID']);
            return;
        }

        $users = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.photo, ub.earned_at
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE ub.badge_key = ?
             ORDER BY ub.earned_at DESC",
            ['custom_' . $badgeId]
        )->fetchAll();

        echo json_encode(['success' => true, 'users' => $users]);
    }

    /**
     * Check if current user is admin
     */
    private function isAdmin()
    {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $user = Database::query(
            "SELECT role FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        )->fetch();

        return $user && in_array($user['role'], ['admin', 'superadmin']);
    }
}
