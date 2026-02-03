<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Database;
use Nexus\Services\TokenService;
use Nexus\Services\TotpService;
use Nexus\Services\TwoFactorChallengeManager;

/**
 * AuthController - Authentication API endpoints
 *
 * Handles login, registration, token refresh, and session management.
 * Supports both session-based (web) and stateless Bearer token (mobile/SPA) authentication.
 *
 * Response format maintains backward compatibility with existing mobile apps:
 * - Success: { "success": true, "user": {...}, "access_token": "...", ... }
 * - Error: { "error": "message", "code": "ERROR_CODE", ... }
 */
class AuthController
{
    /**
     * Helper to return JSON response with API version header
     */
    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        header('API-Version: 1.0');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    /**
     * Helper to return error response with standardized error code
     *
     * @param string $message Human-readable error message
     * @param string $code Error code from ApiErrorCodes
     * @param int $status HTTP status code
     * @param array $extra Additional fields to include in response
     */
    private function errorResponse(string $message, string $code, int $status = 400, array $extra = [])
    {
        $response = array_merge([
            'error' => $message,
            'code' => $code,
            'success' => false,
        ], $extra);

        return $this->jsonResponse($response, $status);
    }

    /**
     * Helper to get JSON input
     */
    private function getJsonInput()
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    public function login()
    {
        $data = $this->getJsonInput();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            return $this->errorResponse(
                'Email and password required',
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                400
            );
        }

        // SECURITY: Rate limiting to prevent brute force attacks
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check rate limit by email
        if (!empty($email)) {
            $emailLimit = \Nexus\Core\RateLimiter::check($email, 'email');
            if ($emailLimit['limited']) {
                $message = \Nexus\Core\RateLimiter::getRetryMessage($emailLimit['retry_after']);
                header('Retry-After: ' . $emailLimit['retry_after']);
                return $this->errorResponse(
                    $message,
                    ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                    429,
                    ['retry_after' => $emailLimit['retry_after']]
                );
            }
        }

        // Check rate limit by IP
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \Nexus\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            header('Retry-After: ' . $ipLimit['retry_after']);
            return $this->errorResponse(
                $message,
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429,
                ['retry_after' => $ipLimit['retry_after']]
            );
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.*, t.configuration FROM users u LEFT JOIN tenants t ON u.tenant_id = t.id WHERE u.email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // SECURITY: Record successful login and clear failed attempts
            if (!empty($email)) {
                \Nexus\Core\RateLimiter::recordAttempt($email, 'email', true);
            }
            \Nexus\Core\RateLimiter::recordAttempt($ip, 'ip', true);

            // Detect if this is a mobile/API request for platform-appropriate token expiry
            $isMobile = TokenService::isMobileRequest();

            // Detect if client wants stateless auth (no session)
            // Mobile apps and SPAs should use Bearer tokens only, no session
            $wantsStateless = $isMobile || isset($_SERVER['HTTP_X_STATELESS_AUTH']);

            // =====================================================
            // 2FA CHECK - DISABLED SYSTEM-WIDE
            // To re-enable, uncomment the block below
            // =====================================================
            // $has2faEnabled = !empty($user['totp_enabled']) || TotpService::isEnabled((int)$user['id']);
            //
            // // Check for trusted device (skips 2FA if trusted)
            // $isTrustedDevice = $has2faEnabled && TotpService::isTrustedDevice((int)$user['id']);
            //
            // if ($has2faEnabled && !$isTrustedDevice) {
            //     // 2FA required - create challenge token instead of access tokens
            //     $twoFactorToken = TwoFactorChallengeManager::create(
            //         (int)$user['id'],
            //         ['totp', 'backup_code']
            //     );
            //
            //     // For session-based clients, also store in session for backward compatibility
            //     if (!$wantsStateless) {
            //         if (session_status() == PHP_SESSION_NONE) {
            //             session_start();
            //         }
            //         $_SESSION['pending_2fa_user_id'] = $user['id'];
            //         $_SESSION['pending_2fa_expires'] = time() + 300; // 5 minutes
            //     }
            //
            //     // Return 2FA required response - no access token yet
            //     return $this->jsonResponse([
            //         'success' => false,
            //         'requires_2fa' => true,
            //         'two_factor_token' => $twoFactorToken,
            //         'methods' => ['totp', 'backup_code'],
            //         'code' => ApiErrorCodes::AUTH_2FA_REQUIRED,
            //         'message' => 'Two-factor authentication required',
            //         // Include partial user info for UI
            //         'user' => [
            //             'id' => $user['id'],
            //             'first_name' => $user['first_name'],
            //             'email_masked' => $this->maskEmail($user['email'])
            //         ]
            //     ], 200); // 200 OK because this is expected flow, not an error
            // }

