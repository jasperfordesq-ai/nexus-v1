<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Services\TokenService;

class AuthController
{
    /**
     * Helper to return JSON response
     */
    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
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
            return $this->jsonResponse(['error' => 'Email and password required'], 400);
        }

        // SECURITY: Rate limiting to prevent brute force attacks
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check rate limit by email
        if (!empty($email)) {
            $emailLimit = \Nexus\Core\RateLimiter::check($email, 'email');
            if ($emailLimit['limited']) {
                $message = \Nexus\Core\RateLimiter::getRetryMessage($emailLimit['retry_after']);
                return $this->jsonResponse(['error' => $message, 'retry_after' => $emailLimit['retry_after']], 429);
            }
        }

        // Check rate limit by IP
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \Nexus\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            return $this->jsonResponse(['error' => $message, 'retry_after' => $ipLimit['retry_after']], 429);
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
            // START SESSION FOR WEB CLIENTS
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

            // Detect if this is a mobile request for platform-appropriate token expiry
            $isMobile = TokenService::isMobileRequest();

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

        return $this->jsonResponse(['error' => 'Invalid credentials', 'success' => false], 401);
    }

    public function register()
    {
        // SECURITY: Rate limiting to prevent registration abuse
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipLimit = \Nexus\Core\RateLimiter::check($ip, 'ip');
        if ($ipLimit['limited']) {
            $message = \Nexus\Core\RateLimiter::getRetryMessage($ipLimit['retry_after']);
            return $this->jsonResponse(['error' => $message, 'retry_after' => $ipLimit['retry_after']], 429);
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? ''; // Still accept 'name' from API for compatibility, but split it
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $tenant_id = $data['tenant_id'] ?? 1;

        if (empty($name) || empty($email) || empty($password)) {
            return $this->jsonResponse(['error' => 'All fields required'], 400);
        }

        $db = Database::getConnection();

        // Check exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return $this->jsonResponse(['error' => 'Email already registered'], 409);
        }

        // Create
        // Create
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $parts = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password_hash, tenant_id, role) VALUES (?, ?, ?, ?, ?, 'member')");

        try {
            $stmt->execute([$firstName, $lastName, $email, $hashed, $tenant_id]);
            return $this->jsonResponse(['success' => true, 'message' => 'Registration successful']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => 'Server error'], 500);
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

        return $this->jsonResponse(['authenticated' => false], 401);
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
        $tokenInfo = null;
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

                // If session is not set but token is valid, consider authenticated
                if (!$isSessionAuth) {
                    $isSessionAuth = true;
                    $_SESSION['user_id'] = $payload['user_id'];
                    $_SESSION['tenant_id'] = $payload['tenant_id'];
                }
            } else {
                $tokenInfo = [
                    'valid' => false,
                    'needs_refresh' => true
                ];
            }
        }

        // Check if user is logged in
        if (!$isSessionAuth) {
            return $this->jsonResponse([
                'error' => 'Unauthorized',
                'authenticated' => false,
                'token' => $tokenInfo
            ], 401);
        }

        // NOTE: Session ID regeneration removed from heartbeat to prevent race conditions
        // with concurrent API requests. Session is regenerated only at login time.
        // This prevents 401 errors when multiple requests are in-flight during regeneration.

        // Verify user still exists in database (prevents stale sessions for deleted users)
        // Only check once per 5 minutes to reduce DB load
        $lastUserCheck = $_SESSION['_last_user_check'] ?? 0;
        if (time() - $lastUserCheck >= 300) {
            try {
                $user = \Nexus\Models\User::findById($_SESSION['user_id'], false); // Don't enforce tenant for this check

                // Robust retry - don't logout on transient DB issues
                if (!$user) {
                    $maxRetries = 3;
                    for ($i = 0; $i < $maxRetries && !$user; $i++) {
                        usleep(200000); // 200ms delay
                        $user = \Nexus\Models\User::findById($_SESSION['user_id'], false);
                    }
                }

                if (!$user) {
                    // User genuinely not found after retries - but DON'T destroy session on mobile
                    // Just log and return 401 - let the app handle re-auth
                    error_log("[Heartbeat] User ID {$_SESSION['user_id']} not found after retries - possible deleted user");
                    return $this->jsonResponse([
                        'error' => 'User not found',
                        'authenticated' => false,
                        'should_reauth' => true // Signal to mobile app to re-authenticate
                    ], 401);
                }
                $_SESSION['_last_user_check'] = time();
            } catch (\Throwable $e) {
                // DB error - don't logout, just skip the check
                // Log for debugging but don't fail the heartbeat
                error_log('[Heartbeat] User check failed: ' . $e->getMessage());
            }
        }

        // Update last activity timestamp
        $_SESSION['_last_heartbeat'] = time();

        // Calculate session expiry based on gc_maxlifetime
        $sessionLifetime = (int) ini_get('session.gc_maxlifetime');
        $expiresAt = date('c', time() + $sessionLifetime);

        // Update user's last_active_at in database (throttled to once per minute)
        $lastUpdate = $_SESSION['_last_active_update'] ?? 0;
        if (time() - $lastUpdate >= 60) {
            try {
                if (class_exists('\Nexus\Models\User')) {
                    \Nexus\Models\User::updateLastActive((int)$_SESSION['user_id']);
                    $_SESSION['_last_active_update'] = time();
                }
            } catch (\Throwable $e) {
                // Silently fail - not critical
            }
        }

        $response = [
            'success' => true,
            'authenticated' => true,
            'expires_at' => $expiresAt,
            'session_lifetime' => $sessionLifetime,
            'user_id' => $_SESSION['user_id']
        ];

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
     */
    public function restoreSession()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Check Bearer token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) || !preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $this->jsonResponse(['error' => 'Bearer token required'], 400);
        }

        $token = $matches[1];
        $payload = TokenService::validateToken($token);

        if (!$payload || ($payload['type'] ?? 'access') !== 'access') {
            return $this->jsonResponse(['error' => 'Invalid or expired token'], 401);
        }

        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId) {
            return $this->jsonResponse(['error' => 'Invalid token payload'], 401);
        }

        // Fetch user data to populate session
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, first_name, last_name, email, role, avatar_url, tenant_id, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->jsonResponse(['error' => 'User not found'], 401);
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
            'session_id' => session_id()
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
            return $this->jsonResponse(['error' => 'Unauthorized'], 401);
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
            return $this->jsonResponse(['error' => 'Refresh token required'], 400);
        }

        // Validate the refresh token
        $payload = TokenService::validateToken($refreshToken);

        if (!$payload) {
            return $this->jsonResponse(['error' => 'Invalid or expired refresh token'], 401);
        }

        // Check it's actually a refresh token
        if (($payload['type'] ?? '') !== 'refresh') {
            return $this->jsonResponse(['error' => 'Invalid token type'], 401);
        }

        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId || !$tenantId) {
            return $this->jsonResponse(['error' => 'Invalid token payload'], 401);
        }

        // Verify user still exists and is active
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, email, role, status FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch();

        if (!$user) {
            return $this->jsonResponse(['error' => 'User not found'], 401);
        }

        if (($user['status'] ?? 'active') === 'suspended') {
            return $this->jsonResponse(['error' => 'Account suspended'], 403);
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
            return $this->jsonResponse(['error' => 'Token required'], 400);
        }

        $payload = TokenService::validateToken($token);

        if (!$payload) {
            return $this->jsonResponse([
                'valid' => false,
                'error' => 'Invalid or expired token'
            ], 401);
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
     * Logout - destroys session and invalidates tokens
     * POST /api/auth/logout
     */
    public function logout()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
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

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
