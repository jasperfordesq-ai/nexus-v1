<?php

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
    // Mobile: Longer access tokens (7 days) + 30-day refresh for "install and forget" experience
    private const ACCESS_TOKEN_EXPIRY_WEB = 7200;           // 2 hours (desktop/web)
    private const ACCESS_TOKEN_EXPIRY_MOBILE = 604800;      // 7 days (mobile - like Facebook/Instagram)
    private const REFRESH_TOKEN_EXPIRY = 2592000;           // 30 days (both platforms)

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
            // Derive from APP_KEY or use a fallback (not recommended for production)
            $appKey = getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? 'nexus-default-key-change-me');
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
     * Generate a refresh token for a user
     * Refresh tokens have longer expiry and can be used to get new access tokens
     *
     * @param int $userId
     * @param int $tenantId
     * @return string The signed refresh token
     */
    public static function generateRefreshToken(int $userId, int $tenantId): string
    {
        return self::createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'refresh',
            // Add a unique identifier for this refresh token (for revocation)
            'jti' => bin2hex(random_bytes(16))
        ], self::REFRESH_TOKEN_EXPIRY);
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
}
