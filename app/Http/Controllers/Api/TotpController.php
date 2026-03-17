<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Services\TokenService;
use Nexus\Services\TotpService;
use Nexus\Services\TwoFactorChallengeManager;

/**
 * TotpController -- TOTP two-factor authentication verify + status.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/TotpApiController.php
 */
class TotpController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST totp/verify
     *
     * Accepts either:
     * - { "two_factor_token": "...", "code": "123456" } (stateless)
     * - { "csrf_token": "...", "code": "123456" } (session-based)
     */
    public function verify(): JsonResponse
    {
        $input = $this->getAllInput();
        $twoFactorToken = $input['two_factor_token'] ?? null;
        $code = trim($input['code'] ?? '');
        $useBackupCode = !empty($input['use_backup_code'] ?? false);
        $trustDevice = !empty($input['trust_device'] ?? false);

        // Determine which auth flow we're using
        $isStateless = !empty($twoFactorToken);

        $userId = null;

        if ($isStateless) {
            // Stateless flow - validate two_factor_token
            $challengeData = TwoFactorChallengeManager::get($twoFactorToken);

            if (!$challengeData) {
                return response()->json([
                    'error' => '2FA session expired. Please log in again.',
                    'code' => ApiErrorCodes::AUTH_2FA_TOKEN_EXPIRED,
                    'success' => false,
                    'redirect' => '/login',
                ], 401);
            }

            // Record attempt and check if we've exceeded max attempts
            $attemptResult = TwoFactorChallengeManager::recordAttempt($twoFactorToken);
            if (!$attemptResult['allowed']) {
                return response()->json([
                    'error' => 'Too many failed attempts. Please log in again.',
                    'code' => ApiErrorCodes::AUTH_2FA_MAX_ATTEMPTS,
                    'success' => false,
                    'redirect' => '/login',
                ], 401);
            }

            $userId = $challengeData['user_id'];
        } else {
            // Session-based flow - check CSRF and session
            $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

            // Start session if needed
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Validate CSRF for session-based requests
            if (!Csrf::verify($csrfToken)) {
                return response()->json([
                    'error' => 'Invalid CSRF token',
                    'code' => ApiErrorCodes::AUTH_CSRF_INVALID,
                    'success' => false,
                ], 403);
            }

            // Check for pending 2FA session
            if (empty($_SESSION['pending_2fa_user_id'])) {
                return response()->json([
                    'error' => 'No pending 2FA session',
                    'code' => ApiErrorCodes::AUTH_2FA_EXPIRED,
                    'success' => false,
                ], 401);
            }

            // Check session expiry
            if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
                return response()->json([
                    'error' => 'Session expired',
                    'code' => ApiErrorCodes::AUTH_2FA_EXPIRED,
                    'success' => false,
                    'redirect' => '/login',
                ], 401);
            }

            $userId = (int)$_SESSION['pending_2fa_user_id'];
        }

        // Validate code is provided
        if (empty($code)) {
            return response()->json([
                'error' => 'Code is required',
                'code' => ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'success' => false,
                'field' => 'code',
            ], 400);
        }

        // Verify the code
        if ($useBackupCode) {
            $result = TotpService::verifyBackupCode($userId, $code);
        } else {
            $result = TotpService::verifyLogin($userId, $code);
        }

        if (!$result['success']) {
            return response()->json([
                'error' => $result['error'] ?? 'Invalid code',
                'code' => ApiErrorCodes::AUTH_2FA_INVALID,
                'success' => false,
            ], 401);
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
            SELECT id, first_name, last_name, email, avatar_url, role, tenant_id,
                   is_super_admin, is_tenant_super_admin, email_verified_at, is_approved
            FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return response()->json([
                'error' => 'User not found',
                'code' => ApiErrorCodes::RESOURCE_NOT_FOUND,
                'success' => false,
            ], 401);
        }

        // SECURITY: Enforce registration policy gates after 2FA completion
        $gateBlock = \Nexus\Services\TenantSettingsService::checkLoginGates($user);
        if ($gateBlock) {
            return response()->json([
                'error' => $gateBlock['message'],
                'code' => $gateBlock['code'],
                'success' => false,
            ], 403);
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

        return response()->json($response);
    }

    /** GET totp/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        return response()->json([
            'success' => true,
            'enabled' => TotpService::isEnabled($userId),
            'setup_required' => TotpService::isSetupRequired($userId),
            'backup_codes_remaining' => TotpService::getBackupCodeCount($userId),
            'trusted_devices' => TotpService::getTrustedDeviceCount($userId)
        ]);
    }
}
