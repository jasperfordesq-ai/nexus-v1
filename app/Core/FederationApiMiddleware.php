<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

use Illuminate\Support\Facades\DB;
use App\Services\FederationJwtService;

/**
 * Federation API authentication and authorization middleware.
 *
 * Supports:
 * - API Key: Simple Bearer token (internal/trusted partners)
 * - HMAC-SHA256: Request signing for external platform integrations
 * - JWT: Token-based authentication for user-level access
 */
class FederationApiMiddleware
{
    private static ?array $authenticatedPartner = null;
    private static ?string $authMethod = null;

    // Timestamp tolerance for replay attack prevention (5 minutes)
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * Authenticate an incoming API request.
     */
    public static function authenticate(): bool
    {
        if (self::hasHmacSignature()) {
            return self::authenticateWithHmac();
        }

        if (self::hasJwtToken()) {
            return self::authenticateWithJwt();
        }

        return self::authenticateWithApiKey();
    }

    /**
     * Check if request has a JWT Bearer token.
     */
    private static function hasJwtToken(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return (bool) preg_match('/^Bearer\s+([A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+)$/i', $authHeader);
    }

    /**
     * Authenticate using JWT Bearer token.
     */
    private static function authenticateWithJwt(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            self::sendError(401, 'Invalid authorization header', 'INVALID_AUTH_HEADER');
            return false;
        }

        $token = $matches[1];
        if (!class_exists(FederationJwtService::class)) {
            self::sendError(500, 'JWT service unavailable', 'SERVICE_UNAVAILABLE');
            return false;
        }
        $payload = FederationJwtService::validateTokenStatic($token);

        if (!$payload) {
            self::sendError(401, 'Invalid or expired token', 'INVALID_TOKEN');
            return false;
        }

        $partnerId = $payload['_partner']['id'] ?? null;

        if (!$partnerId) {
            self::sendError(401, 'Token missing partner information', 'INVALID_TOKEN');
            return false;
        }

        $partner = [
            'id' => $partnerId,
            'tenant_id' => $payload['_partner']['tenant_id'],
            'name' => $payload['_partner']['name'],
            'permissions' => json_encode($payload['scope'] ?? []),
            'status' => 'active',
            'jwt_subject' => $payload['sub'],
            'jwt_issuer' => $payload['iss']
        ];

        if (!self::checkRateLimit($partnerId)) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        self::$authenticatedPartner = $partner;
        self::$authMethod = 'jwt';

        self::logApiAccess($partnerId, $_SERVER['REQUEST_URI'], 'jwt', null);

