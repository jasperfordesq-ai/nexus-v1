<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TokenService — Laravel DI-based JWT token service.
 *
 * Handles HMAC-signed token generation, validation, revocation,
 * and impersonation tokens. Self-contained — no legacy delegation.
 */
class TokenService
{
    // Token expiration times
    private const ACCESS_TOKEN_EXPIRY_WEB = 7200;           // 2 hours (desktop/web)
    private const ACCESS_TOKEN_EXPIRY_MOBILE = 2592000;     // 30 days (mobile)
    private const REFRESH_TOKEN_EXPIRY = 63072000;          // 2 years
    private const REFRESH_TOKEN_EXPIRY_MOBILE = 157680000;  // 5 years (mobile)
    private const IMPERSONATION_TOKEN_EXPIRY = 300;         // 5 minutes

    private const ALGORITHM = 'HS256';

    /**
     * Get the secret key for signing tokens.
     */
    private function getSecretKey(): string
    {
        $secret = config('app.jwt_secret') ?: env('JWT_SECRET');

        if (!$secret) {
            $appKey = config('app.key');
            if (!$appKey) {
                throw new \RuntimeException('Security configuration error: JWT_SECRET or APP_KEY must be set');
            }
            $secret = hash('sha256', $appKey . 'jwt-token-secret');
        }

        return $secret;
    }

    /**
     * Check if the current request is from a mobile app (Capacitor/native).
     */
    public function isMobileRequest(): bool
    {
        $userAgent = request()->userAgent() ?? '';

        return (
            str_contains($userAgent, 'Capacitor') ||
            str_contains($userAgent, 'nexus-mobile') ||
            request()->hasHeader('X-Capacitor-App') ||
            request()->hasHeader('X-Nexus-Mobile')
        );
    }

    /**
     * Get the appropriate access token expiry based on platform.
     */
    public function getAccessTokenExpiry(?bool $isMobile = null): int
    {
        if ($isMobile === null) {
            $isMobile = $this->isMobileRequest();
        }

        return $isMobile ? self::ACCESS_TOKEN_EXPIRY_MOBILE : self::ACCESS_TOKEN_EXPIRY_WEB;
    }

    /**
     * Get the appropriate refresh token expiry based on platform.
     */
    public function getRefreshTokenExpiry(?bool $isMobile = null): int
    {
        if ($isMobile === null) {
            $isMobile = $this->isMobileRequest();
        }

        return $isMobile ? self::REFRESH_TOKEN_EXPIRY_MOBILE : self::REFRESH_TOKEN_EXPIRY;
    }

