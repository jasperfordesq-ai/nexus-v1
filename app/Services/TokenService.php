<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Nexus\Services\TokenService as LegacyTokenService;

/**
 * TokenService — Laravel DI wrapper for legacy \Nexus\Services\TokenService.
 *
 * Delegates to the legacy static JWT-based token service which handles
 * HMAC signing, expiry, revocation, and impersonation tokens.
 *
 * Controllers currently call LegacyTokenService directly; this wrapper
 * exists so they can be migrated to DI incrementally.
 */
class TokenService
{
    /**
     * Check if the current request is from a mobile app (Capacitor/native).
     */
    public function isMobileRequest(): bool
    {
        return LegacyTokenService::isMobileRequest();
    }

    /**
     * Generate an access token for a user.
     *
     * @param  array  $additionalClaims  Optional additional JWT claims
     * @param  bool|null  $isMobile  Force mobile/web mode (null = auto-detect)
     */
    public function generateToken(int $userId, int $tenantId, array $additionalClaims = [], ?bool $isMobile = null): string
    {
        return LegacyTokenService::generateToken($userId, $tenantId, $additionalClaims, $isMobile);
    }

    /**
     * Generate a refresh token for a user.
     *
     * @param  bool|null  $isMobile  Force mobile/web mode (null = auto-detect)
     */
    public function generateRefreshToken(int $userId, int $tenantId, ?bool $isMobile = null): string
    {
        return LegacyTokenService::generateRefreshToken($userId, $tenantId, $isMobile);
    }

    /**
     * Validate a token and return its payload if valid.
     *
     * @return array|null  The full payload array if valid, null otherwise
     */
    public function validateToken(string $token): ?array
    {
        return LegacyTokenService::validateToken($token);
    }

    /**
     * Validate a refresh token with revocation check.
     *
     * @return array|null  The payload if valid and not revoked, null otherwise
     */
    public function validateRefreshToken(string $token): ?array
    {
        return LegacyTokenService::validateRefreshToken($token);
    }

    /**
     * Get the appropriate access token expiry (seconds) based on platform.
     */
    public function getAccessTokenExpiry(?bool $isMobile = null): int
    {
        return LegacyTokenService::getAccessTokenExpiry($isMobile);
    }

    /**
     * Get the appropriate refresh token expiry (seconds) based on platform.
     */
    public function getRefreshTokenExpiry(?bool $isMobile = null): int
    {
        return LegacyTokenService::getRefreshTokenExpiry($isMobile);
    }

    /**
     * Check if a token is expired (without full validation).
     */
    public function isExpired(string $token): bool
    {
        return LegacyTokenService::isExpired($token);
    }

    /**
     * Check if token needs refresh (less than 5 minutes remaining).
     */
    public function needsRefresh(string $token): bool
    {
        return LegacyTokenService::needsRefresh($token);
    }

    /**
     * Extract user ID from token without full validation.
     */
    public function getUserIdFromToken(string $token): ?int
    {
        return LegacyTokenService::getUserIdFromToken($token);
    }

    /**
     * Revoke a refresh token by its jti claim.
     */
    public function revokeToken(string $refreshToken, int $userId): bool
    {
        return LegacyTokenService::revokeToken($refreshToken, $userId);
    }

    /**
     * Revoke all refresh tokens for a user ("log out everywhere").
     *
     * @return int  Estimated number of invalidated tokens
     */
    public function revokeAllTokensForUser(int $userId): int
    {
        return LegacyTokenService::revokeAllTokensForUser($userId);
    }

    /**
     * Generate a short-lived, single-use impersonation token (5 min TTL).
     */
    public function generateImpersonationToken(int $userId, int $tenantId, int $adminId): string
    {
        return LegacyTokenService::generateImpersonationToken($userId, $tenantId, $adminId);
    }

    /**
     * Validate and consume an impersonation token (single-use).
     *
     * @return array|null  The payload if valid and not yet consumed, null otherwise
     */
    public function validateImpersonationToken(string $token): ?array
    {
        return LegacyTokenService::validateImpersonationToken($token);
    }

    /**
     * Get remaining time until token expires.
     *
     * @return int  Seconds remaining (negative if expired)
     */
    public function getTimeRemaining(string $token): int
    {
        return LegacyTokenService::getTimeRemaining($token);
    }

    /**
     * Get token expiration time.
     *
     * @return int|null  Unix timestamp or null if invalid
     */
    public function getExpiration(string $token): ?int
    {
        return LegacyTokenService::getExpiration($token);
    }

    /**
     * Check if a refresh token has been revoked.
     */
    public function isTokenRevoked(string $refreshToken): bool
    {
        return LegacyTokenService::isTokenRevoked($refreshToken);
    }

    /**
     * Clean up expired revocation records (for cron).
     *
     * @return int  Number of records deleted
     */
    public function cleanupExpiredRevocations(): int
    {
        return LegacyTokenService::cleanupExpiredRevocations();
    }
}
