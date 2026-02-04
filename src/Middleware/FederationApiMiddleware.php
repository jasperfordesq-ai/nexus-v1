<?php

namespace Nexus\Middleware;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationGateway;
use Nexus\Services\FederationAuditService;
use Nexus\Services\FederationJwtService;

/**
 * FederationApiMiddleware
 *
 * Handles authentication and authorization for external Federation API requests.
 * Supports multiple authentication methods:
 * - API Key: Simple Bearer token authentication (internal/trusted partners)
 * - HMAC-SHA256: Request signing for external platform integrations
 * - JWT: Token-based authentication for user-level access (future)
 */
class FederationApiMiddleware
{
    private static ?array $authenticatedPartner = null;
    private static ?string $authMethod = null;

    // Timestamp tolerance for replay attack prevention (5 minutes)
    private const TIMESTAMP_TOLERANCE = 300;

    /**
     * Authenticate an incoming API request
     * Returns true if authenticated, sends error response and exits if not
     *
     * Authentication priority:
     * 1. HMAC-SHA256 request signing (highest security, for external platforms)
     * 2. JWT Bearer token (OAuth-style, for user-level access)
     * 3. API Key (simple, for internal/trusted partners)
     */
    public static function authenticate(): bool
    {
        // Check for HMAC signature first (highest security)
        if (self::hasHmacSignature()) {
            return self::authenticateWithHmac();
        }

        // Check for JWT token
        if (self::hasJwtToken()) {
            return self::authenticateWithJwt();
        }

        // Fall back to API key authentication
        return self::authenticateWithApiKey();
    }

    /**
     * Check if request has a JWT Bearer token
     */
    private static function hasJwtToken(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+([A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+)$/i', $authHeader)) {
            return true;
        }

        return false;
    }

    /**
     * Authenticate using JWT Bearer token
     */
    private static function authenticateWithJwt(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            self::sendError(401, 'Invalid authorization header', 'INVALID_AUTH_HEADER');
            return false;
        }

        $token = $matches[1];
        $payload = FederationJwtService::validateToken($token);

        if (!$payload) {
            self::sendError(401, 'Invalid or expired token', 'INVALID_TOKEN');
            return false;
        }

        // Get partner info from payload
        $partnerId = $payload['_partner']['id'] ?? null;

        if (!$partnerId) {
            self::sendError(401, 'Token missing partner information', 'INVALID_TOKEN');
            return false;
        }

        // Build partner array from token data
        $partner = [
            'id' => $partnerId,
            'tenant_id' => $payload['_partner']['tenant_id'],
            'name' => $payload['_partner']['name'],
            'permissions' => json_encode($payload['scope'] ?? []),
            'status' => 'active',
            'jwt_subject' => $payload['sub'],
            'jwt_issuer' => $payload['iss']
        ];

        // Check rate limit
        if (!self::checkRateLimit($partnerId)) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        // Store authenticated partner
        self::$authenticatedPartner = $partner;
        self::$authMethod = 'jwt';

        // Log API access
        self::logApiAccess($partnerId, $_SERVER['REQUEST_URI'], 'jwt', null);

