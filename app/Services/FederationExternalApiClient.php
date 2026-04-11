<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FederationExternalApiClient — Outbound HTTP client for calling external
 * federation partner APIs.
 *
 * Supports three authentication methods (api_key, hmac, oauth2), circuit
 * breaker logic, retry with exponential backoff, and per-call audit logging.
 */
class FederationExternalApiClient
{
    /** Circuit breaker thresholds */
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_COOLDOWN_SECONDS = 300; // 5 minutes

    /** Timeouts (seconds) */
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 30;

    /** Retry configuration — keep delays short to avoid blocking PHP workers.
     *  Worst-case total wait: 200 + 400 + 800 = 1.4s (was 1000 + 2000 + 4000 = 7s). */
    private const MAX_RETRIES = 3;
    private const RETRY_BASE_MS = 200;

    // ----------------------------------------------------------------
    // Core HTTP methods
    // ----------------------------------------------------------------

    /**
     * Send a GET request to an external federation partner.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function get(int $partnerId, string $endpoint, array $params = []): array
    {
        return self::request($partnerId, 'GET', $endpoint, $params);
    }

    /**
     * Send a POST request to an external federation partner.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function post(int $partnerId, string $endpoint, array $data = []): array
    {
        return self::request($partnerId, 'POST', $endpoint, $data);
    }

    // ----------------------------------------------------------------
    // Partner-specific API calls
    // ----------------------------------------------------------------

    /**
     * Fetch members from an external partner.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function fetchMembers(int $partnerId, array $filters = []): array
    {
        return self::get($partnerId, '/members', $filters);
    }

    /**
     * Fetch listings from an external partner.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function fetchListings(int $partnerId, array $filters = []): array
    {
        return self::get($partnerId, '/listings', $filters);
    }

    /**
     * Fetch a single member from an external partner.
     *
     * @return array|null Null on failure
     */
    public static function fetchMember(int $partnerId, int $memberId): ?array
    {
        $result = self::get($partnerId, "/members/{$memberId}");

        return $result['success'] ? ($result['data'] ?? null) : null;
    }

    /**
     * Fetch a single listing from an external partner.
     *
     * @return array|null Null on failure
     */
    public static function fetchListing(int $partnerId, int $listingId): ?array
    {
        $result = self::get($partnerId, "/listings/{$listingId}");

        return $result['success'] ? ($result['data'] ?? null) : null;
    }

    /**
     * Send a cross-platform message via an external partner's API.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function sendMessage(int $partnerId, array $messageData): array
    {
        return self::post($partnerId, '/messages', $messageData);
    }

    /**
     * Create a time-credit transaction via an external partner's API.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function createTransaction(int $partnerId, array $transactionData): array
    {
        return self::post($partnerId, '/transactions', $transactionData);
    }

    /**
     * Health-check an external partner's API.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    public static function healthCheck(int $partnerId): array
    {
        return self::get($partnerId, '/health');
    }

    // ----------------------------------------------------------------
    // Internal: request dispatcher
    // ----------------------------------------------------------------

    /**
     * Execute an HTTP request with auth, retries, circuit breaker, and logging.
     *
     * @return array{success: bool, data?: array, error?: string, status_code?: int}
     */
    private static function request(int $partnerId, string $method, string $endpoint, array $data = []): array
    {
        $startTime = microtime(true);

        // ---- Load partner record ----
        $partner = self::getPartner($partnerId);
        if (!$partner) {
            return ['success' => false, 'error' => "Partner #{$partnerId} not found or inactive", 'status_code' => 0];
        }

        // ---- Circuit breaker check ----
        if (self::isCircuitOpen($partnerId)) {
            self::logApiCall($partnerId, $endpoint, $method, 0, false, 0, 'Circuit breaker open — partner temporarily unavailable');
            return ['success' => false, 'error' => 'Circuit breaker open — partner temporarily unavailable', 'status_code' => 0];
        }

        // ---- Build URL ----
        $baseUrl = rtrim($partner['base_url'], '/');
        $apiPath = rtrim($partner['api_path'] ?? '/api/v1/federation', '/');
        $url = $baseUrl . $apiPath . '/' . ltrim($endpoint, '/');

        // ---- Build body (for signing & sending) ----
        $body = ($method === 'GET') ? '' : json_encode($data);

        // ---- Auth headers ----
        try {
            $headers = self::buildAuthHeaders($partner, $method, $url, $body);
        } catch (\Exception $e) {
            $elapsed = (microtime(true) - $startTime) * 1000;
            self::logApiCall($partnerId, $endpoint, $method, 0, false, $elapsed, 'Auth error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication setup failed: ' . $e->getMessage(), 'status_code' => 0];
        }

        // ---- Execute with retries (only retry on 5xx) ----
        $lastResponse = null;
        $lastException = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            if ($attempt > 0) {
                // Exponential backoff: 1s, 2s, 4s
                $delayMs = self::RETRY_BASE_MS * (2 ** ($attempt - 1));
                usleep($delayMs * 1000);
            }

            try {
                $pending = Http::withHeaders($headers)
                    ->connectTimeout(self::CONNECT_TIMEOUT)
                    ->timeout(self::REQUEST_TIMEOUT)
                    ->acceptJson();

                if ($method === 'GET') {
                    $lastResponse = $pending->get($url, $data);
                } else {
                    $lastResponse = $pending->withBody($body, 'application/json')->post($url);
                }

                // Success or client error (4xx) — don't retry
                if ($lastResponse->successful() || ($lastResponse->status() >= 400 && $lastResponse->status() < 500)) {
                    break;
                }

                // 5xx — retry unless last attempt
                $lastException = null;
            } catch (\Exception $e) {
                $lastException = $e;
                $lastResponse = null;
            }
        }