            // =====================================================
            // NO 2FA or TRUSTED DEVICE - Complete login normally
            // =====================================================

            // START SESSION ONLY FOR WEB CLIENTS (browsers using session cookies)
            // Skip session creation for mobile apps and SPAs to keep API stateless
            if (!$wantsStateless) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }
                // FIXED: Preserve layout preference before session regeneration
                $preservedLayout = $_SESSION['nexus_active_layout'] ?? $_SESSION['nexus_layout'] ?? null;

                // SECURITY: Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // FIXED: Restore layout preference after regeneration
                if ($preservedLayout) {
                    $_SESSION['nexus_active_layout'] = $preservedLayout;
                    $_SESSION['nexus_layout'] = $preservedLayout;
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email']; // For biometric login
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_logged_in'] = true;
            }

            // Generate secure tokens for mobile app
            $accessToken = TokenService::generateToken((int)$user['id'], (int)$user['tenant_id'], [
                'role' => $user['role'],
                'email' => $user['email']
            ], $isMobile);
            $refreshToken = TokenService::generateRefreshToken((int)$user['id'], (int)$user['tenant_id'], $isMobile);

            // Get the actual expiry time based on platform (mobile = 1 year, web = 2 hours)
            $accessTokenExpiry = TokenService::getAccessTokenExpiry($isMobile);
            $refreshTokenExpiry = TokenService::getRefreshTokenExpiry($isMobile);

            return $this->jsonResponse([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'avatar_url' => $user['avatar_url'],
                    'tenant_id' => $user['tenant_id']
                ],
                // New secure tokens for mobile apps
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $accessTokenExpiry, // Platform-aware: 1 year mobile, 2 hours web
                'refresh_expires_in' => $refreshTokenExpiry, // Platform-aware: 5 years mobile, 2 years web
                'is_mobile' => $isMobile, // Let client know what mode was detected
                // Legacy token for backwards compatibility
                'token' => $accessToken,
                'config' => json_decode($user['configuration'] ?? '{"modules": {"events": true, "polls": true, "goals": true, "volunteering": true, "resources": true}}', true)
            ]);
        }

        // SECURITY: Record failed login attempt
        if (!empty($email)) {
            \Nexus\Core\RateLimiter::recordAttempt($email, 'email', false);
        }
        \Nexus\Core\RateLimiter::recordAttempt($ip, 'ip', false);

        return $this->errorResponse(
            'Invalid credentials',
            ApiErrorCodes::AUTH_INVALID_CREDENTIALS,
            401
        );
    }

    public function register()
    {
        // SECURITY: Rate limiting to prevent registration abuse
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \Nexus\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            header('Retry-After: ' . $ipLimit['retry_after']);
            return $this->errorResponse(
                $message,
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429,
                ['retry_after' => $ipLimit['retry_after']]
            );
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? ''; // Still accept 'name' from API for compatibility, but split it
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $tenant_id = $data['tenant_id'] ?? 1;

        if (empty($name) || empty($email) || empty($password)) {
            return $this->errorResponse(
                'All fields required',
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                400
            );
        }

        $db = Database::getConnection();

        // Check exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return $this->errorResponse(
                'Email already registered',
                ApiErrorCodes::VALIDATION_DUPLICATE,
                409
            );
        }

        // Create user
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $parts = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, tenant_id, role) VALUES (?, ?, ?, ?, ?, 'member')");

        try {
            $stmt->execute([$firstName, $lastName, $email, $hashed, $tenant_id]);
            return $this->jsonResponse(['success' => true, 'message' => 'Registration successful']);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Server error',
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                500
            );
        }
    }
    public function checkSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user_id'])) {
            // Optional: Fetch fresh user data?
            // For speed, return session data
            return $this->jsonResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'role' => $_SESSION['user_role'] ?? 'member',
                    'tenant_id' => $_SESSION['tenant_id'] ?? 1
                ]
            ]);
        }

        return $this->errorResponse(
            'Not authenticated',
            ApiErrorCodes::AUTH_TOKEN_MISSING,
            401,
            ['authenticated' => false]
        );
    }

    /**
     * Session heartbeat endpoint - keeps session alive for mobile apps
     * This is critical for preventing unexpected logouts on mobile devices
     * where sessions can expire while the app is backgrounded.
     *
     * Also checks Bearer token validity and signals if refresh is needed.
     */
    public function heartbeat()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is logged in via session
        $isSessionAuth = !empty($_SESSION['user_id']);

        // Also check Bearer token authentication
        // IMPORTANT: Bearer auth is STATELESS - do NOT sync to $_SESSION
        $tokenInfo = null;
        $isBearerAuth = false;
        $bearerUserId = null;
        $bearerTenantId = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = TokenService::validateToken($token);
            if ($payload) {
                $tokenInfo = [
                    'valid' => true,
                    'expires_at' => date('c', $payload['exp']),
                    'time_remaining' => $payload['exp'] - time(),
                    'needs_refresh' => TokenService::needsRefresh($token)
                ];

                // Mark as Bearer-authenticated but DO NOT touch $_SESSION
                // This keeps the API stateless for mobile/SPA clients
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

        // Determine if user is authenticated (session OR Bearer token)
        $isAuthenticated = $isSessionAuth || $isBearerAuth;

        // Check if user is logged in (via session or Bearer token)
        if (!$isAuthenticated) {
            return $this->errorResponse(
                'Unauthorized',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401,
                ['authenticated' => false, 'token' => $tokenInfo]
            );
        }

        // Get the user ID from whichever auth method was used
        $userId = $isBearerAuth ? $bearerUserId : ($_SESSION['user_id'] ?? null);
        $tenantId = $isBearerAuth ? $bearerTenantId : ($_SESSION['tenant_id'] ?? null);

        // NOTE: Session ID regeneration removed from heartbeat to prevent race conditions
        // with concurrent API requests. Session is regenerated only at login time.
        // This prevents 401 errors when multiple requests are in-flight during regeneration.

        // For Bearer-authenticated requests, skip session-related operations
        // This keeps the API stateless for mobile/SPA clients
        if (!$isBearerAuth && $isSessionAuth) {
            // Verify user still exists in database (prevents stale sessions for deleted users)
            // Only check once per 5 minutes to reduce DB load
            $lastUserCheck = $_SESSION['_last_user_check'] ?? 0;
            if (time() - $lastUserCheck >= 300) {
                try {
                    $user = \Nexus\Models\User::findById($_SESSION['user_id'], false);

                    // Robust retry - don't logout on transient DB issues
                    if (!$user) {
                        $maxRetries = 3;
                        for ($i = 0; $i < $maxRetries && !$user; $i++) {
                            usleep(200000); // 200ms delay
                            $user = \Nexus\Models\User::findById($_SESSION['user_id'], false);
                        }
                    }

                    if (!$user) {
                        error_log("[Heartbeat] User ID {$_SESSION['user_id']} not found after retries - possible deleted user");
                        return $this->errorResponse(
                            'User not found',
                            ApiErrorCodes::AUTH_ACCOUNT_DELETED,
                            401,
                            ['authenticated' => false, 'should_reauth' => true]
                        );
                    }
                    $_SESSION['_last_user_check'] = time();
                } catch (\Throwable $e) {
                    error_log('[Heartbeat] User check failed: ' . $e->getMessage());
                }
            }

            // Update last activity timestamp in session
            $_SESSION['_last_heartbeat'] = time();
        }

        // Calculate session/token expiry info
        $sessionLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt = date('c', time() + $sessionLifetime);

        // Update user's last_active_at in database (throttled to once per minute)
        // This applies to both session and Bearer-authenticated users
        static $lastActiveUpdated = false;
        if (!$lastActiveUpdated && $userId) {
            // For session auth, check session-based throttle
            // For Bearer auth, always update (stateless, no throttle tracking)
            $shouldUpdate = $isBearerAuth;
            if (!$isBearerAuth) {
                $lastUpdate = $_SESSION['_last_active_update'] ?? 0;
                $shouldUpdate = (time() - $lastUpdate >= 60);
            }

            if ($shouldUpdate) {
                try {
                    if (class_exists('\Nexus\Models\User')) {
                        \Nexus\Models\User::updateLastActive((int)$userId);
                        if (!$isBearerAuth) {
                            $_SESSION['_last_active_update'] = time();
                        }
                        $lastActiveUpdated = true;
                    }
                } catch (\Throwable $e) {
                    // Silently fail - not critical
                }
            }
        }

        $response = [
            'success' => true,
            'authenticated' => true,
            'auth_type' => $isBearerAuth ? 'bearer' : 'session',
            'user_id' => $userId
        ];

        // Include session info only for session-authenticated requests
        if (!$isBearerAuth) {
            $response['expires_at'] = $expiresAt;
            $response['session_lifetime'] = $sessionLifetime;
        }

        // Include token info if a Bearer token was provided
        if ($tokenInfo !== null) {
            $response['token'] = $tokenInfo;
        }

        return $this->jsonResponse($response);
    }

    /**
     * Restore session from Bearer token
     * Mobile apps should call this on page load if they have tokens but no session
     * This bridges the gap between token-based auth and PHP session-based pages
     *
     * @deprecated This endpoint violates stateless auth principles. Mobile apps should use
     *             Bearer tokens directly without session sync. Will be removed after mobile
     *             app audit confirms no critical dependencies. Sunset: 2026-08-01
     */
    public function restoreSession()
    {
        // DEPRECATED: Add deprecation headers
        header('X-API-Deprecated: true');
        header('Sunset: Sat, 01 Aug 2026 00:00:00 GMT');

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Check Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->errorResponse(
                'Bearer token required',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        $token = $matches[1];
        $payload = TokenService::validateToken($token);

        if (!$payload || ($payload['type'] ?? 'access') !== 'access') {
            return $this->errorResponse(
                'Invalid or expired token',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId) {
            return $this->errorResponse(
                'Invalid token payload',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        // Fetch user data to populate session
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, avatar_url, tenant_id, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                401
            );
        }

        // Restore full session (same as regular login)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'] ?? 'member';
        $_SESSION['role'] = $user['role'] ?? 'member';
        $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';
        $_SESSION['is_logged_in'] = true;

        // Set is_admin flag
        $adminRoles = ['admin', 'super_admin', 'tenant_admin'];
        $_SESSION['is_admin'] = in_array($user['role'], $adminRoles) ? 1 : 0;

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Session restored from token',
            'user_id' => $user['id'],
            'session_id' => session_id(),
            '_deprecated' => [
                'message' => 'This endpoint is deprecated. Mobile apps should use Bearer tokens directly without session sync.',
                'sunset' => '2026-08-01',
                'replacement' => 'Use Authorization: Bearer <token> header on all API requests'
            ]
        ]);
    }

    /**
     * Refresh session - extends session lifetime
     * Mobile apps should call this when coming to foreground
     */
    public function refreshSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return $this->errorResponse(
                'Unauthorized',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        // NOTE: Session ID regeneration removed to prevent race conditions
        // with concurrent API requests. Session is regenerated only at login time.
        // Regenerating here caused 401 errors when multiple requests were in-flight.
        $_SESSION['_session_refreshed_at'] = time();

        // Calculate new expiry
        $sessionLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt = date('c', time() + $sessionLifetime);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Session refreshed',
            'expires_at' => $expiresAt,
            'session_lifetime' => $sessionLifetime
        ]);
    }

    /**
     * Refresh access token using a refresh token
     * Mobile apps should call this when access token is about to expire
     */
    public function refreshToken()
    {
        $data = $this->getJsonInput();
        $refreshToken = $data['refresh_token'] ?? '';

        // Also check Authorization header
        if (empty($refreshToken)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                $refreshToken = $matches[1];
            }
        }

        if (empty($refreshToken)) {
            return $this->errorResponse(
                'Refresh token required',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        // Validate the refresh token
        $payload = TokenService::validateToken($refreshToken);

        if (!$payload) {
            return $this->errorResponse(
                'Invalid or expired refresh token',
                ApiErrorCodes::AUTH_TOKEN_EXPIRED,
                401
            );
        }

        // Check it's actually a refresh token
        if (($payload['type'] ?? '') !== 'refresh') {
            return $this->errorResponse(
                'Invalid token type',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId || !$tenantId) {
            return $this->errorResponse(
                'Invalid token payload',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401
            );
        }

        // Verify user still exists and is active
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, email, role, status FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->errorResponse(
                'User not found',
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                401
            );
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            return $this->errorResponse(
                'Account suspended',
                ApiErrorCodes::AUTH_ACCOUNT_SUSPENDED,
                403
            );
        }

        // Detect if this is a mobile request for platform-appropriate token expiry
        $isMobile = TokenService::isMobileRequest();

        // Generate new tokens with platform-appropriate expiry
        $newAccessToken = TokenService::generateToken((int)$userId, (int)$tenantId, [
            'role' => $user['role'],
            'email' => $user['email']
        ], $isMobile);

        // Get expiry times for response
        $accessTokenExpiry = TokenService::getAccessTokenExpiry($isMobile);
        $refreshTokenExpiry = TokenService::getRefreshTokenExpiry($isMobile);

        // Only generate new refresh token if current one is close to expiring (< 30 days)
        $refreshTimeRemaining = TokenService::getTimeRemaining($refreshToken);
        $newRefreshToken = null;

        if ($refreshTimeRemaining < 2592000) { // 30 days
            $newRefreshToken = TokenService::generateRefreshToken((int)$userId, (int)$tenantId, $isMobile);
        }

        $response = [
            'success' => true,
            'access_token' => $newAccessToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTokenExpiry, // Platform-aware: 1 year mobile, 2 hours web
            'is_mobile' => $isMobile,
            // Legacy compatibility
            'token' => $newAccessToken
        ];

        if ($newRefreshToken) {
            $response['refresh_token'] = $newRefreshToken;
            $response['refresh_expires_in'] = $refreshTokenExpiry;
        }

        return $this->jsonResponse($response);
    }

    /**
     * Validate a token and return user info
     * Useful for mobile apps to check if stored token is still valid
     */
    public function validateToken()
    {
        $token = null;

        // Check Authorization header first
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // Fallback to request body
        if (!$token) {
            $data = $this->getJsonInput();
            $token = $data['token'] ?? $data['access_token'] ?? '';
        }

        if (empty($token)) {
            return $this->errorResponse(
                'Token required',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                400
            );
        }

        $payload = TokenService::validateToken($token);

        if (!$payload) {
            return $this->errorResponse(
                'Invalid or expired token',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                401,
                ['valid' => false]
            );
        }

        // Return token info
        return $this->jsonResponse([
            'valid' => true,
            'user_id' => $payload['user_id'],
            'tenant_id' => $payload['tenant_id'],
            'type' => $payload['type'] ?? 'access',
            'expires_at' => date('c', $payload['exp']),
            'time_remaining' => $payload['exp'] - time(),
            'needs_refresh' => TokenService::needsRefresh($token)
        ]);
    }

    /**
     * Logout - destroys session and optionally revokes refresh token
     * POST /api/auth/logout
     *
     * Body (optional): { "refresh_token": "..." } to also revoke the refresh token
     */
    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Get user ID before clearing session
        $userId = $_SESSION['user_id'] ?? null;

        // Also check Bearer token for user ID
        if (!$userId) {
            $userId = $this->getAuthenticatedUserId();
        }

        // If a refresh token is provided, revoke it
        $data = $this->getJsonInput();
        $refreshToken = $data['refresh_token'] ?? '';
        $tokenRevoked = false;

        if (!empty($refreshToken) && $userId) {
            $tokenRevoked = TokenService::revokeToken($refreshToken, $userId);
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
            'message' => 'Logged out successfully'
        ];

        if ($tokenRevoked) {
            $response['refresh_token_revoked'] = true;
        }

        return $this->jsonResponse($response);
    }

    /**
     * Revoke a specific refresh token
     * POST /api/auth/revoke
     *
     * Requires Bearer token authentication.
     * Body: { "refresh_token": "..." }
     */
    public function revokeToken()
    {
        // Require Bearer authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->errorResponse(
                'Authentication required',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        $data = $this->getJsonInput();
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            return $this->errorResponse(
                'Refresh token required',
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                400
            );
        }

        // Revoke the token
        $revoked = TokenService::revokeToken($refreshToken, $userId);

        if (!$revoked) {
            return $this->errorResponse(
                'Invalid refresh token or already revoked',
                ApiErrorCodes::AUTH_TOKEN_INVALID,
                400
            );
        }

        return $this->jsonResponse([
            'data' => ['revoked' => true]
        ]);
    }

    /**
     * Revoke all refresh tokens for the authenticated user
     * POST /api/auth/revoke-all
     *
     * Requires Bearer token authentication.
     * Use for "log out everywhere" functionality.
     */
    public function revokeAllTokens()
    {
        // Require Bearer authentication
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            return $this->errorResponse(
                'Authentication required',
                ApiErrorCodes::AUTH_TOKEN_MISSING,
                401
            );
        }

        // Revoke all tokens for this user
        $revokedCount = TokenService::revokeAllTokensForUser($userId);

        return $this->jsonResponse([
            'data' => [
                'revoked_count' => $revokedCount,
                'message' => 'All refresh tokens have been revoked. You will need to log in again on all devices.'
            ]
        ]);
    }

    /**
     * Get authenticated user ID from Bearer token
     *
     * @return int|null
     */
    private function getAuthenticatedUserId(): ?int
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        $payload = TokenService::validateToken($token);

        if (!$payload || ($payload['type'] ?? 'access') !== 'access') {
            return null;
        }

        return $payload['user_id'] ?? null;
    }

    /**
     * Get a CSRF token for session-based authentication
     * GET /api/auth/csrf-token
     *
     * This endpoint is for SPAs that use session-based auth (not Bearer tokens).
     * Bearer-authenticated clients do NOT need CSRF tokens.
     *
     * Note: Rate limited to prevent token farming.
     */
    public function getCsrfToken()
    {
        // Rate limit this endpoint (10 per minute per IP)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitResult = \Nexus\Core\RateLimiter::check($ip . ':csrf', 'csrf_token');

        if ($rateLimitResult['limited']) {
            header('Retry-After: ' . $rateLimitResult['retry_after']);
            return $this->errorResponse(
                'Too many requests. Please try again later.',
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                429
            );
        }

        \Nexus\Core\RateLimiter::recordAttempt($ip . ':csrf', 'csrf_token', true);

        // Generate and return the CSRF token
        $token = \Nexus\Core\Csrf::generate();

        return $this->jsonResponse([
            'data' => [
                'csrf_token' => $token
            ]
        ]);
    }

    /**
     * Mask email address for display (e.g., j***@example.com)
     *
     * @param string $email
     * @return string
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Show first character, mask the rest
        $maskedLocal = strlen($local) > 1
            ? $local[0] . str_repeat('*', min(strlen($local) - 1, 5))
            : '*';

        return $maskedLocal . '@' . $domain;
    }
}
