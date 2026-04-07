<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\TenantSettingsService;
use App\Services\TokenService;
use App\Services\TotpService;
use App\Services\TwoFactorChallengeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\Csrf;
use App\Core\TenantContext;

/**
 * TotpController -- TOTP two-factor authentication verify + status.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/TotpApiController.php
 */
class TotpController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly TwoFactorChallengeManager $twoFactorChallengeManager,
        private readonly TokenService $tokenService,
        private readonly TotpService $totpService,
    ) {}

    /**
     * POST totp/verify
     *
     * Accepts either:
     * - { "two_factor_token": "...", "code": "123456" } (stateless)
     * - { "csrf_token": "...", "code": "123456" } (session-based)
     */
    public function verify(): JsonResponse
    {
        $this->rateLimit('totp_verify', 5, 300);

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
            $challengeData = $this->twoFactorChallengeManager->get($twoFactorToken);

            if (!$challengeData) {
                return $this->respondWithError(
                    ApiErrorCodes::AUTH_2FA_TOKEN_EXPIRED,
                    '2FA session expired. Please log in again.',
                    null,
                    401
                );
            }

            // Record attempt and check if we've exceeded max attempts
            $attemptResult = $this->twoFactorChallengeManager->recordAttempt($twoFactorToken);
            if (!$attemptResult['allowed']) {
                return $this->respondWithError(
                    ApiErrorCodes::AUTH_2FA_MAX_ATTEMPTS,
                    'Too many failed attempts. Please log in again.',
                    null,
                    401
                );
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
                return $this->respondWithError(ApiErrorCodes::AUTH_CSRF_INVALID, __('api.invalid_csrf_token'), null, 403);
            }

            // Check for pending 2FA session
            if (empty($_SESSION['pending_2fa_user_id'])) {
                return $this->respondWithError(ApiErrorCodes::AUTH_2FA_EXPIRED, __('api.no_pending_2fa_session'), null, 401);
            }

            // Check session expiry
            if (($_SESSION['pending_2fa_expires'] ?? 0) < time()) {
                unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);
                return $this->respondWithError(ApiErrorCodes::AUTH_2FA_EXPIRED, __('api.session_expired'), null, 401);
            }

            $userId = (int)$_SESSION['pending_2fa_user_id'];
        }

        // Validate code is provided
        if (empty($code)) {
            return $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, __('api.code_required'), 'code', 400);
        }

        // Verify the code
        if ($useBackupCode) {
            $result = $this->totpService->verifyBackupCode($userId, $code);
        } else {
            $result = $this->totpService->verifyLogin($userId, $code);
        }

        if (!$result['success']) {
            return $this->respondWithError(ApiErrorCodes::AUTH_2FA_INVALID, $result['error'] ?? 'Invalid code', null, 401);
        }

        // =========================================================
        // 2FA SUCCESSFUL - Complete login
        // =========================================================

        // Consume the challenge token (single-use)
        if ($isStateless && $twoFactorToken) {
            $this->twoFactorChallengeManager->consume($twoFactorToken);
        }

        // Clear session-based pending state
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_expires']);

        // Trust device if requested — returns the plain token for the frontend
        $trustedDeviceToken = null;
        if ($trustDevice) {
            $trustedDeviceToken = $this->totpService->trustDevice($userId);
        }

        // Fetch user data for response
        // Allow super admins to complete 2FA even when their tenant_id differs
        // from the current tenant context (cross-tenant login)
        $tenantId = TenantContext::getId();
        $userRow = DB::selectOne("
            SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.role, u.tenant_id,
                   u.is_super_admin, u.is_tenant_super_admin, u.is_god, u.email_verified_at,
                   u.is_approved, u.status, u.onboarding_completed, t.configuration
            FROM users u
            LEFT JOIN tenants t ON u.tenant_id = t.id
            WHERE u.id = ? AND (u.tenant_id = ? OR u.is_super_admin = 1 OR u.is_tenant_super_admin = 1)
        ", [$userId, $tenantId]);
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, __('api.user_not_found'), null, 401);
        }

        // SECURITY: Block suspended/banned users from completing 2FA login
        if (($user['status'] ?? 'active') !== 'active') {
            return $this->respondWithError(ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED, __('api.account_suspended'), null, 403);
        }

        // SECURITY: Enforce registration policy gates after 2FA completion
        $gateBlock = $this->tenantSettingsService->checkLoginGates($user);
        if ($gateBlock) {
            return $this->respondWithError($gateBlock['code'], $gateBlock['message'], null, 403);
        }

        // Generate tokens
        $isMobile = $this->tokenService->isMobileRequest();
        $accessToken = $this->tokenService->generateToken(
            (int)$user['id'],
            (int)$user['tenant_id'],
            [
                'role' => $user['role'],
                'email' => $user['email'],
                'is_super_admin' => !empty($user['is_super_admin']),
                'is_tenant_super_admin' => !empty($user['is_tenant_super_admin']),
            ],
            $isMobile
        );
        $refreshToken = $this->tokenService->generateRefreshToken(
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

        // Issue Sanctum token alongside legacy JWT (matches AuthController login flow)
        $sanctumToken = null;
        try {
            $eloquentUser = \App\Models\User::find((int)$user['id']);
            if ($eloquentUser) {
                $sanctumToken = $eloquentUser->createToken(
                    $isMobile ? 'mobile-api' : 'web-api',
                    ['*']
                )->plainTextToken;
            }
        } catch (\Throwable $e) {
            error_log('[TotpController] Sanctum token creation failed: ' . $e->getMessage());
        }

        // Return success response with tokens — MUST match AuthController::login() response shape
        $response = [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'avatar_url' => $user['avatar_url'],
                'tenant_id' => $user['tenant_id'],
                'role' => $user['role'] ?? 'member',
                'is_admin' => in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'super_admin']) || !empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin']),
                'is_super_admin' => !empty($user['is_super_admin']),
                'is_god' => !empty($user['is_god']),
                'is_tenant_super_admin' => !empty($user['is_tenant_super_admin']),
                'onboarding_completed' => (bool)($user['onboarding_completed'] ?? false),
            ],
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->tokenService->getAccessTokenExpiry($isMobile),
            'refresh_expires_in' => $this->tokenService->getRefreshTokenExpiry($isMobile),
            'is_mobile' => $isMobile,
            'token' => $sanctumToken ?? $accessToken,
            'sanctum_token' => $sanctumToken,
            'config' => json_decode($user['configuration'] ?? '{"modules": {"events": true, "polls": true, "goals": true, "volunteering": true, "resources": true}}', true)
        ];

        // Include backup codes remaining if a backup code was used
        if ($useBackupCode && isset($result['codes_remaining'])) {
            $response['codes_remaining'] = $result['codes_remaining'];
        }

        // Include trusted device token so frontend can store it in localStorage
        // and send it via X-Trusted-Device header on future login requests
        if ($trustedDeviceToken) {
            $response['trusted_device_token'] = $trustedDeviceToken;
        }

        return response()->json($response);
    }

    /** GET totp/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        return response()->json([
            'success' => true,
            'enabled' => $this->totpService->isEnabled($userId),
            'setup_required' => $this->totpService->isSetupRequired($userId),
            'backup_codes_remaining' => $this->totpService->getBackupCodeCount($userId),
            'trusted_devices' => $this->totpService->getTrustedDeviceCount($userId)
        ]);
    }
}
