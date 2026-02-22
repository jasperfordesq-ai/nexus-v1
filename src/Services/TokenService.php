<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * TokenService - Secure token generation and validation for mobile API authentication
 *
 * This service provides JWT-like tokens without external dependencies.
 * Tokens are HMAC-signed and include expiration times.
 *
 * Token format: base64(header).base64(payload).base64(signature)
 *
 * Usage:
 *   // Generate token on login
 *   $token = TokenService::generateToken($userId, $tenantId);
 *
 *   // Validate token on API request
 *   $payload = TokenService::validateToken($token);
 *   if ($payload) {
 *       $userId = $payload['user_id'];
 *   }
 */
class TokenService
{
    // Token expiration times
    // Desktop/Web: Short-lived access tokens (2 hours) for security
    // Mobile: Very long access tokens (1 year) for "install and forget" experience - users stay logged in indefinitely
    private const ACCESS_TOKEN_EXPIRY_WEB = 7200;           // 2 hours (desktop/web)
    private const ACCESS_TOKEN_EXPIRY_MOBILE = 31536000;    // 1 year (mobile - stay logged in indefinitely)
    private const REFRESH_TOKEN_EXPIRY = 63072000;          // 2 years (allows indefinite login with periodic refresh)
    private const REFRESH_TOKEN_EXPIRY_MOBILE = 157680000;  // 5 years (mobile - essentially indefinite)

    // Algorithm identifier
    private const ALGORITHM = 'HS256';

    /**
     * Get the secret key for signing tokens
     * Falls back to a derived key from APP_KEY if JWT_SECRET not set
     */
    private static function getSecretKey(): string
    {
        $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? null);

