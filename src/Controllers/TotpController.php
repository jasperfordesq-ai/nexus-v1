<?php

namespace Nexus\Controllers;

use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Services\TotpService;
use Nexus\Models\User;

/**
 * TotpController - Handles 2FA setup, verification, and settings
 */
class TotpController
{
    /**
     * Show 2FA verification form during login
     * GET /auth/2fa
     */
    public function showVerify(): void
    {
        // Must have pending 2FA session
        if (empty($_SESSION['pending_2fa_user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Check if session expired
        if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
            header('Location: ' . TenantContext::getBasePath() . '/login?error=2fa_timeout');
            exit;
        }

        $data = [
            'title' => 'Two-Factor Authentication',
            'error' => $_GET['error'] ?? null,
            'csrf_token' => Csrf::token()
        ];

        extract($data);
        require __DIR__ . '/../../views/' . layout() . '/auth/totp-verify.php';
    }

    /**
     * Process 2FA verification during login
     * POST /auth/2fa
     */
    public function verify(): void
    {
        Csrf::verifyOrDie();

        if (empty($_SESSION['pending_2fa_user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
            header('Location: ' . TenantContext::getBasePath() . '/login?error=2fa_timeout');
            exit;
        }

        $userId = (int)$_SESSION['pending_2fa_user_id'];
        $code = trim($_POST['code'] ?? '');
        $useBackupCode = !empty($_POST['use_backup_code']);

        if (empty($code)) {
            header('Location: ' . TenantContext::getBasePath() . '/auth/2fa?error=code_required');
            exit;
        }

        // Try verification
        if ($useBackupCode) {
            $result = TotpService::verifyBackupCode($userId, $code);
        } else {
            $result = TotpService::verifyLogin($userId, $code);
        }

        if (!$result['success']) {
            $error = urlencode($result['error'] ?? 'Invalid code');
            header('Location: ' . TenantContext::getBasePath() . '/auth/2fa?error=' . $error);
            exit;
        }

        // 2FA successful - complete login
        $this->completeLogin($userId);

        // Warn if backup codes are low
        if ($useBackupCode && isset($result['codes_remaining']) && $result['codes_remaining'] <= 3) {
            $_SESSION['flash_warning'] = "You have only {$result['codes_remaining']} backup codes remaining. Consider generating new ones.";
        }

        // Redirect to intended destination or dashboard
        $redirect = $_SESSION['login_redirect'] ?? TenantContext::getBasePath() . '/dashboard';
        unset($_SESSION['login_redirect']);
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * Show 2FA setup wizard
     * GET /auth/2fa/setup
     */
    public function showSetup(): void
    {
        // Must have pending setup session OR be logged in
        $userId = $_SESSION['pending_2fa_setup_user_id'] ?? $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Check if pending setup expired
        if (isset($_SESSION['pending_2fa_setup_expires']) && $_SESSION['pending_2fa_setup_expires'] < time()) {
            unset($_SESSION['pending_2fa_setup_user_id'], $_SESSION['pending_2fa_setup_expires']);
            header('Location: ' . TenantContext::getBasePath() . '/login?error=setup_timeout');
            exit;
        }

        // Force refresh if requested OR if QR is old base64 data URI format (not raw SVG)
        $needsRefresh = isset($_GET['refresh']);
        if (!$needsRefresh && !empty($_SESSION['totp_setup_qr'])) {
            // Check for old data URI format or missing SVG tag
            $qr = $_SESSION['totp_setup_qr'];
            if (strpos($qr, 'data:image') === 0 || strpos($qr, '<svg') === false) {
                $needsRefresh = true; // Old format, regenerate
            }
        }

        if ($needsRefresh) {
            unset($_SESSION['totp_setup_secret'], $_SESSION['totp_setup_qr'], $_SESSION['totp_setup_uri']);
        }

        // Initialize setup if not already done
        if (empty($_SESSION['totp_setup_secret']) || empty($_SESSION['totp_setup_qr'])) {
            try {
                $setup = TotpService::initializeSetup($userId);
                $_SESSION['totp_setup_secret'] = $setup['secret'];
                $_SESSION['totp_setup_qr'] = $setup['qr_code'];
                $_SESSION['totp_setup_uri'] = $setup['provisioning_uri'];
            } catch (\Exception $e) {
                error_log("TOTP setup init error: " . $e->getMessage());
                $data = [
                    'title' => 'Setup Error',
                    'error' => 'Failed to initialize 2FA setup. Please try again.',
                    'qr_code' => null,
                    'secret' => null,
                    'csrf_token' => Csrf::token()
                ];
                extract($data);
                require __DIR__ . '/../../views/' . layout() . '/auth/totp-setup.php';
                return;
            }
        }

        $data = [
            'title' => 'Set Up Two-Factor Authentication',
            'qr_code' => $_SESSION['totp_setup_qr'],
            'secret' => $_SESSION['totp_setup_secret'],
            'error' => $_GET['error'] ?? null,
            'csrf_token' => Csrf::token()
        ];

        extract($data);
        require __DIR__ . '/../../views/' . layout() . '/auth/totp-setup.php';
    }

    /**
     * Complete 2FA setup after verification
     * POST /auth/2fa/setup
     */
    public function completeSetup(): void
    {
        Csrf::verifyOrDie();

        $userId = $_SESSION['pending_2fa_setup_user_id'] ?? $_SESSION['user_id'] ?? null;

        if (!$userId) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $code = trim($_POST['code'] ?? '');

        if (empty($code)) {
            header('Location: ' . TenantContext::getBasePath() . '/auth/2fa/setup?error=code_required');
            exit;
        }

        $result = TotpService::completeSetup($userId, $code);

        if (!$result['success']) {
            $error = urlencode($result['error'] ?? 'Verification failed');
            header('Location: ' . TenantContext::getBasePath() . '/auth/2fa/setup?error=' . $error);
            exit;
        }

        // Store backup codes in session for display
        $_SESSION['totp_backup_codes'] = $result['backup_codes'];

        // Clear setup session data
        unset(
            $_SESSION['totp_setup_secret'],
            $_SESSION['totp_setup_qr'],
            $_SESSION['totp_setup_uri']
        );

        // If this was a forced setup during login, complete the login
        if (isset($_SESSION['pending_2fa_setup_user_id'])) {
            unset($_SESSION['pending_2fa_setup_user_id'], $_SESSION['pending_2fa_setup_expires']);
            $this->completeLogin($userId);
        }

        // Redirect to backup codes page
        header('Location: ' . TenantContext::getBasePath() . '/auth/2fa/backup-codes');
        exit;
    }

    /**
     * Show backup codes after setup
     * GET /auth/2fa/backup-codes
     */
    public function showBackupCodes(): void
    {
        // Must be logged in
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Get backup codes from session (only shown once after setup)
        $backupCodes = $_SESSION['totp_backup_codes'] ?? null;

        if ($backupCodes) {
            // Clear from session after displaying
            unset($_SESSION['totp_backup_codes']);
        }

        $data = [
            'title' => 'Backup Codes',
            'backup_codes' => $backupCodes,
            'codes_remaining' => TotpService::getBackupCodeCount($_SESSION['user_id']),
            'csrf_token' => Csrf::token()
        ];

        extract($data);
        require __DIR__ . '/../../views/' . layout() . '/auth/backup-codes.php';
    }

    /**
     * Regenerate backup codes
     * POST /auth/2fa/backup-codes/regenerate
     */
    public function regenerateBackupCodes(): void
    {
        Csrf::verifyOrDie();

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        // Require password confirmation
        $password = $_POST['password'] ?? '';
        $user = User::findById($_SESSION['user_id']);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['flash_error'] = 'Invalid password.';
            header('Location: ' . TenantContext::getBasePath() . '/settings/2fa');
            exit;
        }

        $backupCodes = TotpService::generateBackupCodes($_SESSION['user_id']);
        $_SESSION['totp_backup_codes'] = $backupCodes;

        header('Location: ' . TenantContext::getBasePath() . '/auth/2fa/backup-codes');
        exit;
    }

    /**
     * Show 2FA settings page
     * GET /settings/2fa
     */
    public function settings(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        $data = [
            'title' => '2FA Settings',
            'is_enabled' => TotpService::isEnabled($userId),
            'backup_codes_remaining' => TotpService::getBackupCodeCount($userId),
            'flash_success' => $_SESSION['flash_success'] ?? null,
            'flash_error' => $_SESSION['flash_error'] ?? null,
            'csrf_token' => Csrf::token()
        ];

        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        extract($data);
        require __DIR__ . '/../../views/' . layout() . '/settings/2fa.php';
    }

    /**
     * Disable 2FA - BLOCKED
     * 2FA is mandatory for all users, disabling is not permitted
     * POST /settings/2fa/disable
     */
    public function disable(): void
    {
        // 2FA is mandatory - disabling is not allowed
        $_SESSION['flash_error'] = 'Two-factor authentication is mandatory and cannot be disabled.';
        header('Location: ' . TenantContext::getBasePath() . '/settings/2fa');
        exit;
    }

    /**
     * Complete the login process after successful 2FA
     */
    private function completeLogin(int $userId): void
    {
        $user = User::findById($userId, false);

        if (!$user) {
            header('Location: ' . TenantContext::getBasePath() . '/login?error=user_not_found');
            exit;
        }

        // Clear pending 2FA session
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set full session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'member';
        $_SESSION['role'] = $user['role'] ?? 'member';
        $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
        $_SESSION['is_god'] = $user['is_god'] ?? 0;
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';

        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $_SESSION['is_admin'] = in_array($user['role'], $adminRoles) ? 1 : 0;

        // Log activity
        \Nexus\Models\ActivityLog::log($user['id'], 'login', 'User logged in with 2FA');

        // Gamification
        try {
            \Nexus\Services\StreakService::recordLogin($user['id']);
            \Nexus\Services\GamificationService::checkMembershipBadges($user['id']);
        } catch (\Throwable $e) {
            error_log("Gamification login error: " . $e->getMessage());
        }
    }
}
