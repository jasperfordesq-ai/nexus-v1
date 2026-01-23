<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\View;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Core\AdminAuth;
use Nexus\Models\User;
use Nexus\Helpers\UrlHelper;

class UserController
{
    private function requireAdmin()
    {
        AdminAuth::requireAdmin();
    }

    public function index()
    {
        $this->requireAdmin();

        // Get filter from query parameter
        $filter = $_GET['filter'] ?? 'all';

        // Get users based on filter
        if ($filter === 'pending') {
            $users = User::getPendingUsers();
        } elseif ($filter === 'approved') {
            $users = User::getApprovedUsers();
        } elseif ($filter === 'admins') {
            $users = User::getAdminUsers();
        } else {
            $users = User::getAll();
        }

        View::render('admin/users/index', [
            'users' => $users,
            'filter' => $filter
        ]);
    }

    /**
     * Show the create user form
     */
    public function create()
    {
        $this->requireAdmin();
        View::render('admin/users/create', []);
    }

    /**
     * Store a new user created by admin
     */
    public function store()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $status = $_POST['status'] ?? 1;
        $location = trim($_POST['location'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $sendWelcomeEmail = isset($_POST['send_welcome_email']);

        // Super admin can only be granted by existing super admins
        $isSuperAdmin = false;
        if (!empty($_SESSION['is_super_admin']) && isset($_POST['is_super_admin']) && $role === 'admin') {
            $isSuperAdmin = (bool)$_POST['is_super_admin'];
        }

        // Validation
        $errors = [];
        if (empty($firstName)) $errors[] = 'First name is required.';
        if (empty($lastName)) $errors[] = 'Last name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

        // Check if email already exists
        $existingUser = User::findByEmail($email);
        if ($existingUser) {
            $errors[] = 'A user with this email already exists.';
        }

        if (!empty($errors)) {
            View::render('admin/users/create', [
                'errors' => $errors,
                'old' => $_POST
            ]);
            return;
        }

        // Create the user
        User::create($firstName, $lastName, $email, $password, $location ?: null, $phone ?: null);

        // Get the newly created user
        $newUser = User::findByEmail($email);

        if ($newUser) {
            // Update role, status, and super admin flag
            User::updateAdminFields($newUser['id'], $role, $status, $isSuperAdmin);

            // Record GDPR consents (admin-created accounts)
            try {
                $gdprService = new \Nexus\Services\Enterprise\GdprService();
                $consentText = "Account created by administrator. User agrees to Terms of Service and Privacy Policy upon first login.";
                $consentVersion = '1.0';
                $gdprService->recordConsent($newUser['id'], 'terms_of_service', true, $consentText, $consentVersion);
                $gdprService->recordConsent($newUser['id'], 'privacy_policy', true, $consentText, $consentVersion);
            } catch (\Throwable $e) {
                error_log("GDPR Consent Recording Failed for admin-created user: " . $e->getMessage());
            }

            // Send welcome email if requested
            if ($sendWelcomeEmail) {
                try {
                    $tenant = TenantContext::get();
                    $tenantName = $tenant['name'] ?? 'Project NEXUS';
                    $loginLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . TenantContext::getBasePath() . "/login";

                    $html = \Nexus\Core\EmailTemplate::render(
                        "Your Account Has Been Created",
                        "Welcome to {$tenantName}!",
                        "<p>Hello <strong>{$firstName}</strong>,</p>
                        <p>An administrator has created an account for you on {$tenantName}.</p>
                        <p>Your login credentials are:</p>
                        <ul>
                            <li><strong>Email:</strong> {$email}</li>
                            <li><strong>Password:</strong> {$password}</li>
                        </ul>
                        <p>We recommend changing your password after your first login.</p>",
                        "Login Now",
                        $loginLink,
                        "Project NEXUS"
                    );

                    $mailer = new \Nexus\Core\Mailer();
                    $mailer->send($email, "Your account on {$tenantName}", $html);
                } catch (\Throwable $e) {
                    error_log("Welcome email failed for admin-created user: " . $e->getMessage());
                }
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?created=1');
        exit;
    }

    public function edit($id)
    {
        $this->requireAdmin();
        $user = User::findById($id);

        if (!$user) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users');
            exit;
        }

        // Fetch Badges for Management
        $badges = \Nexus\Models\UserBadge::getForUser($id);
        $availableBadges = \Nexus\Services\GamificationService::getBadgeDefinitions();

        View::render('admin/users/edit', [
            'user' => $user,
            'badges' => $badges,
            'availableBadges' => $availableBadges
        ]);
    }

    /**
     * Show user permissions editor
     */
    public function permissions($id)
    {
        $this->requireAdmin();
        $user = User::findById($id);

        if (!$user) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users');
            exit;
        }

        View::render('admin/users/permissions', ['user' => $user]);
    }

    public function update()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        $user = User::findById($id);
        if (!$user) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users');
            exit;
        }

