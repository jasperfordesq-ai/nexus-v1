<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Csrf;
use Nexus\Services\TotpService;

/**
 * TotpApiController - API endpoints for 2FA operations
 */
class TotpApiController
{
    /**
     * Verify a TOTP code (AJAX)
     * POST /api/totp/verify
     */
    public function verify(): void
    {
        header('Content-Type: application/json');

        // Check CSRF
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Csrf::validate($csrfToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }

        // Must have pending 2FA session
        if (empty($_SESSION['pending_2fa_user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No pending 2FA session']);
            exit;
        }

        // Check session expiry
        if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Session expired', 'redirect' => '/login']);
            exit;
        }

        $userId = (int)$_SESSION['pending_2fa_user_id'];
        $code = trim($_POST['code'] ?? '');
        $useBackupCode = !empty($_POST['use_backup_code']);

        if (empty($code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Code is required']);
            exit;
        }

        // Verify
        if ($useBackupCode) {
            $result = TotpService::verifyBackupCode($userId, $code);
        } else {
            $result = TotpService::verifyLogin($userId, $code);
        }

        if (!$result['success']) {
            http_response_code(401);
            echo json_encode($result);
            exit;
        }

        // Success - return OK (actual session completion happens in controller)
        echo json_encode([
            'success' => true,
            'codes_remaining' => $result['codes_remaining'] ?? null
        ]);
    }

    /**
     * Get 2FA status for current user
     * GET /api/totp/status
     */
    public function status(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }

        $userId = $_SESSION['user_id'];

        echo json_encode([
            'enabled' => TotpService::isEnabled($userId),
            'setup_required' => TotpService::isSetupRequired($userId),
            'backup_codes_remaining' => TotpService::getBackupCodeCount($userId)
        ]);
    }
}