        return true;
    }

    /**
     * Check if request has HMAC signature headers
     */
    private static function hasHmacSignature(): bool
    {
        return !empty($_SERVER['HTTP_X_FEDERATION_SIGNATURE'])
            && !empty($_SERVER['HTTP_X_FEDERATION_TIMESTAMP'])
            && !empty($_SERVER['HTTP_X_FEDERATION_PLATFORM_ID']);
    }

    /**
     * Authenticate using HMAC-SHA256 request signing
     */
    private static function authenticateWithHmac(): bool
    {
        $platformId = $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] ?? '';
        $timestamp = $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] ?? '';
        $signature = $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] ?? '';
        $tenantId = $_SERVER['HTTP_X_FEDERATION_TENANT_ID'] ?? null;

        // Validate timestamp to prevent replay attacks
        if (!self::validateTimestamp($timestamp)) {
            self::sendError(401, 'Request timestamp expired or invalid', 'TIMESTAMP_INVALID');
            return false;
        }

        // Look up partner by platform ID
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

        // Verify HMAC signature
        if (!self::verifyHmacSignature($signature, $partner['signing_secret'], $timestamp)) {
            self::sendError(401, 'Invalid request signature', 'SIGNATURE_INVALID');
            return false;
        }

        // Check rate limit
        if (!self::checkRateLimit($partner['id'])) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        // Store authenticated partner
        self::$authenticatedPartner = $partner;
        self::$authMethod = 'hmac';

        // Log API access
        self::logApiAccess($partner['id'], $_SERVER['REQUEST_URI'], 'hmac', true);

        return true;
    }

    /**
     * Authenticate using simple API key
     */
    private static function authenticateWithApiKey(): bool
    {
        // Check for API key in header or query param
        $apiKey = self::extractApiKey();

        if (empty($apiKey)) {
            self::sendError(401, 'API key required', 'MISSING_API_KEY');
            return false;
        }

        // Validate API key
        $partner = self::validateApiKey($apiKey);

        if (!$partner) {
            self::sendError(401, 'Invalid API key', 'INVALID_API_KEY');
            return false;
        }

        // Check if partner is active
        if ($partner['status'] !== 'active') {
            self::sendError(403, 'Partner account is not active', 'PARTNER_INACTIVE');
            return false;
        }

        // If HMAC signing is required for this key, reject API key auth
        if (!empty($partner['signing_enabled'])) {
            self::sendError(401, 'HMAC signing required for this API key', 'HMAC_REQUIRED');
            return false;
        }

        // Check rate limit
        if (!self::checkRateLimit($partner['id'])) {
            self::sendError(429, 'Rate limit exceeded', 'RATE_LIMIT_EXCEEDED');
            return false;
        }

        // Store authenticated partner for later use
        self::$authenticatedPartner = $partner;
        self::$authMethod = 'api_key';

        // Log API access
        self::logApiAccess($partner['id'], $_SERVER['REQUEST_URI'], 'api_key', null);

        return true;
    }

    /**
     * Validate request timestamp for replay attack prevention
     */
    private static function validateTimestamp(string $timestamp): bool
    {
        // Try parsing as ISO 8601 first
        $requestTime = strtotime($timestamp);

        if ($requestTime === false) {
            // Try as Unix timestamp
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
     * Verify HMAC-SHA256 signature
     *
     * Signature format:
     * HMAC-SHA256(secret, method + "\n" + path + "\n" + timestamp + "\n" + body)
     */
    private static function verifyHmacSignature(string $signature, string $secret, string $timestamp): bool
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];
        $body = file_get_contents('php://input') ?: '';

        // Build the string to sign
        $stringToSign = implode("\n", [
            $method,
            $path,
            $timestamp,
            $body
        ]);

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $stringToSign, $secret);

        // Compare using timing-safe comparison
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get partner by platform ID
     */
    private static function getPartnerByPlatformId(string $platformId): ?array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
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
        ");
        $stmt->execute([$platformId]);
        $partner = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($partner) {
            // Update last used timestamp
            $updateStmt = $db->prepare("
                UPDATE federation_api_keys
                SET last_used_at = NOW(), request_count = request_count + 1
                WHERE id = ?
            ");
            $updateStmt->execute([$partner['id']]);
        }

        return $partner ?: null;
    }

    /**
     * Get the authentication method used
     */
    public static function getAuthMethod(): ?string
    {
        return self::$authMethod;
    }

    /**
     * Generate a new signing secret
     * Used when creating or regenerating API keys with HMAC support
     */
    public static function generateSigningSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a signature for a request (for testing/client libraries)
     * Partners can use this format to sign their requests
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
     * Get the authenticated partner info
     */
    public static function getPartner(): ?array
    {
        return self::$authenticatedPartner;
    }

    /**
     * Get the partner's tenant ID
     */
    public static function getPartnerTenantId(): ?int
    {
        return self::$authenticatedPartner['tenant_id'] ?? null;
    }

    /**
     * Check if authenticated partner has permission for a feature
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
     * Require a specific permission, send error if not available
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
     * Extract API key from request
     *
     * SECURITY: API keys are ONLY accepted via HTTP headers, never via query parameters.
     * Query parameters are logged in server access logs, appear in browser history,
     * and can leak via referrer headers - making them unsuitable for secrets.
     *
     * Supported methods (in order of preference):
     * 1. Authorization: Bearer <api_key>
     * 2. X-API-Key: <api_key>
     */
    private static function extractApiKey(): ?string
    {
        // Check Authorization header (Bearer token) - preferred method
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check X-API-Key header - alternative method
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        // SECURITY: Query parameter authentication removed
        // API keys in URLs are logged in server access logs, browser history,
        // and can leak via HTTP Referer headers. Use header-based auth only.

        return null;
    }

    /**
     * Validate API key and return partner info
     */
    private static function validateApiKey(string $apiKey): ?array
    {
        $db = Database::getInstance();

        // Hash the API key for lookup (we store hashed keys)
        $hashedKey = hash('sha256', $apiKey);

        $stmt = $db->prepare("
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
        ");
        $stmt->execute([$hashedKey]);
        $partner = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($partner) {
            // Update last used timestamp
            $updateStmt = $db->prepare("
                UPDATE federation_api_keys
                SET last_used_at = NOW(), request_count = request_count + 1
                WHERE id = ?
            ");
            $updateStmt->execute([$partner['id']]);
        }

        return $partner ?: null;
    }

    /**
     * Check rate limit for partner
     *
     * Uses a sliding window approach with hourly buckets stored in the database.
     * Each hour, the counter resets automatically when the hour changes.
     *
     * Rate limit headers are set for client visibility:
     * - X-RateLimit-Limit: Maximum requests per hour
     * - X-RateLimit-Remaining: Requests remaining in current window
     * - X-RateLimit-Reset: Unix timestamp when the limit resets
     *
     * Requires migration: 2026_02_04_add_rate_limit_tracking_columns.sql
     */
    private static function checkRateLimit(int $keyId): bool
    {
        $db = Database::getInstance();
        $currentHour = date('Y-m-d H:00:00');

        try {
            // Get rate limit config and current usage
            // Uses new columns (hourly_request_count, rate_limit_hour) for sliding window
            $stmt = $db->prepare("
                SELECT
                    rate_limit,
                    COALESCE(hourly_request_count, 0) as hourly_request_count,
                    rate_limit_hour
                FROM federation_api_keys
                WHERE id = ?
            ");
            $stmt->execute([$keyId]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config) {
                return false;
            }

            $rateLimit = (int) ($config['rate_limit'] ?? 1000); // Default 1000 requests per hour
            $storedHour = $config['rate_limit_hour'];
            $requestCount = (int) $config['hourly_request_count'];

            // Check if we're in a new hour - reset counter if so
            if ($storedHour !== $currentHour) {
                // New hour, reset the counter to 1 (this request)
                $updateStmt = $db->prepare("
                    UPDATE federation_api_keys
                    SET hourly_request_count = 1,
                        rate_limit_hour = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$currentHour, $keyId]);
                $requestCount = 1;
            } else {
                // Same hour, increment counter
                $updateStmt = $db->prepare("
                    UPDATE federation_api_keys
                    SET hourly_request_count = hourly_request_count + 1
                    WHERE id = ?
                ");
                $updateStmt->execute([$keyId]);
                $requestCount++;
            }
        } catch (\PDOException $e) {
            // Fallback for pre-migration schemas: use simple request_count
            // This allows the system to work before migration is applied
            $stmt = $db->prepare("
                SELECT rate_limit, request_count
                FROM federation_api_keys
                WHERE id = ?
            ");
            $stmt->execute([$keyId]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config) {
                return false;
            }

            $rateLimit = (int) ($config['rate_limit'] ?? 1000);
            $requestCount = (int) ($config['request_count'] ?? 0);

            // Note: Without the new columns, rate limiting is less accurate
            // as request_count doesn't auto-reset hourly without a cron job
            error_log('FederationApiMiddleware: Using legacy rate limiting. Run migration 2026_02_04_add_rate_limit_tracking_columns.sql for proper hourly reset.');
        }

        // Calculate reset timestamp (start of next hour)
        $resetTime = strtotime($currentHour) + 3600;
        $remaining = max(0, $rateLimit - $requestCount);

        // Set rate limit headers for client visibility
        header("X-RateLimit-Limit: {$rateLimit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$resetTime}");

        // Check if rate limit exceeded
        if ($requestCount > $rateLimit) {
            // Add Retry-After header when rate limited
            $retryAfter = max(1, $resetTime - time());
            header("Retry-After: {$retryAfter}");
            return false;
        }

        return true;
    }

    /**
     * Log API access for auditing
     */
    private static function logApiAccess(int $keyId, string $endpoint, string $authMethod = 'api_key', ?bool $signatureValid = null): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO federation_api_logs
            (api_key_id, endpoint, method, ip_address, user_agent, auth_method, signature_valid, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $keyId,
            $endpoint,
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $authMethod,
            $signatureValid
        ]);
    }

    /**
     * Send JSON error response
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
     * Send JSON success response
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
     * Send paginated response
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