        // PROTECT EXISTING DATA - Only update fields that were actually submitted
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $role = $_POST['role'] ?? 'member';
        $status = $_POST['status'] ?? 0;

        // Super admin can only be granted/revoked by existing super admins
        $isSuperAdmin = $user['is_super_admin'] ?? false; // Preserve existing value by default
        if (!empty($_SESSION['is_god'])) {
            // Only god users can change super admin status
            // Check if "super_admin" was selected as role (new approach)
            if ($role === 'super_admin') {
                $isSuperAdmin = true;
                // Preserve original admin role type (admin or tenant_admin)
                $role = $user['role'] === 'tenant_admin' ? 'tenant_admin' : 'admin';
            } elseif (in_array($role, ['admin', 'tenant_admin'])) {
                // Check checkbox for super admin (old approach - backward compatible)
                $isSuperAdmin = isset($_POST['is_super_admin']) && $_POST['is_super_admin'] === '1';
            } else {
                // If role is not admin, remove super admin status
                $isSuperAdmin = false;
            }
        } elseif (!empty($_SESSION['is_super_admin'])) {
            // Non-god super admins can only change role, not super admin status
            // Preserve existing is_super_admin value
            if ($role === 'super_admin') {
                // Preserve original admin role type
                $role = $user['role'] === 'tenant_admin' ? 'tenant_admin' : 'admin';
            }
        }

        // Preserve existing values if new values are empty
        $firstName = $firstName !== '' ? $firstName : ($user['first_name'] ?? '');
        $lastName = $lastName !== '' ? $lastName : ($user['last_name'] ?? '');

        // For optional fields, preserve if not in POST
        $location = isset($_POST['location']) ? $_POST['location'] : ($user['location'] ?? '');
        $phone = isset($_POST['phone']) ? $_POST['phone'] : ($user['phone'] ?? '');

        // Preserve fields that aren't in the admin form
        $bio = $user['bio'] ?? '';
        $profileType = $user['profile_type'] ?? 'individual';
        $orgName = $user['organization_name'] ?? '';

        User::updateProfile($id, $firstName, $lastName, $bio, $location, $phone, $profileType, $orgName);

        User::updateAdminFields($id, $role, $status, $isSuperAdmin);

