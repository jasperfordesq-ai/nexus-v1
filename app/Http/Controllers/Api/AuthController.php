<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\RateLimitService;
use App\Services\TenantSettingsService;
use App\Services\TokenService;
use App\Services\TotpService;
use App\Services\TwoFactorChallengeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Core\ApiErrorCodes;
use App\Core\TenantContext;

/**
 * AuthController — Authentication: login, logout, token refresh, session management.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/AuthController.php
 */
class AuthController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly RateLimitService $rateLimitService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly TokenService $tokenService,
        private readonly TotpService $totpService,
        private readonly TwoFactorChallengeManager $twoFactorChallengeManager,
    ) {}

    /**
     * Helper to return auth error response.
     *
     * Uses respondWithError() from BaseApiController for standard cases.
     * Falls back to a direct response when extra fields are needed to
     * preserve the auth-specific response contract (retry_after, etc.).
     */
    private function authError(string $message, string $code, int $status = 400, array $extra = []): JsonResponse
    {
        if (empty($extra)) {
            return $this->respondWithError($code, $message, null, $status);
        }

        // When extra fields are present, build a combined error envelope
        // that includes both the standard error structure and auth-specific fields.
        $response = array_merge([
            'errors' => [['code' => $code, 'message' => $message]],
            'success' => false,
        ], $extra);

        return response()->json($response, $status, [
            'API-Version' => '2.0',
        ]);
    }

    /**
     * POST /api/auth/login
     */
    public function login(): JsonResponse
    {
        $data = $this->getAllInput();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->authError(
                __('api.email_and_password_required'),
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                400
            );
        }

        // SECURITY: Database-based brute force protection (tracks FAILED attempts only)
        // Route-level throttle:30,1 middleware handles general request-rate DoS protection.
        $ip = \App\Core\ClientIp::get();
        if (!empty($email)) {
            $emailLimit = \App\Core\RateLimiter::check($email, 'email');
            if ($emailLimit['limited']) {
                $message = \App\Core\RateLimiter::getRetryMessage($emailLimit['retry_after']);
                return $this->authError(
                    $message,
                    ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                    429,
                    ['retry_after' => $emailLimit['retry_after']]
                );
            }
        }

        // Check rate limit by IP
        $ipLimit = \App\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \App\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            return $this->authError(
                $message,
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429,
                ['retry_after' => $ipLimit['retry_after']]
            );
        }

        // Scope login by tenant when tenant context is available.
        // Use a single UNION query that atomically checks both the tenant-scoped user
        // row and any super-admin cross-tenant row in one round-trip. This eliminates
        // the timing difference between a normal login and a super-admin login that
        // would otherwise create a username oracle (two separate queries ≠ equal timing).
        $tenantId = TenantContext::getId();
        $user = null;

        if ($tenantId) {
            // Priority 1: exact tenant match
            // Priority 2: super-admin from any tenant (cross-tenant fallback)
            // UNION picks whichever row matches first; ORDER BY ensures tenant row wins.
            $userRow = DB::selectOne(
                "SELECT u.*, t.configuration, 1 AS _match_priority
                 FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.email = ? AND u.tenant_id = ?
                 UNION
                 SELECT u.*, t.configuration, 2 AS _match_priority
                 FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.email = ?
                   AND (u.is_super_admin = 1 OR u.is_tenant_super_admin = 1 OR u.role = 'super_admin')
                 ORDER BY _match_priority ASC
                 LIMIT 1",
                [$email, $tenantId, $email]
            );
            $user = $userRow ? (array)$userRow : null;
            // Remove internal sorting column from user array
            if ($user) {
                unset($user['_match_priority']);
            }
        } else {
            $userRow = DB::selectOne("SELECT u.*, t.configuration FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id WHERE u.email = ?", [$email]);
            $user = $userRow ? (array)$userRow : null;
        }

        // Constant-time comparison
        $dummyHash = '$argon2id$v=19$m=65536,t=4,p=1$V1Jna0owWXBLNC55ajFQRQ$h0+cXUsJzOi6TzES3RPuquTJpwPbpYmVHS4A3ArHHXo';
        $passwordValid = password_verify($password, $user['password_hash'] ?? $dummyHash);

        if ($user && $passwordValid) {
            // Record successful login and clear failed attempts
            if (!empty($email)) {
                \App\Core\RateLimiter::recordAttempt($email, 'email', true);
            }
            \App\Core\RateLimiter::recordAttempt($ip, 'ip', true);

            // (DB brute force counters already cleared by recordAttempt with success=true)

            // Registration policy enforcement
            $gateBlock = $this->tenantSettingsService->checkLoginGatesForUser($user);
            if ($gateBlock) {
                return $this->authError(
                    $gateBlock['message'],
                    $gateBlock['code'],
                    403,
                    $gateBlock['extra']
                );
            }

            // Detect if this is a mobile/API request
            $isMobile = $this->tokenService->isMobileRequest();
            $wantsStateless = $isMobile || isset($_SERVER['HTTP_X_STATELESS_AUTH']);

            // 2FA CHECK — ENFORCED FOR ALL USERS WHO HAVE IT ENABLED
            $has2faEnabled = !empty($user['totp_enabled']) || $this->totpService->isEnabled((int)$user['id']);
            $isTrustedDevice = $has2faEnabled && $this->totpService->isTrustedDevice((int)$user['id']);

            if ($has2faEnabled && !$isTrustedDevice) {
                // 2FA required - create challenge token
                $twoFactorToken = $this->twoFactorChallengeManager->create(
                    (int)$user['id'],
                    ['totp', 'backup_code']
                );

                // For session-based clients, also store in session
                if (!$wantsStateless) {
                    if (session_status() == PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_expires'] = time() + 300;
                }

                return response()->json([
                    'success' => false,
                    'requires_2fa' => true,
                    'two_factor_token' => $twoFactorToken,
                    'methods' => ['totp', 'backup_code'],
                    'code' => ApiErrorCodes::AUTH_2FA_REQUIRED,
                    'message' => __('api_controllers_1.auth.two_factor_required'),
                    'user' => [
                        'id' => $user['id'],
                        'first_name' => $user['first_name'],
                        'email_masked' => $this->maskEmail($user['email'])
                    ]
                ], 200);
            }

            // NO 2FA or TRUSTED DEVICE - Complete login normally

            // START SESSION ONLY FOR WEB CLIENTS
            if (!$wantsStateless) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;
                session_regenerate_id(true);
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

            // Generate secure tokens (legacy JWT-based)
            $accessToken = $this->tokenService->generateToken((int)$user['id'], (int)$user['tenant_id'], [
                'role' => $user['role'],
                'email' => $user['email'],
                'is_super_admin' => !empty($user['is_super_admin']),
                'is_tenant_super_admin' => !empty($user['is_tenant_super_admin']),
            ], $isMobile);
            $refreshToken = $this->tokenService->generateRefreshToken((int)$user['id'], (int)$user['tenant_id'], $isMobile);

            $accessTokenExpiry = $this->tokenService->getAccessTokenExpiry($isMobile);
            $refreshTokenExpiry = $this->tokenService->getRefreshTokenExpiry($isMobile);

            // Issue Sanctum token alongside legacy JWT for gradual migration
            $sanctumToken = null;
            try {
                $eloquentUser = \App\Models\User::find((int)$user['id']);
                if ($eloquentUser) {
                    $tokenAbilities = ['*'];
                    $tokenResult = $eloquentUser->createToken(
                        $isMobile ? 'mobile-api' : 'web-api',
                        $tokenAbilities
                    );
                    $sanctumToken = $tokenResult->plainTextToken;

                    // Stamp the tenant_id on the token record so the middleware can
                    // validate cross-tenant token usage (tenant_id column is nullable
                    // for backwards compatibility with tokens created before migration).
                    $tokenResult->accessToken->forceFill(['tenant_id' => TenantContext::getId()])->save();
                }
            } catch (\Throwable $e) {
                // Sanctum token creation may fail if personal_access_tokens table
                // doesn't exist yet — fall back gracefully to legacy tokens only
                \Illuminate\Support\Facades\Log::warning('[AuthController] Sanctum token creation failed: ' . $e->getMessage());
            }

            return response()->json([
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
                'expires_in' => $accessTokenExpiry,
                'refresh_expires_in' => $refreshTokenExpiry,
                'is_mobile' => $isMobile,
                'token' => $sanctumToken ?? $accessToken,
                'sanctum_token' => $sanctumToken,
                'config' => json_decode($user['configuration'] ?? '{"modules": {"events": true, "polls": true, "goals": true, "volunteering": true, "resources": true}}', true)
            ]);
        }

        // Record failed login attempt
        if (!empty($email)) {
            \App\Core\RateLimiter::recordAttempt($email, 'email', false);
        }
        \App\Core\RateLimiter::recordAttempt($ip, 'ip', false);

        // Log failed login to application logs for security monitoring
        // Email is masked to prevent PII leakage in logs
        $maskedEmail = !empty($email) ? substr($email, 0, 2) . '***@' . (explode('@', $email)[1] ?? '***') : 'empty';
        \Illuminate\Support\Facades\Log::warning('Failed login attempt', [
            'email_masked' => $maskedEmail,
            'ip' => $ip,
            'tenant_id' => $tenantId,
            'user_agent' => request()->userAgent(),
        ]);

        return $this->authError(
            __('api.invalid_credentials'),
            ApiErrorCodes::AUTH_INVALID_CREDENTIALS,
            401
        );
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Get user ID before clearing session
        $userId = $_SESSION['user_id'] ?? null;

        // Also check Bearer token for user ID
        if (!$userId) {
            $userId = $this->getOptionalUserId();
        }

        // Revoke the current Sanctum token if present
        $sanctumTokenRevoked = false;
        try {
            if ($request->user() && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
                $sanctumTokenRevoked = true;
            }
        } catch (\Throwable $e) {
            // Sanctum may not be fully set up yet — ignore gracefully
            \Illuminate\Support\Facades\Log::warning('[AuthController] Sanctum token revocation failed: ' . $e->getMessage());
        }

        // If a refresh token is provided, revoke it (legacy JWT)
        $data = $this->getAllInput();
        $refreshToken = $data['refresh_token'] ?? '';
        $tokenRevoked = false;

        if (!empty($refreshToken) && $userId) {
            $tokenRevoked = $this->tokenService->revokeToken($refreshToken, $userId);
        }

        // Clear all session data
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
        session_destroy();

        $response = [
            'success' => true,
            'message' => __('api_controllers_1.auth.logged_out')
        ];

        if ($tokenRevoked) {
            $response['refresh_token_revoked'] = true;
        }

        if ($sanctumTokenRevoked) {
            $response['sanctum_token_revoked'] = true;
        }

        return response()->json($response);
    }

    /**
     * POST /api/auth/refresh-token
     */
    public function refreshToken(): JsonResponse
    {
        // Rate limiting
        $ip = \App\Core\ClientIp::get();
        if (!$this->rateLimitService->increment("auth:refresh:$ip", 10, 60)) {
            return $this->authError(
                __('api.too_many_attempts'),
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429,
                ['retry_after' => 60]
            );
        }

        $data = $this->getAllInput();
        $refreshToken = $data['refresh_token'] ?? '';

        // Also check Authorization header
        if (empty($refreshToken)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                $refreshToken = $matches[1];
            }
        }

        if (empty($refreshToken)) {
            return $this->authError(
                __('api.refresh_token_required'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        // Validate the refresh token
        $payload = $this->tokenService->validateToken($refreshToken);

        if (!$payload) {
            return $this->authError(
                __('api.invalid_or_expired_refresh_token'),
                ApiErrorCodes::AUTH_TOKEN_EXPIRED,
                401
            );
        }

        // Check it's actually a refresh token
        if (($payload['type'] ?? '') !== 'refresh') {
            return $this->authError(
                __('api.invalid_token_type'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId || !$tenantId) {
            return $this->authError(
                __('api.invalid_token_payload'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        // Verify user still exists and is active
        $userRow = DB::selectOne("SELECT id, email, role, status, is_super_admin, is_tenant_super_admin, tenant_id, email_verified_at, is_approved FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->authError(
                __('api.user_not_found'),
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                401
            );
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            return $this->authError(
                __('api.account_suspended'),
                ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED,
                403
            );
        }

        // Enforce registration policy gates on token refresh
        $gateBlock = $this->tenantSettingsService->checkLoginGatesForUser($user);
        if ($gateBlock) {
            return $this->authError(
                $gateBlock['message'],
                $gateBlock['code'],
                403,
                $gateBlock['extra']
            );
        }

        $isMobile = $this->tokenService->isMobileRequest();

        // Generate new tokens
        $newAccessToken = $this->tokenService->generateToken((int)$userId, (int)$tenantId, [
            'role' => $user['role'],
            'email' => $user['email'],
            'is_super_admin' => !empty($user['is_super_admin']),
            'is_tenant_super_admin' => !empty($user['is_tenant_super_admin']),
        ], $isMobile);

        $accessTokenExpiry = $this->tokenService->getAccessTokenExpiry($isMobile);
        $refreshTokenExpiry = $this->tokenService->getRefreshTokenExpiry($isMobile);

        // Only generate new refresh token if current one is close to expiring (< 30 days)
        $refreshTimeRemaining = $this->tokenService->getTimeRemaining($refreshToken);
        $newRefreshToken = null;

        if ($refreshTimeRemaining < 2592000) { // 30 days
            $newRefreshToken = $this->tokenService->generateRefreshToken((int)$userId, (int)$tenantId, $isMobile);
        }

        $response = [
            'success' => true,
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenExpiry,
            'is_mobile' => $isMobile,
            'token' => $newAccessToken
        ];

        if ($newRefreshToken) {
            $response['refresh_token'] = $newRefreshToken;
            $response['refresh_expires_in'] = $refreshTokenExpiry;
        }

        return response()->json($response);
    }

    /**
     * POST /api/auth/heartbeat
     */
    public function heartbeat(): JsonResponse
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $isSessionAuth = !empty($_SESSION['user_id']);

        // Check Bearer token authentication
        $tokenInfo = null;
        $isBearerAuth = false;
        $bearerUserId = null;
        $bearerTenantId = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = $this->tokenService->validateToken($token);
            if ($payload) {
                $tokenInfo = [
                    'valid' => true,
                    'expires_at' => date('c', $payload['exp']),
                    'time_remaining' => $payload['exp'] - time(),
                    'needs_refresh' => $this->tokenService->needsRefresh($token)
                ];
                $isBearerAuth = true;
                $bearerUserId = $payload['user_id'];
                $bearerTenantId = $payload['tenant_id'];
            } else {
                $tokenInfo = [
                    'valid' => false,
                    'needs_refresh' => true
                ];
            }
        }

        $isAuthenticated = $isSessionAuth || $isBearerAuth;

        if (!$isAuthenticated) {
            return $this->authError(
                __('api.unauthorized'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401,
                ['authenticated' => false, 'token' => $tokenInfo]
            );
        }

        $userId = $isBearerAuth ? $bearerUserId : ($_SESSION['user_id'] ?? null);
        $tenantId = $isBearerAuth ? $bearerTenantId : ($_SESSION['tenant_id'] ?? null);

        // For Bearer-authenticated requests, verify user still exists
        if ($isBearerAuth && $bearerUserId) {
            try {
                $bearerUser = \App\Models\User::findById((int)$bearerUserId, false);
                if (!$bearerUser || ($bearerUser['status'] ?? 'active') === 'suspended') {
                    return $this->authError(
                        __('api.user_not_found'),
                        ApiErrorCodes::AUTH_ACCOUNT_DELETED,
                        401,
                        ['authenticated' => false, 'should_reauth' => true]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[Heartbeat] Bearer user check failed: ' . $e->getMessage());
            }
        }

        // For session-authenticated requests, perform session-related operations
        if (!$isBearerAuth && $isSessionAuth) {
            $lastUserCheck = $_SESSION['_last_user_check'] ?? 0;
            if (time() - $lastUserCheck >= 300) {
                try {
                    $user = \App\Models\User::findById($_SESSION['user_id'], false);

                    if (!$user) {
                        $maxRetries = 3;
                        for ($i = 0; $i < $maxRetries && !$user; $i++) {
                            usleep(200000);
                            $user = \App\Models\User::findById($_SESSION['user_id'], false);
                        }
                    }

                    if (!$user) {
                        \Illuminate\Support\Facades\Log::warning("[Heartbeat] User ID {$_SESSION['user_id']} not found after retries - possible deleted user");
                        return $this->authError(
                            __('api.user_not_found'),
                            ApiErrorCodes::AUTH_ACCOUNT_DELETED,
                            401,
                            ['authenticated' => false, 'should_reauth' => true]
                        );
                    }
                    $_SESSION['_last_user_check'] = time();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[Heartbeat] User check failed: ' . $e->getMessage());
                }
            }

            $_SESSION['_last_heartbeat'] = time();
        }

        // Calculate session/token expiry info
        $sessionLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt = date('c', time() + $sessionLifetime);

        // Update user's last_active_at in database (throttled)
        static $lastActiveUpdated = false;
        if (!$lastActiveUpdated && $userId) {
            $shouldUpdate = $isBearerAuth;
            if (!$isBearerAuth) {
                $lastUpdate = $_SESSION['_last_active_update'] ?? 0;
                $shouldUpdate = (time() - $lastUpdate >= 60);
            }

            if ($shouldUpdate) {
                try {
                    if (class_exists('\App\Models\User')) {
                        \App\Models\User::updateLastActive((int)$userId);
                        if (!$isBearerAuth) {
                            $_SESSION['_last_active_update'] = time();
                        }
                        $lastActiveUpdated = true;
                    }
                } catch (\Throwable $e) {
                    // Silently fail
                }
            }
        }

        // Also trigger presence heartbeat so auth heartbeat doubles as presence ping
        if ($userId) {
            try {
                \App\Services\PresenceService::heartbeat((int) $userId);
            } catch (\Throwable) {
                // Non-critical — presence is best-effort
            }
        }

        $response = [
            'success' => true,
            'authenticated' => true,
            'auth_type' => $isBearerAuth ? 'bearer' : 'session',
            'user_id' => $userId
        ];

        if (!$isBearerAuth) {
            $response['expires_at'] = $expiresAt;
            $response['session_lifetime'] = $sessionLifetime;
        }

        if ($tokenInfo !== null) {
            $response['token'] = $tokenInfo;
        }

        return response()->json($response);
    }

    /**
     * GET /api/auth/check-session
     */
    public function checkSession(): JsonResponse
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            return response()->json([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'role' => $_SESSION['user_role'] ?? 'member',
                    'tenant_id' => $_SESSION['tenant_id'] ?? TenantContext::getId()
                ]
            ]);
        }

        return $this->authError(
            __('api.not_authenticated'),
            ApiErrorCodes::AUTH_TOKEN_MISSING,
            401,
            ['authenticated' => false]
        );
    }

    /**
     * POST /api/auth/refresh-session
     */
    public function refreshSession(): JsonResponse
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return $this->authError(
                __('api.unauthorized'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        $_SESSION['_session_refreshed_at'] = time();

        $sessionLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt = date('c', time() + $sessionLifetime);

        return response()->json([
            'success' => true,
            'message' => __('api_controllers_1.auth.session_refreshed'),
            'expires_at' => $expiresAt,
            'session_lifetime' => $sessionLifetime
        ]);
    }

    /**
     * POST /api/auth/restore-session
     *
     * @deprecated Sunset: 2026-08-01
     */
    public function restoreSession(): JsonResponse
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Check Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->authError(
                __('api.bearer_token_required'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        $token = $matches[1];
        $payload = $this->tokenService->validateToken($token);

        if (!$payload || ($payload['type'] ?? 'access') !== 'access') {
            return $this->authError(
                __('api.invalid_or_expired_token'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        $userId = $payload['user_id'] ?? null;

        if (!$userId) {
            return $this->authError(
                __('api.invalid_token_payload'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        // Fetch user data to populate session — scoped by tenant to prevent cross-tenant session restore
        $tenantId = TenantContext::getId();
        $userRow = DB::selectOne(
            "SELECT id, first_name, last_name, email, role, avatar_url, tenant_id, is_super_admin, is_tenant_super_admin, email_verified_at, is_approved FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->authError(
                __('api.user_not_found'),
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                401
            );
        }

        // Enforce registration policy gates on session restore
        $gateBlock = $this->tenantSettingsService->checkLoginGatesForUser($user);
        if ($gateBlock) {
            return $this->authError(
                $gateBlock['message'],
                $gateBlock['code'],
                403,
                $gateBlock['extra']
            );
        }

        // Restore full session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'member';
        $_SESSION['role'] = $user['role'] ?? 'member';
        $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
        $_SESSION['is_tenant_super_admin'] = $user['is_tenant_super_admin'] ?? 0;
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';
        $_SESSION['is_logged_in'] = true;

        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $_SESSION['is_admin'] = in_array($user['role'], $adminRoles) ? 1 : 0;

        return response()->json([
            'success' => true,
            'message' => __('api_controllers_1.auth.session_restored'),
            'user_id' => $user['id'],
            'session_id' => session_id(),
            '_deprecated' => [
                'message' => __('api_controllers_1.auth.session_deprecated'),
                'sunset' => '2026-08-01',
                'replacement' => 'Use Authorization: Bearer <token> header on all API requests'
            ]
        ], 200, [
            'X-API-Deprecated' => 'true',
            'Sunset' => 'Sat, 01 Aug 2026 00:00:00 GMT',
        ]);
    }

    /**
     * POST /api/auth/validate-token
     */
    public function validateToken(): JsonResponse
    {
        $token = null;

        // Check Authorization header first
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // Fallback to request body
        if (!$token) {
            $data = $this->getAllInput();
            $token = $data['token'] ?? $data['access_token'] ?? '';
        }

        if (empty($token)) {
            return $this->authError(
                __('api.token_required'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        $payload = $this->tokenService->validateToken($token);

        if (!$payload) {
            return $this->authError(
                __('api.invalid_or_expired_token'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401,
                ['valid' => false]
            );
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'user_id' => $payload['user_id'],
            'tenant_id' => $payload['tenant_id'],
            'type' => $payload['type'] ?? 'access',
            'expires_at' => date('c', $payload['exp']),
            'time_remaining' => $payload['exp'] - time(),
            'needs_refresh' => $this->tokenService->needsRefresh($token)
        ]);
    }

    /**
     * POST /api/auth/revoke
     */
    public function revokeToken(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        if (!$userId) {
            return $this->authError(
                __('api.auth_required'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        $data = $this->getAllInput();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->authError(
                __('api.refresh_token_required'),
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                400
            );
        }

        $revoked = $this->tokenService->revokeToken($refreshToken, $userId);

        if (!$revoked) {
            return $this->authError(
                __('api.invalid_refresh_token_or_revoked'),
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                400
            );
        }

        return response()->json([
            'success' => true,
            'data' => ['revoked' => true]
        ]);
    }

    /**
     * POST /api/auth/revoke-all
     */
    public function revokeAllTokens(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        if (!$userId) {
            return $this->authError(
                __('api.auth_required'),
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        $revokedCount = $this->tokenService->revokeAllTokensForUser($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'revoked_count' => $revokedCount,
                'message' => __('api_controllers_1.auth.tokens_revoked')
            ]
        ]);
    }

    /**
     * POST /api/auth/admin-session
     */
    public function adminSession(): JsonResponse
    {
        $input = $this->getAllInput();
        $token = $input['token'] ?? '';
        $redirect = $input['redirect'] ?? '/admin-legacy';

        // Sanitize redirect — only allow paths starting with /admin-legacy
        if (strpos($redirect, '/admin-legacy') !== 0) {
            $redirect = '/admin-legacy';
        }

        if (empty($token)) {
            return $this->authError(__('api.missing_token'), ApiErrorCodes::AUTH_TOKEN_MISSING, 400);
        }

        // Validate the JWT
        $payload = $this->tokenService->validateToken($token);
        if (!$payload) {
            return $this->authError(__('api.invalid_or_expired_token'), ApiErrorCodes::AUTH_TOKEN_INVALID, 401);
        }

        $userId = $payload['user_id'] ?? $payload['sub'] ?? null;
        if (!$userId) {
            return $this->authError(__('api.invalid_token_payload'), ApiErrorCodes::AUTH_TOKEN_INVALID, 401);
        }

        // Load user from DB — tenant-scoped, with super-admin bypass
        $tenantId = TenantContext::getId();

        // First try tenant-scoped lookup
        $userRow = DB::selectOne(
            "SELECT id, first_name, last_name, email, role, tenant_id, avatar_url, is_super_admin, is_tenant_super_admin, is_god, is_admin FROM users WHERE id = ? AND tenant_id = ?",
            [(int)$userId, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        // Super-admin cross-tenant fallback: if not found in current tenant,
        // check if user is a super-admin who can access any tenant's admin panel
        if (!$user && $tenantId) {
            $candidateRow = DB::selectOne(
                "SELECT id, first_name, last_name, email, role, tenant_id, avatar_url, is_super_admin, is_tenant_super_admin, is_god, is_admin FROM users WHERE id = ?",
                [(int)$userId]
            );
            $candidate = $candidateRow ? (array)$candidateRow : null;
            if ($candidate && (!empty($candidate['is_super_admin']) || !empty($candidate['is_god']))) {
                $user = $candidate;
            }
        }

        if (!$user) {
            return $this->authError(__('api.user_not_found'), ApiErrorCodes::RESOURCE_NOT_FOUND, 404);
        }

        // Check admin privileges
        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $isAdmin = in_array($user['role'], $adminRoles) || !empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin']) || !empty($user['is_god']);
        if (!$isAdmin) {
            return $this->authError(__('api.admin_access_required'), ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS, 403);
        }

        // Create PHP session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'member';
        $_SESSION['role'] = $user['role'] ?? 'member';
        $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
        $_SESSION['is_tenant_super_admin'] = $user['is_tenant_super_admin'] ?? 0;
        $_SESSION['is_god'] = $user['is_god'] ?? 0;
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';
        $_SESSION['is_admin'] = in_array($user['role'], $adminRoles) ? 1 : 0;
        $_SESSION['is_logged_in'] = true;

        // Redirect to legacy admin
        return response()->json(null, 302, ['Location' => $redirect]);
    }

    /**
     * GET /api/auth/csrf-token
     */
    public function getCsrfToken(): JsonResponse
    {
        // Rate limit this endpoint (10 per minute per IP)
        $ip = \App\Core\ClientIp::get();
        $rateLimitResult = \App\Core\RateLimiter::check($ip . ':csrf', 'csrf_token');

        if ($rateLimitResult['limited']) {
            return $this->authError(
                __('api.rate_limit_exceeded'),
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429
            );
        }

        \App\Core\RateLimiter::recordAttempt($ip . ':csrf', 'csrf_token', true);

        // Generate and return the CSRF token
        $token = \App\Core\Csrf::generate();

        return response()->json([
            'success' => true,
            'data' => [
                'csrf_token' => $token
            ]
        ]);
    }

    /**
     * Mask email address for display (e.g., j***@example.com)
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        $maskedLocal = strlen($local) > 1
            ? $local[0] . str_repeat('*', min(strlen($local) - 1, 5))
            : '*';

        return $maskedLocal . '@' . $domain;
    }
}
