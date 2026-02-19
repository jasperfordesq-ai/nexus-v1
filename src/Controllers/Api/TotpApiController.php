<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Services\TokenService;
use Nexus\Services\TotpService;
use Nexus\Services\TwoFactorChallengeManager;

/**
 * TotpApiController - API endpoints for 2FA TOTP operations
 *
 * Supports both stateless (Bearer token) and session-based authentication.
 *
 * Stateless Flow (mobile/SPA):
 * 1. POST /api/auth/login → { requires_2fa: true, two_factor_token: "..." }
 * 2. POST /api/totp/verify → { two_factor_token: "...", code: "123456" }
 * 3. Returns access_token + refresh_token on success
 *
 * Session Flow (browser - backward compatible):
 * 1. Login sets $_SESSION['pending_2fa_user_id']
 * 2. POST /api/totp/verify with CSRF token
 * 3. Returns success, frontend handles redirect
 */
class TotpApiController extends BaseApiController
{
    /**
     * Verify a TOTP code
     * POST /api/totp/verify
     *
     * Accepts either:
     * - { "two_factor_token": "...", "code": "123456" } (stateless)
     * - { "csrf_token": "...", "code": "123456" } (session-based)
     */
    public function verify(): void
    {
        $input = $this->getJsonInput();
        $twoFactorToken = $input['two_factor_token'] ?? null;
        $code = trim($input['code'] ?? $_POST['code'] ?? '');
        $useBackupCode = !empty($input['use_backup_code'] ?? $_POST['use_backup_code'] ?? false);
        $trustDevice = !empty($input['trust_device'] ?? $_POST['trust_device'] ?? false);

        // Determine which auth flow we're using
        $isStateless = !empty($twoFactorToken);

        $userId = null;
        $challengeData = null;

        if ($isStateless) {
            // Stateless flow - validate two_factor_token
            $challengeData = TwoFactorChallengeManager::get($twoFactorToken);

            if (!$challengeData) {
                $this->error(
                    '2FA session expired. Please log in again.',
                    401,
                    ApiErrorCodes::AUTH_2FA_TOKEN_EXPIRED,
                    ['redirect' => '/login']
                );
            }

            // Record attempt and check if we've exceeded max attempts
            $attemptResult = TwoFactorChallengeManager::recordAttempt($twoFactorToken);
            if (!$attemptResult['allowed']) {
                $this->error(
                    'Too many failed attempts. Please log in again.',
                    401,
                    ApiErrorCodes::AUTH_2FA_MAX_ATTEMPTS,
                    ['redirect' => '/login']
                );
            }

            $userId = $challengeData['user_id'];
        } else {
            // Session-based flow - check CSRF and session
            $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

            // Start session if needed
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Validate CSRF for session-based requests
            if (!Csrf::validate($csrfToken)) {
                $this->error(
                    'Invalid CSRF token',
                    403,
                    ApiErrorCodes::AUTH_CSRF_INVALID
                );
            }

            // Check for pending 2FA session
            if (empty($_SESSION['pending_2fa_user_id'])) {
                $this->error(
                    'No pending 2FA session',
                    401,
                    ApiErrorCodes::AUTH_2FA_EXPIRED
                );
            }

            // Check session expiry
            if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
                $this->error(
                    'Session expired',
                    401,
                    ApiErrorCodes::AUTH_2FA_EXPIRED,
                    ['redirect' => '/login']
                );
            }

            $userId = (int)$_SESSION['pending_2fa_user_id'];
        }

        // Validate code is provided
        if (empty($code)) {
            $this->error(
                'Code is required',
                400,
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                ['field' => 'code']
            );
        }

        // Verify the code
        if ($useBackupCode) {
            $result = TotpService::verifyBackupCode($userId, $code);
        } else {
            $result = TotpService::verifyLogin($userId, $code);
        }

        if (!$result['success']) {
            $this->error(
                $result['error'] ?? 'Invalid code',
                401,
                ApiErrorCodes::AUTH_2FA_INVALID
            );
        }

        // =========================================================
        // 2FA SUCCESSFUL - Complete login
        // =========================================================

        // Consume the challenge token (single-use)
        if ($isStateless && $twoFactorToken) {
            TwoFactorChallengeManager::consume($twoFactorToken);
        }

        // Clear session-based pending state
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);

        // Trust device if requested
        if ($trustDevice) {
            TotpService::trustDevice($userId);
        }

        // Fetch user data for response
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, avatar_url, role, tenant_id
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->error(
                'User not found',
                401,
                ApiErrorCodes::RESOURCE_NOT_FOUND
            );
        }

        // Generate tokens
        $isMobile = TokenService::isMobileRequest();
        $accessToken = TokenService::generateToken(
            (int)$user['id'],
            (int)$user['tenant_id'],
            ['role' => $user['role'], 'email' => $user['email']],
            $isMobile
        );
        $refreshToken = TokenService::generateRefreshToken(
            (int)$user['id'],
            (int)$user['tenant_id'],
            $isMobile
        );

        // For session-based clients, set up full session
        $wantsStateless = $isMobile || isset($_SERVER['HTTP_X_STATELESS_AUTH']);
        if (!$wantsStateless) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Preserve layout preference
            $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Restore layout preference
            if ($preservedLayout) {
                $_SESSION['nexus_active_layout'] = $preservedLayout;
                $_SESSION['nexus_layout'] = $preservedLayout;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_logged_in'] = true;
        }

        // Return success response with tokens
        $response = [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url'],
                'tenant_id' => $user['tenant_id']
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => TokenService::getAccessTokenExpiry($isMobile),
            'refresh_expires_in' => TokenService::getRefreshTokenExpiry($isMobile),
            'is_mobile' => $isMobile,
            // Legacy compatibility
            'token' => $accessToken
        ];

        // Include backup codes remaining if a backup code was used
        if ($useBackupCode && isset($result['codes_remaining'])) {
            $response['codes_remaining'] = $result['codes_remaining'];
        }

        $this->jsonResponse($response);
    }

    /**
     * Get 2FA status for current user
     * GET /api/totp/status
     */
    public function status(): void
    {
        $userId = $this->requireAuth();

        $this->jsonResponse([
            'success' => true,
            'enabled' => TotpService::isEnabled($userId),
            'setup_required' => TotpService::isSetupRequired($userId),
            'backup_codes_remaining' => TotpService::getBackupCodeCount($userId),
            'trusted_devices' => TotpService::getTrustedDeviceCount($userId)
        ]);
    }

    /**
     * Get JSON input from request body
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
