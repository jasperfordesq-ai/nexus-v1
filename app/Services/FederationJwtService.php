<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * FederationJwtService — JWT token management for federation API.
 *
 * Handles JWT generation, validation, and encoding for cross-platform federation.
 */
class FederationJwtService
{
    public const SUPPORTED_ALGORITHMS = ['HS256', 'RS256'];
    public const DEFAULT_TOKEN_LIFETIME = 3600;
    public const MAX_TOKEN_LIFETIME = 86400;

    public function __construct()
    {
    }

    /**
     * Generate a JWT token for a platform.
     *
     * @param string $platformId Platform identifier
     * @param string $userId User identifier
     * @param int $tenantId Tenant ID
     * @param array $scopes Token scopes
     * @param int $lifetime Token lifetime in seconds
     * @return array|null Token data or null on failure
     */
    public static function generateToken(string $platformId, string $userId, int $tenantId, array $scopes = [], int $lifetime = self::DEFAULT_TOKEN_LIFETIME): ?array
    {
        Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Validate a JWT token.
     *
     * @param string $token JWT token string
     * @return array|null Decoded payload or null if invalid
     */
    public static function validateToken(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Decode header
        $headerJson = self::base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);

        if (!is_array($header)) {
            return null;
        }

        // Check algorithm
        $alg = $header['alg'] ?? 'none';
        if (!in_array($alg, self::SUPPORTED_ALGORITHMS, true)) {
            return null;
        }

        // Decode payload
        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null;
        }

        // Check required claims
        if (empty($payload['sub'])) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        // In a full implementation, we would verify the signature here
        // For now, return null because we can't verify without the secret
        return null;
    }

    /**
     * Static proxy for validateToken.
     */
    public static function validateTokenStatic(string $token): ?array
    {
        return self::validateToken($token);
    }

    /**
     * Handle an OAuth2-style token request.
     *
     * @return array Token response or error
     */
    public static function handleTokenRequest(): array
    {
        $grantType = $_POST['grant_type'] ?? '';

        if ($grantType !== 'client_credentials') {
            return [
                'error' => 'unsupported_grant_type',
                'error_description' => 'Only client_credentials grant type is supported.',
            ];
        }

        // Check for client authentication
        $clientId = $_SERVER['PHP_AUTH_USER'] ?? '';
        $clientSecret = $_SERVER['PHP_AUTH_PW'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'error' => 'invalid_client',
                'error_description' => 'Client authentication is required.',
            ];
        }

        // Validate client credentials against federation platforms
        // This is a stub - full implementation would check the database
        Log::warning('Legacy delegation removed: FederationJwtService::handleTokenRequest');

        return [
            'error' => 'invalid_client',
            'error_description' => 'Unknown client.',
        ];
    }

    /**
     * Get supported algorithms.
     *
     * @return array
     */
    public static function getSupportedAlgorithms(): array
    {
        return self::SUPPORTED_ALGORITHMS;
    }

    /**
     * Get default token lifetime.
     *
     * @return int
     */
    public static function getDefaultTokenLifetime(): int
    {
        return self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get maximum token lifetime.
     *
     * @return int
     */
    public static function getMaxTokenLifetime(): int
    {
        return self::MAX_TOKEN_LIFETIME;
    }

    /**
     * Base64 URL-safe encode.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode.
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