    /**
     * Generate an access token for a user.
     */
    public function generateToken(int $userId, int $tenantId, array $additionalClaims = [], ?bool $isMobile = null): string
    {
        $expiry = $this->getAccessTokenExpiry($isMobile);
        $platform = ($isMobile ?? $this->isMobileRequest()) ? 'mobile' : 'web';

        return $this->createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'access',
            'platform' => $platform,
            ...$additionalClaims,
        ], $expiry);
    }

    /**
     * Generate a refresh token for a user.
     */
    public function generateRefreshToken(int $userId, int $tenantId, ?bool $isMobile = null): string
    {
        $expiry = $this->getRefreshTokenExpiry($isMobile);

        return $this->createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ], $expiry);
    }

    /**
     * Validate a token and return its payload if valid.
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        $providedSignature = $this->base64UrlDecode($signatureEncoded);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

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
     * Validate a refresh token with revocation check.
     */
    public function validateRefreshToken(string $token): ?array
    {
        $payload = $this->validateToken($token);

        if (!$payload) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            return null;
        }

        if ($this->isTokenRevoked($token)) {
            return null;
        }

        return $payload;
    }

    /**
     * Check if a token is expired (without full validation).
     */
    public function isExpired(string $token): bool
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return true;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!$payload || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < time();
    }

    /**
     * Check if token needs refresh (less than 5 minutes remaining).
     */
    public function needsRefresh(string $token): bool
    {
        return $this->getTimeRemaining($token) < 300;
    }

    /**
     * Extract user ID from token without full validation.
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        return $payload['user_id'] ?? null;
    }

    /**
     * Revoke a refresh token by its jti claim.
     */
    public function revokeToken(string $refreshToken, int $userId): bool
    {
        $payload = $this->validateToken($refreshToken);

        if (!$payload) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'refresh') {
            return false;
        }

        if (($payload['user_id'] ?? 0) !== $userId) {
            return false;
        }

        $jti = $payload['jti'] ?? null;
        if (!$jti) {
            return false;
        }

        try {
            // Check if already revoked
            $existing = DB::selectOne("SELECT id FROM revoked_tokens WHERE jti = ?", [$jti]);
            if ($existing) {
                return true;
            }

            DB::insert(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))",
                [$userId, $jti, $payload['exp']]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('[TokenService] Failed to revoke token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke all refresh tokens for a user ("log out everywhere").
     */
    public function revokeAllTokensForUser(int $userId): int
    {
        try {
            $specialJti = 'revoke_all_' . $userId . '_' . time();
            $farFutureExpiry = time() + (10 * 365 * 24 * 60 * 60);

            DB::insert(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))",
                [$userId, $specialJti, $farFutureExpiry]
            );

            $globalJti = 'global_revoke_' . $userId;
            DB::insert(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))
                 ON DUPLICATE KEY UPDATE revoked_at = NOW()",
                [$userId, $globalJti, $farFutureExpiry]
            );

            $result = DB::selectOne("SELECT COUNT(*) as cnt FROM revoked_tokens WHERE user_id = ?", [$userId]);
            return (int) ($result->cnt ?? 0);
        } catch (\Exception $e) {
            Log::error('[TokenService] Failed to revoke all tokens: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate a short-lived, single-use impersonation token (5 min TTL).
     */
    public function generateImpersonationToken(int $userId, int $tenantId, int $adminId): string
    {
        return $this->createToken([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => 'impersonation',
            'impersonated_by' => $adminId,
            'jti' => bin2hex(random_bytes(16)),
        ], self::IMPERSONATION_TOKEN_EXPIRY);
    }

    /**
     * Validate and consume an impersonation token (single-use).
     */
    public function validateImpersonationToken(string $token): ?array
    {
        $payload = $this->validateToken($token);

        if (!$payload) {
            return null;
        }

        if (($payload['type'] ?? '') !== 'impersonation') {
            return null;
        }

        if (empty($payload['impersonated_by'])) {
            return null;
        }

        $jti = $payload['jti'] ?? null;
        if (!$jti) {
            return null;
        }

        try {
            $existing = DB::selectOne("SELECT id FROM revoked_tokens WHERE jti = ?", [$jti]);
            if ($existing) {
                return null;
            }

            DB::insert(
                "INSERT INTO revoked_tokens (user_id, jti, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))",
                [$payload['user_id'], $jti, $payload['exp']]
            );
        } catch (\Exception $e) {
            Log::error('[TokenService] Failed to consume impersonation token: ' . $e->getMessage());
            return null;
        }

        return $payload;
    }

    /**
     * Get remaining time until token expires.
     */
    public function getTimeRemaining(string $token): int
    {
        $exp = $this->getExpiration($token);

        if ($exp === null) {
            return -1;
        }

        return $exp - time();
    }

    /**
     * Get token expiration time.
     */
    public function getExpiration(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        return $payload['exp'] ?? null;
    }

    /**
     * Check if a refresh token has been revoked.
     */
    public function isTokenRevoked(string $refreshToken): bool
    {
        $payload = $this->validateToken($refreshToken);

        if (!$payload) {
            return true;
        }

        $jti = $payload['jti'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $iat = $payload['iat'] ?? 0;

        if (!$jti || !$userId) {
            return true;
        }

        try {
            $existing = DB::selectOne("SELECT id FROM revoked_tokens WHERE jti = ?", [$jti]);
            if ($existing) {
                return true;
            }

            $globalJti = 'global_revoke_' . $userId;
            $globalRevoke = DB::selectOne(
                "SELECT revoked_at FROM revoked_tokens WHERE jti = ? AND revoked_at > FROM_UNIXTIME(?)",
                [$globalJti, $iat]
            );
            if ($globalRevoke) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('[TokenService] Failed to check token revocation: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Clean up expired revocation records (for cron).
     */
    public function cleanupExpiredRevocations(): int
    {
        try {
            return DB::delete("DELETE FROM revoked_tokens WHERE expires_at < NOW() AND jti NOT LIKE 'global_revoke_%'");
        } catch (\Exception $e) {
            Log::error('[TokenService] Failed to cleanup revocations: ' . $e->getMessage());
            return 0;
        }
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Create a signed token with the given payload.
     */
    private function createToken(array $payload, int $expirySeconds): string
    {
        $header = [
            'alg' => self::ALGORITHM,
            'typ' => 'JWT',
        ];

        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $expirySeconds,
            'nbf' => $now,
        ]);

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Create HMAC signature.
     */
    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->getSecretKey(), true);
    }

    /**
     * Base64 URL-safe encoding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