        // ---- Process result ----
        $elapsed = (microtime(true) - $startTime) * 1000;

        if ($lastException) {
            self::recordFailure($partnerId);
            $errorMsg = 'Request failed: ' . $lastException->getMessage();
            self::logApiCall($partnerId, $endpoint, $method, 0, false, $elapsed, $errorMsg, $body ?: null);
            return ['success' => false, 'error' => $errorMsg, 'status_code' => 0];
        }

        $statusCode = $lastResponse->status();
        $rawResponseBody = $lastResponse->body();

        if ($lastResponse->successful()) {
            self::recordSuccess($partnerId);
            $responseData = $lastResponse->json() ?? [];
            // Unwrap v1 API response envelope: {success: true, data: [...actual data...]}
            // The v1 endpoints wrap everything in {success, data, pagination}. We need the
            // inner 'data' array, not the entire envelope, so callers get actual records.
            $innerData = (is_array($responseData) && array_key_exists('data', $responseData))
                ? $responseData['data']
                : $responseData;
            self::logApiCall($partnerId, $endpoint, $method, $statusCode, true, $elapsed, null, $body ?: null, $rawResponseBody);
            return ['success' => true, 'data' => $innerData, 'status_code' => $statusCode];
        }

        // Non-success HTTP status
        self::recordFailure($partnerId);
        $errorBody = $lastResponse->json('message') ?? $lastResponse->body();
        $errorMsg = "HTTP {$statusCode}: " . (is_string($errorBody) ? substr($errorBody, 0, 500) : json_encode($errorBody));
        self::logApiCall($partnerId, $endpoint, $method, $statusCode, false, $elapsed, $errorMsg, $body ?: null, $rawResponseBody);