        header('Location: ' . TenantContext::getBasePath() . '/admin/users');
        exit;
    }

    public function delete()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Prevent deleting self
        if ($id == $_SESSION['user_id']) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=cannot_delete_self');
            exit;
        }

        // Fetch target user to check privileges
        $targetUser = User::findById($id);

        if (!$targetUser) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=user_not_found');
            exit;
        }

        // Check if current user can manage the target user
        if (!AdminAuth::canManageUser($targetUser)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=insufficient_privileges');
            exit;
        }

        User::delete($id);
        header('Location: ' . TenantContext::getBasePath() . '/admin/users?deleted=1');
        exit;
    }

    public function approve()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Fetch user first for email
        $target = User::findById($id);

        if ($target) {
            User::updateAdminFields($id, 'member', 1);

            // Send Approval Email
            try {
                $tenant = TenantContext::get();
                $tenantName = $tenant['name'] ?? 'Project NEXUS';
                $config = json_decode($tenant['configuration'] ?? '{}', true);
                $welcomeConfig = $config['welcome_email'] ?? [];

                $subject = !empty($welcomeConfig['subject']) ? $welcomeConfig['subject'] : "Account Approved - Welcome to $tenantName";

                if (!empty($welcomeConfig['body'])) {
                    $mainMessage = $welcomeConfig['body'];
                } else {
                    $mainMessage = "Congratulations, <strong>{$target['name']}</strong>! Your account has been approved by the administrators.<br><br>You can now login and complete your onboarding profile to join the community.";
                }

                $loginLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . TenantContext::getBasePath() . "/login";

                // Check for Raw HTML override (if user provided full template)
                if (stripos($mainMessage, '<!DOCTYPE') !== false || stripos($mainMessage, '<html') !== false) {
                    $html = $mainMessage;
                } else {
                    $html = \Nexus\Core\EmailTemplate::render(
                        "Account Approved!",
                        "You are now a member of $tenantName",
                        $mainMessage,
                        "Login & Get Started",
                        $loginLink,
                        "Project NEXUS"
                    );
                }

                $mailer = new \Nexus\Core\Mailer();
                $mailer->send($target['email'], $subject, $html);
            } catch (\Throwable $e) {
                error_log("Approval Email Failed: " . $e->getMessage());
            }
        }

        $referer = UrlHelper::safeReferer('/admin');
        header('Location: ' . $referer);
        exit;
    }

    public function addBadge()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        try {
            $userId = $_POST['user_id'];
            $name = trim($_POST['badge_name']);
            $icon = trim($_POST['badge_icon']);

            if ($userId && $name && $icon) {
                // Use provided key (e.g., standard system badge) or generate custom one
                $badgeKey = !empty($_POST['badge_key'])
                    ? $_POST['badge_key']
                    : 'man_' . time() . '_' . substr(md5($name), 0, 5);

                // Prevent duplicates for Standard Badges if key is provided
                if (!empty($_POST['badge_key']) && \Nexus\Models\UserBadge::hasBadge($userId, $badgeKey)) {
                    // Already has badge, just redirect back
                    header('Location: ' . TenantContext::getBasePath() . '/admin/users/' . $userId . '/edit?error=already_has_badge');
                    exit;
                }

                \Nexus\Models\UserBadge::award($userId, $badgeKey, $name, $icon);

                $basePath = \Nexus\Core\TenantContext::getBasePath();
                \Nexus\Models\Notification::create(
                    $userId,
                    "You were awarded the '{$name}' badge! {$icon}",
                    "{$basePath}/profile/me",
                    "achievement"
                );

                // Optional: Email with separate safety
                try {
                    $user = \Nexus\Models\User::findById($userId);
                    if ($user && !empty($user['email'])) {
                        $mailer = new \Nexus\Core\Mailer();
                        $body = \Nexus\Core\EmailTemplate::render(
                            "You earned a badge!",
                            "An administrator has awarded you a new badge.",
                            "<p style='font-size: 18px; text-align: center;'>You have been awarded the <strong>{$name}</strong> badge!</p>
                             <p style='font-size: 48px; text-align: center; margin: 20px 0;'>{$icon}</p>
                             <p>Log in to your profile to view your latest achievement.</p>",
                            "View My Badges",
                            (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $basePath . "/profile/me"
                        );
                        $mailer->send($user['email'], "You earned a new badge! {$icon}", $body);
                    }
                } catch (\Throwable $e) {
                    error_log("Failed to send manual badge email: " . $e->getMessage());
                }
            }

            header('Location: ' . TenantContext::getBasePath() . '/admin/users/' . $userId . '/edit?badge_added=true');
            exit;
        } catch (\Throwable $e) {
            // Check if it is a missing class error
            error_log("Add Badge Failed: " . $e->getMessage());
            header('Location: ' . TenantContext::getBasePath() . '/admin/users/' . $_POST['user_id'] . '/edit?error=system_error&msg=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function removeBadge()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userId = $_POST['user_id'];
        $badgeKey = $_POST['badge_key'];

        if ($userId && $badgeKey) {
            // We need a remove method in UserBadge model, or execute query directly here for now as explicit model update wasn't planned but is needed.
            // Direct query for MVP speed, matching project pattern
            \Nexus\Core\Database::query("DELETE FROM user_badges WHERE user_id = ? AND badge_key = ?", [$userId, $badgeKey]);
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/users/' . $userId . '/edit?badge_removed=true');
        exit;
    }

    /**
     * Run all badge checks for a user to catch up on any missed badges
     */
    public function recheckBadges()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userId = $_POST['user_id'];

        if ($userId) {
            try {
                \Nexus\Services\GamificationService::runAllBadgeChecks($userId);
            } catch (\Throwable $e) {
                error_log("Badge recheck failed: " . $e->getMessage());
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/users/' . $userId . '/edit?badges_rechecked=true');
        exit;
    }

    /**
     * Bulk award a badge to multiple users
     */
    public function bulkAwardBadge()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $userIds = $_POST['user_ids'] ?? [];
        $badgeKey = $_POST['badge_key'] ?? '';

        if (empty($userIds) || empty($badgeKey)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=missing_data');
            exit;
        }

        $badge = \Nexus\Services\GamificationService::getBadgeByKey($badgeKey);
        if (!$badge) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=invalid_badge');
            exit;
        }

        $awarded = 0;
        foreach ($userIds as $userId) {
            if (!\Nexus\Models\UserBadge::hasBadge($userId, $badgeKey)) {
                \Nexus\Models\UserBadge::award($userId, $badgeKey, $badge['name'], $badge['icon']);

                $basePath = TenantContext::getBasePath();
                \Nexus\Models\Notification::create(
                    $userId,
                    "You were awarded the '{$badge['name']}' badge! {$badge['icon']}",
                    "{$basePath}/profile/me",
                    "achievement"
                );
                $awarded++;
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?bulk_awarded=' . $awarded);
        exit;
    }

    /**
     * Run badge checks for all users (catch-up for existing users)
     */
    public function recheckAllBadges()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $users = User::getAll();
        $processed = 0;

        foreach ($users as $user) {
            try {
                \Nexus\Services\GamificationService::runAllBadgeChecks($user['id']);
                $processed++;
            } catch (\Throwable $e) {
                error_log("Badge recheck failed for user {$user['id']}: " . $e->getMessage());
            }
        }

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?all_rechecked=' . $processed);
        exit;
    }

    /**
     * Suspend a user account
     * God users can suspend anyone including super admins
     */
    public function suspend()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Prevent suspending self
        if ($id == $_SESSION['user_id']) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=cannot_suspend_self');
            exit;
        }

        $targetUser = User::findById($id);

        if (!$targetUser) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=user_not_found');
            exit;
        }

        // Check if current user can manage the target user
        if (!AdminAuth::canManageUser($targetUser)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=insufficient_privileges');
            exit;
        }

        // Update status to suspended
        \Nexus\Core\Database::query(
            "UPDATE users SET status = 'suspended' WHERE id = ?",
            [$id]
        );

        // Log the action
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'suspend_user', "Suspended user #{$id}: {$targetUser['email']}");

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?suspended=1');
        exit;
    }

    /**
     * Ban a user account (permanent)
     * God users can ban anyone including super admins
     */
    public function ban()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Prevent banning self
        if ($id == $_SESSION['user_id']) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=cannot_ban_self');
            exit;
        }

        $targetUser = User::findById($id);

        if (!$targetUser) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=user_not_found');
            exit;
        }

        // Check if current user can manage the target user
        if (!AdminAuth::canManageUser($targetUser)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=insufficient_privileges');
            exit;
        }

        // Update status to banned
        \Nexus\Core\Database::query(
            "UPDATE users SET status = 'banned' WHERE id = ?",
            [$id]
        );

        // Log the action
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'ban_user', "Banned user #{$id}: {$targetUser['email']}");

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?banned=1');
        exit;
    }

    /**
     * Reactivate a suspended/banned user
     */
    public function reactivate()
    {
        $this->requireAdmin();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        $targetUser = User::findById($id);

        if (!$targetUser) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=user_not_found');
            exit;
        }

        // Check if current user can manage the target user
        if (!AdminAuth::canManageUser($targetUser)) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=insufficient_privileges');
            exit;
        }

        // Update status to active
        \Nexus\Core\Database::query(
            "UPDATE users SET status = 'active' WHERE id = ?",
            [$id]
        );

        // Log the action
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'reactivate_user', "Reactivated user #{$id}: {$targetUser['email']}");

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?reactivated=1');
        exit;
    }

    /**
     * Revoke super admin status (God only)
     */
    public function revokeSuperAdmin()
    {
        AdminAuth::requireGod();
        Csrf::verifyOrDie();

        $id = $_POST['user_id'];

        // Prevent revoking own super admin
        if ($id == $_SESSION['user_id']) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=cannot_revoke_self');
            exit;
        }

        $targetUser = User::findById($id);

        if (!$targetUser) {
            header('Location: ' . TenantContext::getBasePath() . '/admin/users?error=user_not_found');
            exit;
        }

        // Revoke super admin status
        \Nexus\Core\Database::query(
            "UPDATE users SET is_super_admin = 0 WHERE id = ?",
            [$id]
        );

        // Log the action
        \Nexus\Models\ActivityLog::log($_SESSION['user_id'], 'revoke_super_admin', "Revoked super admin from user #{$id}: {$targetUser['email']}");

        header('Location: ' . TenantContext::getBasePath() . '/admin/users?super_admin_revoked=1');
        exit;
    }
}
