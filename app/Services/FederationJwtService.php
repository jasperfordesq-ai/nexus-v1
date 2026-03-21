<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationJwtService — JWT token management for federation API authentication.
 *
 * Handles JWT generation, validation, and OAuth2 client_credentials flow
 * for cross-platform federation using HMAC-SHA256 signing.
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
        $secret = self::getSigningSecret();
        if (!$secret) {
            Log::error('[FederationJwt] No signing secret configured');
            return null;
        }

        $lifetime = min(max($lifetime, 60), self::MAX_TOKEN_LIFETIME);

        $now = time();
        $payload = [
            'iss' => config('app.url', 'project-nexus'),
            'sub' => $userId,
            'aud' => $platformId,
            'iat' => $now,
            'exp' => $now + $lifetime,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16)),
            'tenant_id' => $tenantId,
            'scopes' => $scopes,
        ];

        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $headerB64 = self::base64UrlEncode(json_encode($header));
        $payloadB64 = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        $signatureB64 = self::base64UrlEncode($signature);

        $token = "{$headerB64}.{$payloadB64}.{$signatureB64}";

        Log::info('[FederationJwt] Token generated', [
            'platform' => $platformId,
            'user' => $userId,
            'tenant' => $tenantId,
            'expires_in' => $lifetime,
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $lifetime,
            'scope' => implode(' ', $scopes),
        ];
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

        // Check not-before
        if (isset($payload['nbf']) && (int) $payload['nbf'] > time()) {
            return null;
        }

        // Verify HMAC signature
        $secret = self::getSigningSecret();
        if (!$secret) {
            Log::error('[FederationJwt] Cannot verify token: no signing secret');
            return null;
        }

        if ($alg === 'HS256') {
            $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
            $expectedSignatureB64 = self::base64UrlEncode($expectedSignature);

            if (!hash_equals($expectedSignatureB64, $signatureB64)) {
                Log::warning('[FederationJwt] Token signature mismatch');
                return null;
            }
        } else {
            // RS256 not implemented yet — reject
            Log::warning('[FederationJwt] RS256 validation not implemented');
            return null;
        }

        return $payload;
    }

    /**
     * Static proxy for validateToken.
     */
    public static function validateTokenStatic(string $token): ?array
    {
        return self::validateToken($token);
    }

    /**
     * Handle an OAuth2-style client_credentials token request.
     *
     * @return array Token response or error
     */
    public static function handleTokenRequest(): array
    {
        $request = request();
        $grantType = $request->input('grant_type', $request->request->get('grant_type', ''));

        if ($grantType !== 'client_credentials') {
            return [
                'error' => 'unsupported_grant_type',
                'error_description' => 'Only client_credentials grant type is supported.',
            ];
        }

        // Check for client authentication (Basic Auth or POST body)
        $clientId = $request->getUser() ?: $request->input('client_id', '');
        $clientSecret = $request->getPassword() ?: $request->input('client_secret', '');

        if (empty($clientId) || empty($clientSecret)) {
            return [
                'error' => 'invalid_client',
                'error_description' => 'Client authentication is required.',
            ];
        }

        try {
            // Validate client credentials against federation API keys
            $apiKey = DB::selectOne(
                "SELECT fak.id, fak.tenant_id, fak.name, fak.permissions, fak.status, fak.expires_at
                 FROM federation_api_keys fak
                 WHERE fak.key_prefix = ? AND fak.key_hash = ? AND fak.status = 'active'",
                [substr($clientId, 0, 8), hash('sha256', $clientSecret)]
            );

            if (!$apiKey) {
                return [
                    'error' => 'invalid_client',
                    'error_description' => 'Unknown client or invalid credentials.',
                ];
            }

            // Check expiry
            if ($apiKey->expires_at && strtotime($apiKey->expires_at) < time()) {
                return [
                    'error' => 'invalid_client',
                    'error_description' => 'API key has expired.',
                ];
            }

            // Parse scopes
            $scopes = [];
            if (!empty($apiKey->permissions)) {
                $scopes = json_decode($apiKey->permissions, true) ?: [];
            }

            $requestedScope = $request->input('scope', '');
            if (!empty($requestedScope)) {
                $requestedScopes = explode(' ', $requestedScope);
                $scopes = array_intersect($requestedScopes, $scopes) ?: $scopes;
            }

            // Generate token
            $tokenData = self::generateToken(
                $clientId,
                'api_key_' . $apiKey->id,
                (int) $apiKey->tenant_id,
                $scopes
            );

            if (!$tokenData) {
                return [
                    'error' => 'server_error',
                    'error_description' => 'Failed to generate token.',
                ];
            }

            // Update last_used_at
            DB::update(
                "UPDATE federation_api_keys SET last_used_at = NOW() WHERE id = ?",
                [$apiKey->id]
            );

            return $tokenData;
        } catch (\Exception $e) {
            Log::error('[FederationJwt] handleTokenRequest failed', ['error' => $e->getMessage()]);
            return [
                'error' => 'server_error',
                'error_description' => 'Internal server error.',
            ];
        }
    }

    /**
     * Get supported algorithms.
     */
    public static function getSupportedAlgorithms(): array
    {
        return self::SUPPORTED_ALGORITHMS;
    }

    /**
     * Get default token lifetime.
     */
    public static function getDefaultTokenLifetime(): int
    {
        return self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get maximum token lifetime.
     */
    public static function getMaxTokenLifetime(): int
    {
        return self::MAX_TOKEN_LIFETIME;
    }

    /**
     * Get the signing secret from configuration.
     */
    private static function getSigningSecret(): ?string
    {
        // Use a dedicated federation secret, falling back to APP_KEY
        $secret = config('federation.jwt_secret', config('app.key'));
        if (empty($secret)) {
            return null;
        }
        // Laravel APP_KEY is prefixed with "base64:" — decode it
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }
        return $secret;
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