        return ['success' => false, 'error' => $errorMsg, 'status_code' => $statusCode];
    }

    // ----------------------------------------------------------------
    // Auth helpers
    // ----------------------------------------------------------------

    /**
     * Build authentication headers based on the partner's auth_method.
     *
     * @throws \RuntimeException When credentials are missing or decryption fails
     */
    private static function buildAuthHeaders(array $partner, string $method, string $url, string $body = ''): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'ProjectNexus-Federation/1.0',
        ];

        $authMethod = $partner['auth_method'] ?? 'api_key';

        switch ($authMethod) {
            case 'api_key':
                if (empty($partner['api_key'])) {
                    throw new \RuntimeException('API key not configured for partner');
                }
                $decryptedKey = self::decryptCredential($partner['api_key']);
                $headers['Authorization'] = 'Bearer ' . $decryptedKey;
                break;

            case 'hmac':
                if (empty($partner['signing_secret'])) {
                    throw new \RuntimeException('HMAC signing secret not configured for partner');
                }
                $decryptedSecret = self::decryptCredential($partner['signing_secret']);
                $timestamp = (string) time();
                $nonce = bin2hex(random_bytes(16));
                $signature = self::generateHmacSignature($decryptedSecret, $method, $url, $timestamp, $body, $nonce);

                $headers['X-Federation-Signature'] = $signature;
                $headers['X-Federation-Timestamp'] = $timestamp;
                $headers['X-Federation-Nonce'] = $nonce;
                break;

            case 'oauth2':
                $token = self::getOAuth2Token($partner);
                $headers['Authorization'] = 'Bearer ' . $token;
                break;

            default:
                throw new \RuntimeException("Unsupported auth method: {$authMethod}");
        }

        return $headers;
    }

    /**
     * Obtain an OAuth2 access token via the client credentials grant.
     *
     * Tokens are cached for their lifetime minus a 60-second buffer.
     * Falls back to a pre-stored token in api_key if no client credentials are configured.
     */
    private static function getOAuth2Token(array $partner): string
    {
        $partnerId = (int) $partner['id'];
        $cacheKey = "federation_oauth2_token:{$partnerId}";

        // Return cached token if available
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // If client credentials are configured, use the client credentials grant
        if (!empty($partner['oauth_client_id']) && !empty($partner['oauth_client_secret']) && !empty($partner['oauth_token_url'])) {
            $clientId = $partner['oauth_client_id'];
            $clientSecret = self::decryptCredential($partner['oauth_client_secret']);
            $tokenUrl = $partner['oauth_token_url'];

            $response = Http::timeout(10)->asForm()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (!$response->successful()) {
                Log::error('FederationExternalApiClient: OAuth2 token request failed', [
                    'partner_id' => $partnerId,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw new \RuntimeException('OAuth2 token request failed: HTTP ' . $response->status());
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            if (!$accessToken) {
                throw new \RuntimeException('OAuth2 token response missing access_token');
            }

            // Cache for token lifetime minus 60s buffer (default 1 hour)
            $expiresIn = max(60, ($data['expires_in'] ?? 3600) - 60);
            Cache::put($cacheKey, $accessToken, $expiresIn);

            return $accessToken;
        }

        // Fallback: use pre-stored token in api_key column
        if (!empty($partner['api_key'])) {
            return self::decryptCredential($partner['api_key']);
        }

        throw new \RuntimeException('OAuth2 credentials not configured for partner');
    }

    /**
     * Generate an HMAC-SHA256 signature matching the inbound middleware's format.
     *
     * String to sign: METHOD\nPATH\nTIMESTAMP\nNONCE\nBODY
     *
     * The nonce is included in the signed payload so that an attacker cannot
     * replay a captured request with a different nonce within the 5-minute
     * timestamp window.
     */
    private static function generateHmacSignature(string $secret, string $method, string $url, string $timestamp, string $body, string $nonce): string
    {
        // Use path + query string (not full URL) to match the inbound middleware's
        // verifyHmacSignature() which uses $_SERVER['REQUEST_URI'] (path only).
        $path = parse_url($url, PHP_URL_PATH) ?? '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $pathWithQuery = $query ? $path . '?' . $query : $path;

        $stringToSign = implode("\n", [
            strtoupper($method),
            $pathWithQuery,
            $timestamp,
            $nonce,
            $body,
        ]);

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * Decrypt an encrypted credential stored in the database.
     *
     * Falls back to the raw value if decryption fails (for unencrypted legacy values).
     */
    private static function decryptCredential(string $encryptedValue): string
    {
        return Crypt::decryptString($encryptedValue);
    }

    // ----------------------------------------------------------------
    // Circuit breaker
    // ----------------------------------------------------------------

    /**
     * Check whether the circuit breaker is open for a partner.
     */
    private static function isCircuitOpen(int $partnerId): bool
    {
        return Cache::has("federation_cb_open:{$partnerId}");
    }

    /**
     * Record a successful call — reset failure counter.
     *
     * Defense-in-depth: tenant_id is included in the WHERE clause even though
     * getPartner() already scopes by tenant. This prevents a compromised or
     * misrouted partner ID from modifying another tenant's partner record.
     */
    private static function recordSuccess(int $partnerId): void
    {
        Cache::forget("federation_cb_failures:{$partnerId}");
        Cache::forget("federation_cb_open:{$partnerId}");

        $tenantId = \App\Core\TenantContext::getId();

        // Restore partner to 'active' if it was in 'failed' state so that
        // a recovered partner can self-heal after the circuit-breaker cooldown
        // expires. Do NOT auto-promote 'pending' partners — they must be
        // explicitly approved by an admin via the External Partners UI.
        $query = DB::table('federation_external_partners')
            ->where('id', $partnerId)
            ->where('status', 'failed');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $query->update([
            'status' => 'active',
            'error_count' => 0,
            'last_error' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Record a failed call — increment counter and potentially trip the circuit breaker.
     *
     * Defense-in-depth: tenant_id is included in the WHERE clause even though
     * getPartner() already scopes by tenant.
     */
    private static function recordFailure(int $partnerId): void
    {
        // Fix #8: Use Cache::increment() (atomic Redis INCR) instead of the
        // non-atomic get()+put() pattern. Under concurrent failures, get+put
        // can under-count (two requests both read 0, both write 1) causing
        // the breaker to miss the threshold.
        $cacheKey = "federation_cb_failures:{$partnerId}";
        $failures = Cache::increment($cacheKey);

        // On first increment the key has no TTL — set it.
        // Subsequent increments on an existing key preserve the TTL.
        if ($failures === 1) {
            Cache::put($cacheKey, $failures, self::CIRCUIT_BREAKER_COOLDOWN_SECONDS * 2);
        }

        $tenantId = \App\Core\TenantContext::getId();

        if ($failures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            // Trip the circuit breaker
            Cache::put("federation_cb_open:{$partnerId}", true, self::CIRCUIT_BREAKER_COOLDOWN_SECONDS);

            // Mark partner as failed in DB
            $query = DB::table('federation_external_partners')
                ->where('id', $partnerId);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $query->update([
                'status' => 'failed',
                'error_count' => $failures,
                'last_error' => 'Circuit breaker tripped after ' . $failures . ' consecutive failures',
                'updated_at' => now(),
            ]);

            Log::warning('FederationExternalApiClient: circuit breaker tripped', [
                'partner_id' => $partnerId,
                'failures' => $failures,
            ]);
        } else {
            // Update error count in DB
            $query = DB::table('federation_external_partners')
                ->where('id', $partnerId);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $query->update([
                'error_count' => $failures,
                'updated_at' => now(),
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Partner lookup
    // ----------------------------------------------------------------

    /**
     * Load an external partner record by ID.
     *
     * 'failed' partners are intentionally included here so that after the
     * circuit-breaker cache key expires (CIRCUIT_BREAKER_COOLDOWN_SECONDS)
     * the next outbound call can attempt recovery.  isCircuitOpen() is the
     * sole gate while the cooldown is active; once the cache key is gone,
     * the call is attempted and recordSuccess() will restore the DB status
     * to 'active' if the partner responds successfully.
     *
     * Partners that are explicitly 'disabled' or 'deleted' are still excluded.
     */
    private static function getPartner(int $partnerId, ?int $tenantId = null): ?array
    {
        // Always scope by tenant — use explicit ID or fall back to current context
        $tenantId = $tenantId ?? \App\Core\TenantContext::getId();

        $query = DB::table('federation_external_partners')
            ->where('id', $partnerId)
            ->whereIn('status', ['active', 'pending', 'failed']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $row = $query->first();

        if (!$row) {
            return null;
        }

        return (array) $row;
    }

    // ----------------------------------------------------------------
    // Logging
    // ----------------------------------------------------------------

    /**
     * Log an outbound API call to federation_external_partner_logs.
     *
     * Sensitive fields (message bodies, credentials, tokens) are redacted
     * before storage to prevent data leakage via admin log views.
     */
    private static function logApiCall(
        int $partnerId,
        string $endpoint,
        string $method,
        int $statusCode,
        bool $success,
        float $responseTime,
        ?string $errorMessage = null,
        ?string $requestBody = null,
        ?string $responseBody = null
    ): void {
        try {
            DB::table('federation_external_partner_logs')->insert([
                'partner_id' => $partnerId,
                'endpoint' => substr($endpoint, 0, 500),
                'method' => strtoupper($method),
                'request_body' => $requestBody ? substr(self::redactSensitiveFields($requestBody), 0, 10000) : null,
                'response_code' => $statusCode ?: null,
                'response_body' => $responseBody ? substr(self::redactSensitiveFields($responseBody), 0, 10000) : null,
                'response_time_ms' => (int) round($responseTime),
                'success' => $success ? 1 : 0,
                'error_message' => $errorMessage ? substr($errorMessage, 0, 65535) : null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('FederationExternalApiClient::logApiCall insert failed', [
                'partner_id' => $partnerId,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Redact sensitive fields from JSON strings before logging.
     */
    private static function redactSensitiveFields(string $json): string
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }

        $sensitiveKeys = ['body', 'message_body', 'content', 'api_key', 'signing_secret',
            'oauth_client_secret', 'token', 'access_token', 'refresh_token', 'password', 'secret'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys, true) && is_string($value) && strlen($value) > 0) {
                $value = '[REDACTED]';
            }
        });

        return json_encode($data, JSON_UNESCAPED_SLASHES) ?: $json;
    }
}