        return true;
    }

    /**
     * Check if request has HMAC signature headers.
     */
    private static function hasHmacSignature(): bool
    {
        return !empty($_SERVER['HTTP_X_FEDERATION_SIGNATURE'])
            && !empty($_SERVER['HTTP_X_FEDERATION_TIMESTAMP'])
            && !empty($_SERVER['HTTP_X_FEDERATION_PLATFORM_ID']);
    }

    /**
     * Authenticate using HMAC-SHA256 request signing.
     */
    private static function authenticateWithHmac(): bool
    {
        $platformId = $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] ?? '';
        $timestamp = $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] ?? '';

        if (!self::validateTimestamp($timestamp)) {
            self::sendError(401, 'Request timestamp expired or invalid', 'TIMESTAMP_INVALID');
            return false;
        }

        $partner = self::getPartnerByPlatformId($platformId);

        if (!$partner) {
            self::sendError(401, 'Unknown platform', 'PLATFORM_NOT_FOUND');
            return false;
        }

        if ($partner['status'] !== 'active') {
            self::sendError(403, 'Partner account is not active', 'PARTNER_INACTIVE');
            return false;
        }

        if (empty($partner['signing_secret'])) {
            self::sendError(401, 'HMAC signing not configured for this platform', 'SIGNING_NOT_CONFIGURED');
            return false;
        }

        if (!self::verifyHmacSignature($signature, $partner['signing_secret'], $timestamp)) {
            self::sendError(401, 'Invalid request signature', 'SIGNATURE_INVALID');
            return false;
        }

        if (!self::checkRateLimit($partner['id'])) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        self::$authenticatedPartner = $partner;
        self::$authMethod = 'hmac';

        self::logApiAccess($partner['id'], $_SERVER['REQUEST_URI'], 'hmac', true);

        return true;
    }

    /**
     * Authenticate using simple API key.
     */
    private static function authenticateWithApiKey(): bool
    {
        $apiKey = self::extractApiKey();

        if (empty($apiKey)) {
            self::sendError(401, 'API key required', 'MISSING_API_KEY');
            return false;
        }

        $partner = self::validateApiKey($apiKey);

        if (!$partner) {
            self::sendError(401, 'Invalid API key', 'INVALID_API_KEY');
            return false;
        }

        if ($partner['status'] !== 'active') {
            self::sendError(403, 'Partner account is not active', 'PARTNER_INACTIVE');
            return false;
        }

        if (!empty($partner['signing_enabled'])) {
            self::sendError(401, 'HMAC signing required for this API key', 'HMAC_REQUIRED');
            return false;
        }

        if (!self::checkRateLimit($partner['id'])) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        self::$authenticatedPartner = $partner;
        self::$authMethod = 'api_key';

        self::logApiAccess($partner['id'], $_SERVER['REQUEST_URI'], 'api_key', null);

        return true;
    }

    /**
     * Validate request timestamp for replay attack prevention.
     */
    private static function validateTimestamp(string $timestamp): bool
    {
        $requestTime = strtotime($timestamp);

        if ($requestTime === false) {
            if (is_numeric($timestamp)) {
                $requestTime = (int)$timestamp;
            } else {
                return false;
            }
        }

        $currentTime = time();
        $diff = abs($currentTime - $requestTime);

        return $diff <= self::TIMESTAMP_TOLERANCE;
    }

    /**
     * Verify HMAC-SHA256 signature.
     */
    private static function verifyHmacSignature(string $signature, string $secret, string $timestamp): bool
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        $body = file_get_contents('php://input') ?: '';

        $stringToSign = implode("\n", [$method, $path, $timestamp, $body]);
        $expectedSignature = hash_hmac('sha256', $stringToSign, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get partner by platform ID.
     */
    private static function getPartnerByPlatformId(string $platformId): ?array
    {
        $results = DB::select("
            SELECT
                fak.id,
                fak.tenant_id,
                fak.name,
                fak.permissions,
                fak.rate_limit,
                fak.status,
                fak.signing_secret,
                fak.signing_enabled,
                fak.platform_id,
                fak.last_used_at,
                t.name as tenant_name,
                t.domain as tenant_domain
            FROM federation_api_keys fak
            JOIN tenants t ON t.id = fak.tenant_id
            WHERE fak.platform_id = ?
            AND fak.status = 'active'
            AND (fak.expires_at IS NULL OR fak.expires_at > NOW())
        ", [$platformId]);

        $row = $results[0] ?? null;

        if ($row) {
            // Convert stdClass to array
            $partner = (array) $row;

            // Update last used timestamp
            DB::update("
                UPDATE federation_api_keys
                SET last_used_at = NOW(), request_count = request_count + 1
                WHERE id = ?
            ", [$partner['id']]);

            return $partner;
        }

        return null;
    }

    /**
     * Get the authentication method used.
     */
    public static function getAuthMethod(): ?string
    {
        return self::$authMethod;
    }

    /**
     * Generate a new signing secret.
     */
    public static function generateSigningSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a signature for a request (for testing/client libraries).
     */
    public static function generateSignature(string $secret, string $method, string $path, string $timestamp, string $body = ''): string
    {
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $body
        ]);

        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * Get the authenticated partner info.
     */
    public static function getPartner(): ?array
    {
        return self::$authenticatedPartner;
    }

    /**
     * Get the partner's tenant ID.
     */
    public static function getPartnerTenantId(): ?int
    {
        return self::$authenticatedPartner['tenant_id'] ?? null;
    }

    /**
     * Check if authenticated partner has permission for a feature.
     */
    public static function hasPermission(string $feature): bool
    {
        if (!self::$authenticatedPartner) {
            return false;
        }

        $permissions = json_decode(self::$authenticatedPartner['permissions'] ?? '[]', true);
        return in_array($feature, $permissions) || in_array('*', $permissions);
    }

    /**
     * Require a specific permission, send error if not available.
     */
    public static function requirePermission(string $feature): bool
    {
        if (!self::hasPermission($feature)) {
            self::sendError(403, "Permission denied for: {$feature}", 'PERMISSION_DENIED');
            return false;
        }
        return true;
    }

    /**
     * Extract API key from request headers.
     */
    private static function extractApiKey(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        return null;
    }

    /**
     * Validate API key and return partner info.
     */
    private static function validateApiKey(string $apiKey): ?array
    {
        $hashedKey = hash('sha256', $apiKey);

        $results = DB::select("
            SELECT
                fak.id,
                fak.tenant_id,
                fak.name,
                fak.permissions,
                fak.rate_limit,
                fak.status,
                fak.last_used_at,
                t.name as tenant_name,
                t.domain as tenant_domain
            FROM federation_api_keys fak
            JOIN tenants t ON t.id = fak.tenant_id
            WHERE fak.key_hash = ?
            AND fak.status = 'active'
            AND (fak.expires_at IS NULL OR fak.expires_at > NOW())
        ", [$hashedKey]);

        $row = $results[0] ?? null;

        if ($row) {
            $partner = (array) $row;

            DB::update("
                UPDATE federation_api_keys
                SET last_used_at = NOW(), request_count = request_count + 1
                WHERE id = ?
            ", [$partner['id']]);

            return $partner;
        }

        return null;
    }

    /**
     * Check rate limit for partner.
     */
    private static function checkRateLimit(int $keyId): bool
    {
        $currentHour = date('Y-m-d H:00:00');

        try {
            $results = DB::select("
                SELECT
                    rate_limit,
                    COALESCE(hourly_request_count, 0) as hourly_request_count,
                    rate_limit_hour
                FROM federation_api_keys
                WHERE id = ?
            ", [$keyId]);

            $config = $results[0] ?? null;

            if (!$config) {
                return false;
            }

            $rateLimit = (int) ($config->rate_limit ?? 1000);
            $storedHour = $config->rate_limit_hour;
            $requestCount = (int) $config->hourly_request_count;

            if ($storedHour !== $currentHour) {
                DB::update("
                    UPDATE federation_api_keys
                    SET hourly_request_count = 1,
                        rate_limit_hour = ?
                    WHERE id = ?
                ", [$currentHour, $keyId]);
                $requestCount = 1;
            } else {
                DB::update("
                    UPDATE federation_api_keys
                    SET hourly_request_count = hourly_request_count + 1
                    WHERE id = ?
                ", [$keyId]);
                $requestCount++;
            }
        } catch (\PDOException $e) {
            // Fallback for pre-migration schemas
            $results = DB::select("
                SELECT rate_limit, request_count
                FROM federation_api_keys
                WHERE id = ?
            ", [$keyId]);

            $config = $results[0] ?? null;

            if (!$config) {
                return false;
            }

            $rateLimit = (int) ($config->rate_limit ?? 1000);
            $requestCount = (int) ($config->request_count ?? 0);

            error_log('FederationApiMiddleware: Using legacy rate limiting. Run migration for proper hourly reset.');
        }

        $resetTime = strtotime($currentHour) + 3600;
        $remaining = max(0, $rateLimit - $requestCount);

        header("X-RateLimit-Limit: {$rateLimit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$resetTime}");

        if ($requestCount > $rateLimit) {
            $retryAfter = max(1, $resetTime - time());
            header("Retry-After: {$retryAfter}");
            return false;
        }

        return true;
    }

    /**
     * Log API access for auditing.
     */
    private static function logApiAccess(int $keyId, string $endpoint, string $authMethod = 'api_key', ?bool $signatureValid = null): void
    {
        DB::insert("
            INSERT INTO federation_api_logs
            (api_key_id, endpoint, method, ip_address, user_agent, auth_method, signature_valid, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ", [
            $keyId,
            $endpoint,
            $_SERVER['REQUEST_METHOD'],
            ClientIp::get(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $authMethod,
            $signatureValid
        ]);
    }

    /**
     * Send JSON error response.
     */
    public static function sendError(int $statusCode, string $message, string $code): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'code' => $code,
            'message' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }

    /**
     * Send JSON success response.
     */
    public static function sendSuccess(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => true,
            'timestamp' => date('c')
        ], $data));
        exit;
    }

    /**
     * Send paginated response.
     */
    public static function sendPaginated(array $items, int $total, int $page, int $perPage): void
    {
        $totalPages = ceil($total / $perPage);

        self::sendSuccess([
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ]);
    }
}
