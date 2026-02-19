<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;

/**
 * FederationJwtService
 *
 * Handles JWT (JSON Web Token) generation and validation for federation API.
 * Implements RS256 (RSA with SHA-256) signing for secure cross-platform authentication.
 *
 * JWT Structure:
 * - iss (issuer): Platform ID of the sending platform
 * - sub (subject): User ID being authenticated
 * - aud (audience): Our platform identifier
 * - iat (issued at): Token creation timestamp
 * - exp (expiration): Token expiry timestamp
 * - tenant_id: Partner's tenant identifier
 * - scope: Array of permission strings
 */
class FederationJwtService
{
    // Token lifetime in seconds (1 hour default)
    private const DEFAULT_TOKEN_LIFETIME = 3600;

    // Maximum allowed token lifetime (24 hours)
    private const MAX_TOKEN_LIFETIME = 86400;

    // Supported algorithms
    private const SUPPORTED_ALGORITHMS = ['HS256', 'RS256'];

    /**
     * Generate a JWT token for a partner platform
     */
    public static function generateToken(
        string $platformId,
        string $userId,
        int $tenantId,
        array $scopes = [],
        int $lifetime = self::DEFAULT_TOKEN_LIFETIME
    ): ?array {
        $partner = self::getPartnerConfig($platformId);

        if (!$partner) {
            return null;
        }

        $lifetime = min($lifetime, self::MAX_TOKEN_LIFETIME);
        $issuedAt = time();
        $expiresAt = $issuedAt + $lifetime;

        $payload = [
            'iss' => $platformId,
            'sub' => $userId,
            'aud' => self::getOurPlatformId(),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'tenant_id' => $tenantId,
            'scope' => $scopes
        ];

        // Use HMAC-SHA256 with the partner's signing secret
        $secret = $partner['signing_secret'];
        $token = self::encode($payload, $secret, 'HS256');

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $lifetime,
            'expires_at' => date('c', $expiresAt),
            'scope' => implode(' ', $scopes)
        ];
    }

    /**
     * Validate and decode a JWT token
     */
    public static function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Decode header
        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!$header || !isset($header['alg'])) {
            return null;
        }

        // Verify algorithm is supported
        if (!in_array($header['alg'], self::SUPPORTED_ALGORITHMS)) {
            return null;
        }

        // Decode payload
        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) {
            return null;
        }

        // Check required claims
        if (!isset($payload['iss'], $payload['sub'], $payload['exp'])) {
            return null;
        }

        // Check expiration
        if ($payload['exp'] < time()) {
            return null;
        }

        // Get partner config to verify signature
        $partner = self::getPartnerConfig($payload['iss']);
        if (!$partner || empty($partner['signing_secret'])) {
            return null;
        }

        // Verify signature
        $signatureInput = $headerB64 . '.' . $payloadB64;
        $expectedSignature = self::sign($signatureInput, $partner['signing_secret'], $header['alg']);

        if (!hash_equals($expectedSignature, self::base64UrlDecode($signatureB64))) {
            return null;
        }

        // Add partner info to payload
        $payload['_partner'] = [
            'id' => $partner['id'],
            'tenant_id' => $partner['tenant_id'],
            'name' => $partner['name']
        ];

        return $payload;
    }

    /**
     * Encode a payload into a JWT
     */
    private static function encode(array $payload, string $secret, string $algorithm = 'HS256'): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
        ];

        $headerB64 = self::base64UrlEncode(json_encode($header));
        $payloadB64 = self::base64UrlEncode(json_encode($payload));

        $signatureInput = $headerB64 . '.' . $payloadB64;
        $signature = self::sign($signatureInput, $secret, $algorithm);
        $signatureB64 = self::base64UrlEncode($signature);

        return $headerB64 . '.' . $payloadB64 . '.' . $signatureB64;
    }

    /**
     * Sign data with the specified algorithm
     */
    private static function sign(string $data, string $secret, string $algorithm): string
    {
        switch ($algorithm) {
            case 'HS256':
                return hash_hmac('sha256', $data, $secret, true);

            case 'RS256':
                $privateKey = openssl_pkey_get_private($secret);
                if (!$privateKey) {
                    throw new \RuntimeException('Invalid private key for RS256');
                }
                openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
                return $signature;

            default:
                throw new \RuntimeException("Unsupported algorithm: {$algorithm}");
        }
    }

    /**
     * Base64 URL-safe encoding
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding
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
     * Get partner configuration by platform ID
     */
    private static function getPartnerConfig(string $platformId): ?array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT
                fak.id,
                fak.tenant_id,
                fak.name,
                fak.permissions,
                fak.signing_secret,
                fak.platform_id,
                fak.status
            FROM federation_api_keys fak
            WHERE fak.platform_id = ?
            AND fak.status = 'active'
            AND (fak.expires_at IS NULL OR fak.expires_at > NOW())
        ");
        $stmt->execute([$platformId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get our platform identifier
     */
    private static function getOurPlatformId(): string
    {
        return $_ENV['FEDERATION_PLATFORM_ID'] ?? 'nexus-timebank';
    }

    /**
     * Create a token endpoint response for OAuth-style token exchange
     */
    public static function handleTokenRequest(): array
    {
        $grantType = $_POST['grant_type'] ?? '';

        switch ($grantType) {
            case 'client_credentials':
                return self::handleClientCredentials();

            case 'refresh_token':
                return self::handleRefreshToken();

            default:
                return [
                    'error' => 'unsupported_grant_type',
                    'error_description' => 'Grant type not supported'
                ];
        }
    }

    /**
     * Handle client_credentials grant type
     */
    private static function handleClientCredentials(): array
    {
        $clientId = $_POST['client_id'] ?? $_SERVER['PHP_AUTH_USER'] ?? '';
        $clientSecret = $_POST['client_secret'] ?? $_SERVER['PHP_AUTH_PW'] ?? '';
        $scope = $_POST['scope'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'error' => 'invalid_client',
                'error_description' => 'Client credentials required'
            ];
        }

        // Validate client credentials (platform_id + signing_secret)
        $partner = self::getPartnerConfig($clientId);

        if (!$partner || !hash_equals($partner['signing_secret'], $clientSecret)) {
            return [
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials'
            ];
        }

        // Parse requested scopes
        $requestedScopes = $scope ? explode(' ', $scope) : [];
        $allowedScopes = json_decode($partner['permissions'] ?? '[]', true);

        // Filter to only allowed scopes
        $grantedScopes = array_intersect($requestedScopes, $allowedScopes);
        if (empty($grantedScopes) && !in_array('*', $allowedScopes)) {
            $grantedScopes = $allowedScopes;
        }

        // Generate token
        $tokenData = self::generateToken(
            $clientId,
            'client',
            $partner['tenant_id'],
            $grantedScopes
        );

        if (!$tokenData) {
            return [
                'error' => 'server_error',
                'error_description' => 'Failed to generate token'
            ];
        }

        return $tokenData;
    }

    /**
     * Handle refresh_token grant type (placeholder)
     */
    private static function handleRefreshToken(): array
    {
        // For now, refresh tokens are not implemented
        // Partners should request new tokens via client_credentials
        return [
            'error' => 'unsupported_grant_type',
            'error_description' => 'Refresh tokens not supported, use client_credentials'
        ];
    }
}