        if (!$secret) {
            // Derive from APP_KEY - no insecure fallback allowed
            $appKey = getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? null);
            if (!$appKey) {
                throw new \RuntimeException('Security configuration error: JWT_SECRET or APP_KEY must be set in environment');
            }
            $secret = hash('sha256', $appKey . 'jwt-token-secret');
        }

        return $secret;
    }

    /**
     * Check if the current request is from a mobile app
     * Mobile apps get longer token lifetimes for "install and forget" experience
     */
    public static function isMobileRequest(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check for Capacitor/native app indicators
        $isCapacitor = (
            strpos($userAgent, 'Capacitor') !== false ||
            strpos($userAgent, 'nexus-mobile') !== false ||
            isset($_SERVER['HTTP_X_CAPACITOR_APP']) ||
            isset($_SERVER['HTTP_X_NEXUS_MOBILE'])
        );

        // Check for mobile user agents (iOS/Android WebView or browsers)
        $isMobileUA = (
            strpos($userAgent, 'Mobile') !== false ||
            strpos($userAgent, 'Android') !== false ||
            strpos($userAgent, 'iPhone') !== false ||
            strpos($userAgent, 'iPad') !== false
        );

        return $isCapacitor || $isMobileUA;
    }

    /**
     * Get the appropriate access token expiry based on platform
     */
    public static function getAccessTokenExpiry(bool $isMobile = null): int
    {
        if ($isMobile === null) {
            $isMobile = self::isMobileRequest();
        }

        return $isMobile ? self::ACCESS_TOKEN_EXPIRY_MOBILE : self::ACCESS_TOKEN_EXPIRY_WEB;
    }

    /**
     * Generate an access token for a user
     *
     * @param int $userId
     * @param int $tenantId
     * @param array $additionalClaims Optional additional claims
     * @param bool|null $isMobile Force mobile/web mode (null = auto-detect)
     * @return string The signed token
     */
    public static function generateToken(int $userId, int $tenantId, array $additionalClaims = [], ?bool $isMobile = null): string
    {
        $expiry = self::getAccessTokenExpiry($isMobile);
        $platform = ($isMobile ?? self::isMobileRequest()) ? 'mobile' : 'web';

        return self::createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'access',
            'platform' => $platform,  // Track which platform token was issued for
            ...$additionalClaims
        ], $expiry);
    }

    /**
     * Get the appropriate refresh token expiry based on platform
     */
    public static function getRefreshTokenExpiry(bool $isMobile = null): int
    {
        if ($isMobile === null) {
            $isMobile = self::isMobileRequest();
        }

        return $isMobile ? self::REFRESH_TOKEN_EXPIRY_MOBILE : self::REFRESH_TOKEN_EXPIRY;
    }

    /**
     * Generate a refresh token for a user
     * Refresh tokens have longer expiry and can be used to get new access tokens
     *
     * @param int $userId
     * @param int $tenantId
     * @param bool|null $isMobile Force mobile/web mode (null = auto-detect)
     * @return string The signed refresh token
     */
    public static function generateRefreshToken(int $userId, int $tenantId, ?bool $isMobile = null): string
    {
        $expiry = self::getRefreshTokenExpiry($isMobile);

        return self::createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'refresh',
            // Add a unique identifier for this refresh token (for revocation)
            'jti' => bin2hex(random_bytes(16))
        ], $expiry);
    }

    /**
     * Create a signed token with the given payload
     *
     * @param array $payload
     * @param int $expirySeconds
     * @return string
     */
    private static function createToken(array $payload, int $expirySeconds): string
    {
        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'JWT'
        ];

        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,           // Issued at
            'exp' => $now + $expirySeconds,  // Expiration time
            'nbf' => $now,           // Not before
        ]);

        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Validate a token and return its payload if valid
     *
     * @param string $token
     * @return array|null The payload if valid, null if invalid
     */
    public static function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = self::sign($headerEncoded . '.' . $payloadEncoded);
        $providedSignature = self::base64UrlDecode($signatureEncoded);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        // Check not-before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Validate a refresh token with revocation check
     *
     * @param string $token
     * @return array|null The payload if valid and not revoked, null otherwise
     */
    public static function validateRefreshToken(string $token): ?array
    {
        $payload = self::validateToken($token);

        if (!$payload) {
            return null;
        }

        // Ensure it's a refresh token
        if (($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        // Check if revoked
        if (self::isTokenRevoked($token)) {
            return null;
        }

        return $payload;
    }

    /**
     * Check if a token is expired (without full validation)
     *
     * @param string $token
     * @return bool
     */
    public static function isExpired(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return true;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        if (!$payload || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < time();
    }

    /**
     * Get token expiration time
     *
     * @param string $token
     * @return int|null Unix timestamp or null if invalid
     */
    public static function getExpiration(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        return $payload['exp'] ?? null;
    }

    /**
     * Get remaining time until token expires
     *
     * @param string $token
     * @return int Seconds remaining (negative if expired)
     */
    public static function getTimeRemaining(string $token): int
    {
        $exp = self::getExpiration($token);

        if ($exp === null) {
            return -1;
        }

        return $exp - time();
    }

    /**
     * Check if token needs refresh (less than 5 minutes remaining)
     *
     * @param string $token
     * @return bool
     */
    public static function needsRefresh(string $token): bool
    {
        return self::getTimeRemaining($token) < 300; // 5 minutes
    }

    /**
     * Create HMAC signature
     *
     * @param string $data
     * @return string
     */
    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::getSecretKey(), true);
    }

    /**
     * Base64 URL-safe encoding
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Extract user ID from token without full validation
     * Useful for logging/debugging
     *
     * @param string $token
     * @return int|null
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        return $payload['user_id'] ?? null;
    }

    // ============================================
    // TOKEN REVOCATION
    // ============================================

    /**
     * Revoke a refresh token by its jti claim
     *
     * @param string $refreshToken The refresh token to revoke
     * @param int $userId The user ID (for ownership verification)
     * @return bool True if revoked, false if token was invalid or not a refresh token
     */
    public static function revokeToken(string $refreshToken, int $userId): bool
    {
        $payload = self::validateToken($refreshToken);

        if (!$payload) {
            return false;
        }

        // Only refresh tokens can be revoked (they have jti)
        if (($payload['type'] ?? '') !== 'refresh') {
            return false;
        }

        // Verify ownership
        if (($payload['user_id'] ?? 0) !== $userId) {
            return false;
        }

        $jti = $payload['jti'] ?? null;
        if (!$jti) {
            return false;
        }

        // Store the revocation
        try {
            $db = \Nexus\Core\Database::getConnection();

            // Check if already revoked
            $stmt = $db->prepare("SELECT id FROM revoked_tokens WHERE jti = ?");
            $stmt->execute([$jti]);
            if ($stmt->fetch()) {
                return true; // Already revoked
            }

            // Insert revocation record
            $stmt = $db->prepare(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))"
            );
            $stmt->execute([$userId, $jti, $payload['exp']]);

            return true;
        } catch (\Exception $e) {
            error_log('[TokenService] Failed to revoke token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke all refresh tokens for a user
     * This is useful for "log out everywhere" functionality
     *
     * @param int $userId The user ID
     * @return int Number of active tokens that were invalidated (estimate based on new revocations)
     */
    public static function revokeAllTokensForUser(int $userId): int
    {
        try {
            $db = \Nexus\Core\Database::getConnection();

            // We can't enumerate all tokens (they're stateless), but we can:
            // 1. Record a "revoke all" timestamp for the user
            // 2. Check this timestamp during validation

            // For now, we'll use a special jti entry that marks "all tokens before this time are invalid"
            $specialJti = 'revoke_all_' . $userId . '_' . time();
            $farFutureExpiry = time() + (10 * 365 * 24 * 60 * 60); // 10 years

            $stmt = $db->prepare(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))"
            );
            $stmt->execute([$userId, $specialJti, $farFutureExpiry]);

            // Also store the revoke-all timestamp in a way we can check
            // Update or insert a user-level "tokens_revoked_at" marker
            $stmt = $db->prepare(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))
                 ON DUPLICATE KEY UPDATE revoked_at = NOW()"
            );
            $globalJti = 'global_revoke_' . $userId;
            $stmt->execute([$userId, $globalJti, $farFutureExpiry]);

            // Count existing revocations for this user (rough estimate)
            $stmt = $db->prepare("SELECT COUNT(*) FROM revoked_tokens WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = (int) $stmt->fetchColumn();

            return $count;
        } catch (\Exception $e) {
            error_log('[TokenService] Failed to revoke all tokens: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a refresh token has been revoked
     *
     * @param string $refreshToken The token to check
     * @return bool True if revoked, false if valid
     */
    public static function isTokenRevoked(string $refreshToken): bool
    {
        $payload = self::validateToken($refreshToken);

        if (!$payload) {
            return true; // Invalid token treated as revoked
        }

        $jti = $payload['jti'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $iat = $payload['iat'] ?? 0;

        if (!$jti || !$userId) {
            return true;
        }

        try {
            $db = \Nexus\Core\Database::getConnection();

            // Check if this specific token is revoked
            $stmt = $db->prepare("SELECT id FROM revoked_tokens WHERE jti = ?");
            $stmt->execute([$jti]);
            if ($stmt->fetch()) {
                return true;
            }

            // Check if there's a "revoke all" entry for this user that's newer than the token
            $stmt = $db->prepare(
                "SELECT revoked_at FROM revoked_tokens WHERE jti = ? AND revoked_at > FROM_UNIXTIME(?)"
            );
            $globalJti = 'global_revoke_' . $userId;
            $stmt->execute([$globalJti, $iat]);
            if ($stmt->fetch()) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            error_log('[TokenService] Failed to check token revocation: ' . $e->getMessage());
            return true; // Fail closed — treat as revoked on DB errors for security
        }
    }

    /**
     * Clean up expired revocation records
     * Should be called periodically via cron
     *
     * @return int Number of records deleted
     */
    public static function cleanupExpiredRevocations(): int
    {
        try {
            $db = \Nexus\Core\Database::getConnection();
            $stmt = $db->prepare("DELETE FROM revoked_tokens WHERE expires_at < NOW() AND jti NOT LIKE 'global_revoke_%'");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('[TokenService] Failed to cleanup revocations: ' . $e->getMessage());
            return 0;
        }
    }
}
