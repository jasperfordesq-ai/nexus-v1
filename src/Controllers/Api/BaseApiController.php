<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiAuth;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Core\RateLimiter;
use Nexus\Helpers\UrlHelper;
use Nexus\Config\ApiDeprecation;

/**
 * BaseApiController - Base class for all API controllers
 *
 * Provides standardized:
 * - JSON response handling with consistent envelope format
 * - Authentication (session + Bearer token)
 * - CSRF verification
 * - Rate limiting with headers
 * - Input parsing (JSON body + POST)
 * - Error handling with standardized codes
 * - API versioning headers
 *
 * All API controllers should extend this class to eliminate code duplication
 * and ensure consistent behavior across the API.
 *
 * Response envelope formats:
 * - v1 (legacy): { "success": true/false, "data": {...}, "error": "...", "code": "..." }
 * - v2 (current): { "data": {...} } or { "errors": [{code, message, field?}] }
 *
 * @package Nexus\Controllers\Api
 */
abstract class BaseApiController
{
    use ApiAuth;

    /** Current API version for v2 endpoints */
    protected const API_VERSION = '2.0';

    /** API version for legacy endpoints */
    protected const API_VERSION_LEGACY = '1.0';

    /**
     * Cached input data from request body
     */
    private ?array $inputData = null;

    /**
     * Whether this controller is a v2 API (uses respondWithData format)
     * Override in subclasses to mark as v2
     */
    protected bool $isV2Api = false;

    // ============================================
    // RESPONSE METHODS
    // ============================================

    /**
     * Send a JSON response and exit
     *
     * Automatically adds:
     * - Content-Type header
     * - API version header
     * - Rate limit headers (if rate limiting was applied)
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return never
     */
    protected function jsonResponse($data, int $status = 200): void
    {
        // Set content type
        header('Content-Type: application/json');

        // Set API version header
        $this->setApiVersionHeaders();

        // Set rate limit headers if rate limiting was applied
        $this->setRateLimitHeaders();

        // Set X-Tenant-ID response header so clients can confirm tenant context
        $this->setTenantHeader();

        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Set API version headers and deprecation signals
     */
    private function setApiVersionHeaders(): void
    {
        $version = $this->isV2Api ? self::API_VERSION : self::API_VERSION_LEGACY;
        header('API-Version: ' . $version);

        // For v2 APIs, no deprecation needed
        if ($this->isV2Api) {
            return;
        }

        // Check config-driven deprecation mapping
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $deprecationHeaders = ApiDeprecation::getDeprecationHeaders($method, $path);

        if (!empty($deprecationHeaders)) {
            foreach ($deprecationHeaders as $name => $value) {
                header($name . ': ' . $value);
            }
            return;
        }

        // Fallback: Check controller-level deprecation flag
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/api/v2/') === false && strpos($requestUri, '/api/') !== false) {
            if ($this->hasV2Equivalent()) {
                header('X-API-Deprecated: true');
                header('Sunset: ' . ApiDeprecation::SUNSET_DATE);
            }
        }
    }

    /**
     * Check if the current v1 endpoint has a v2 equivalent
     * Override in controllers to mark specific endpoints as deprecated
     *
     * @return bool
     */
    protected function hasV2Equivalent(): bool
    {
        // Default: assume no v2 equivalent exists
        // Override in specific controllers to mark deprecation
        return false;
    }

    /**
     * Get deprecation notice for response body
     *
     * @return array|null _deprecated object or null
     */
    protected function getDeprecationNotice(): ?array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return ApiDeprecation::getDeprecationNotice($method, $path);
    }

    /**
     * Set rate limit headers from the current rate limit state
     */
    private function setRateLimitHeaders(): void
    {
        $state = RateLimiter::getCurrentState();

        if ($state === null) {
            return;
        }

        header('X-RateLimit-Limit: ' . $state['limit']);
        header('X-RateLimit-Remaining: ' . $state['remaining']);
        header('X-RateLimit-Reset: ' . $state['reset']);
    }

    /**
     * Set X-Tenant-ID response header so clients can confirm which tenant context is active
     */
    private function setTenantHeader(): void
    {
        $tenantId = TenantContext::getId();
        if ($tenantId) {
            header('X-Tenant-ID: ' . $tenantId);
        }
    }

    /**
     * Send a standardized success response
     *
     * Standard envelope format:
     * {
     *   "success": true,
     *   "data": { ... },
     *   "meta": { "pagination": ... }  // optional
     * }
     *
     * @param mixed $data Response data
     * @param array|null $meta Optional metadata (pagination, etc.)
     * @param int $status HTTP status code (default 200)
     * @return never
     */
    protected function success($data = null, ?array $meta = null, int $status = 200): void
    {
        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        $this->jsonResponse($response, $status);
    }

    /**
     * Send a standardized error response
     *
     * Standard envelope format:
     * {
     *   "success": false,
     *   "error": "Human readable message",
     *   "code": "ERROR_CODE",  // optional
     *   "details": { ... }     // optional, for validation errors
     * }
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param string|null $code Error code for programmatic handling
     * @param array|null $details Additional error details
     * @return never
     */
    protected function error(string $message, int $status = 400, ?string $code = null, ?array $details = null): void
    {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        if ($details !== null) {
            $response['details'] = $details;
        }

        $this->jsonResponse($response, $status);
    }

    /**
     * Send a 201 Created response with optional location header
     *
     * @param mixed $data Created resource data
     * @param string|null $location URL of created resource
     * @return never
     */
    protected function created($data = null, ?string $location = null): void
    {
        if ($location !== null) {
            header('Location: ' . $location);
        }
        $this->success($data, null, 201);
    }

    /**
     * Send a 204 No Content response
     *
     * @return never
     */
    protected function noContent(): void
    {
        $this->setApiVersionHeaders();
        $this->setRateLimitHeaders();
        http_response_code(204);
        exit;
    }

    /**
     * Send a paginated response (offset-based pagination)
     *
     * @param array $items Array of items
     * @param int $total Total count
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return never
     */
    protected function paginated(array $items, int $total, int $page = 1, int $perPage = 20): void
    {
        $totalPages = (int) ceil($total / $perPage);

        $this->success($items, [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ]
        ]);
    }

    // ============================================
    // STANDARDIZED API RESPONSE ENVELOPE
    // ============================================
    // These methods implement the universal API response format:
    // {
    //   "data": {},          // or [] for collections
    //   "meta": {},          // pagination, request info
    //   "errors": []         // array of {code, message, field?}
    // }

    /**
     * Send a standardized data response
     *
     * Response format:
     * {
     *   "data": { ... },
     *   "meta": { "base_url": "...", ... }  // always includes base_url for absolute URL construction
     * }
     *
     * @param mixed $data Response data (object or array)
     * @param array|null $meta Optional metadata (base_url is added automatically)
     * @param int $status HTTP status code (default 200)
     * @return never
     */
    protected function respondWithData($data, ?array $meta = null, int $status = 200): void
    {
        $response = ['data' => $data];

        // Always include base_url in meta for v2 APIs
        $baseMeta = ['base_url' => UrlHelper::getBaseUrl()];

        if ($meta !== null) {
            $response['meta'] = array_merge($baseMeta, $meta);
        } else {
            $response['meta'] = $baseMeta;
        }

        $this->jsonResponse($response, $status);
    }

    /**
     * Send a standardized error response
     *
     * Response format:
     * {
     *   "errors": [
     *     { "code": "ERROR_CODE", "message": "Human readable message", "field": "optional_field" }
     *   ]
     * }
     *
     * @param string $code Error code for programmatic handling
     * @param string $message Human-readable error message
     * @param string|null $field Optional field name for validation errors
     * @param int $status HTTP status code (default 400)
     * @return never
     */
    protected function respondWithError(string $code, string $message, ?string $field = null, int $status = 400): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($field !== null) {
            $error['field'] = $field;
        }

        $this->jsonResponse(['errors' => [$error]], $status);
    }

    /**
     * Send multiple errors in a single response
     *
     * @param array $errors Array of errors, each with 'code', 'message', optional 'field'
     * @param int $status HTTP status code (default 400)
     * @return never
     */
    protected function respondWithErrors(array $errors, int $status = 400): void
    {
        $this->jsonResponse(['errors' => $errors], $status);
    }

    /**
     * Send a standardized collection response with cursor-based pagination
     *
     * Response format:
     * {
     *   "data": [ ... ],
     *   "meta": {
     *     "cursor": "abc123",      // cursor for next page (null if no more)
     *     "per_page": 20,
     *     "has_more": true
     *   }
     * }
     *
     * @param array $items Array of items
     * @param string|null $cursor Cursor for next page (null if no more items)
     * @param int $perPage Items per page (for meta info)
     * @param bool $hasMore Whether more items exist
     * @return never
     */
    protected function respondWithCollection(array $items, ?string $cursor = null, int $perPage = 20, bool $hasMore = false): void
    {
        $meta = [
            'base_url' => UrlHelper::getBaseUrl(),
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ];

        if ($cursor !== null) {
            $meta['cursor'] = $cursor;
        }

        $this->jsonResponse([
            'data' => $items,
            'meta' => $meta,
        ]);
    }

    /**
     * Send a collection response with offset-based pagination metadata
     *
     * Response format:
     * {
     *   "data": [ ... ],
     *   "meta": {
     *     "page": 1,
     *     "per_page": 20,
     *     "total": 100,
     *     "total_pages": 5,
     *     "has_more": true
     *   }
     * }
     *
     * @param array $items Array of items
     * @param int $total Total count of all items
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return never
     */
    protected function respondWithPaginatedCollection(array $items, int $total, int $page = 1, int $perPage = 20): void
    {
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        $this->jsonResponse([
            'data' => $items,
            'meta' => [
                'base_url' => UrlHelper::getBaseUrl(),
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Generate a cursor from an ID for cursor-based pagination
     * The cursor is base64-encoded to be URL-safe
     *
     * @param int|string $id The ID to encode as a cursor
     * @return string The encoded cursor
     */
    protected function encodeCursor($id): string
    {
        return base64_encode((string) $id);
    }

    /**
     * Decode a cursor back to an ID for cursor-based pagination
     *
     * @param string $cursor The cursor to decode
     * @return string|null The decoded ID, or null if invalid
     */
    protected function decodeCursor(string $cursor): ?string
    {
        $decoded = base64_decode($cursor, true);
        return $decoded !== false ? $decoded : null;
    }

    // ============================================
    // INPUT METHODS
    // ============================================

    /**
     * Get input data from JSON body or POST
     *
     * @param string $key Input key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        $data = $this->getAllInput();
        return $data[$key] ?? $default;
    }

    /**
     * Get all input data
     *
     * @return array
     */
    protected function getAllInput(): array
    {
        if ($this->inputData !== null) {
            return $this->inputData;
        }

        $contentType = $_SERVER['CONTENT_TYPE']
            ?? $_SERVER['HTTP_CONTENT_TYPE']
            ?? '';

        $rawBody = file_get_contents('php://input');

        // Parse JSON if content type is JSON or body looks like JSON
        if (strpos($contentType, 'application/json') !== false ||
            (strlen($rawBody) > 0 && ($rawBody[0] === '{' || $rawBody[0] === '['))) {
            $this->inputData = json_decode($rawBody, true) ?? [];
        } else {
            $this->inputData = $_POST;
        }

        return $this->inputData;
    }

    /**
     * Get a required input value or return error
     *
     * @param string $key Input key
     * @param string|null $errorMessage Custom error message
     * @return mixed
     */
    protected function requireInput(string $key, ?string $errorMessage = null)
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            $message = $errorMessage ?? "Missing required field: {$key}";
            $this->error($message, 400, ApiErrorCodes::VALIDATION_REQUIRED_FIELD, ['field' => $key]);
        }

        return $value;
    }

    /**
     * Get integer input with validation
     *
     * @param string $key Input key
     * @param int|null $default Default value
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return int|null
     */
    protected function inputInt(string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return $default;
        }

        $intVal = (int) $value;

        if ($min !== null && $intVal < $min) {
            $intVal = $min;
        }

        if ($max !== null && $intVal > $max) {
            $intVal = $max;
        }

        return $intVal;
    }

    /**
     * Get boolean input
     *
     * @param string $key Input key
     * @param bool $default Default value
     * @return bool
     */
    protected function inputBool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    // ============================================
    // AUTHENTICATION METHODS
    // ============================================

    /**
     * Get current user ID (requires authentication)
     *
     * @return int User ID
     */
    protected function getUserId(): int
    {
        return $this->requireAuth();
    }

    /**
     * Get current user ID if authenticated (optional auth)
     *
     * @return int|null User ID or null
     */
    protected function getOptionalUserId(): ?int
    {
        return $this->getAuthenticatedUserId();
    }

    /**
     * Require admin role
     * Works for both Bearer token and session authentication
     *
     * @return int User ID
     */
    protected function requireAdmin(): int
    {
        $userId = $this->requireAuth();

        // Use the trait method which works for both Bearer and session auth
        $role = $this->getAuthenticatedUserRole() ?? 'member';

        // Accept admin, tenant_admin, super_admin, and god roles
        // Also accept if is_super_admin claim is set in the JWT
        if (!in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
            // Fallback: check is_super_admin from JWT or DB
            if (!$this->isAuthenticatedSuperAdmin()) {
                $this->error('Admin access required', 403, ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS);
            }
        }

        return $userId;
    }

    /**
     * Require broker/admin role for broker control endpoints.
     * Extends requireAdmin() to also allow tenant_admin, because tenant admins
     * are the primary users of broker controls but don't have the 'admin' role.
     *
     * @return int User ID
     */
    protected function requireBrokerAdmin(): int
    {
        $userId = $this->requireAuth();

        $role = $this->getAuthenticatedUserRole() ?? 'member';

        if (!in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
            $this->error('Admin access required', 403, ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS);
        }

        return $userId;
    }

    /**
     * Require super admin role
     * Checks JWT role claim AND is_super_admin flag (from JWT or DB fallback)
     *
     * @return int User ID
     */
    protected function requireSuperAdmin(): int
    {
        $userId = $this->requireAuth();

        $role = $this->getAuthenticatedUserRole() ?? 'member';

        // Check role claim first
        if (in_array($role, ['super_admin', 'god'])) {
            return $userId;
        }

        // Fallback: check is_super_admin from JWT claim or DB
        if ($this->isAuthenticatedSuperAdmin()) {
            return $userId;
        }

        $this->error('Super admin access required', 403, ApiErrorCodes::AUTH_INSUFFICIENT_PERMISSIONS);
        return $userId; // unreachable, but satisfies static analysis
    }

    /**
     * Check if the authenticated user is a super admin
     * First checks JWT is_super_admin claim, then falls back to DB lookup
     *
     * @return bool
     */
    protected function isAuthenticatedSuperAdmin(): bool
    {
        // Check JWT claim first (set during login)
        $payload = $this->tokenPayload ?? [];
        if (!empty($payload['is_super_admin'])) {
            return true;
        }

        // DB fallback for tokens issued before is_super_admin was added to JWT
        $userId = $this->authenticatedUserId ?? null;
        if ($userId) {
            $stmt = \Nexus\Core\Database::query(
                "SELECT is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
                [$userId]
            );
            $user = $stmt->fetch();
            if ($user && (!empty($user['is_super_admin']) || !empty($user['is_tenant_super_admin']))) {
                return true;
            }
        }

        return false;
    }

    // ============================================
    // SECURITY METHODS
    // ============================================

    /**
     * Verify CSRF token for session-based requests
     * Skips verification for Bearer token authentication
     */
    protected function verifyCsrf(): void
    {
        // Skip CSRF for Bearer token authentication (API clients)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!empty($authHeader) && stripos($authHeader, 'Bearer ') === 0) {
            return;
        }

        // For session-based requests, verify CSRF
        Csrf::verifyOrDieJson();
    }

    /**
     * Apply rate limiting to an endpoint
     *
     * Automatically adds X-RateLimit-* headers to all responses.
     * Returns 429 Too Many Requests with Retry-After header when limit exceeded.
     *
     * @param string $action Action identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return void
     */
    protected function rateLimit(string $action, int $maxAttempts = 60, int $windowSeconds = 60): void
    {
        $identifier = $this->getRateLimitIdentifier();
        $key = "api:{$action}:{$identifier}";

        if (!RateLimiter::attempt($key, $maxAttempts, $windowSeconds)) {
            // Get the rate limit state for headers
            $state = RateLimiter::getCurrentState();
            $retryAfter = $state ? ($state['reset'] - time()) : $windowSeconds;

            // Set Retry-After header for 429 responses
            header('Retry-After: ' . max(1, $retryAfter));

            $this->error(
                'Rate limit exceeded. Please try again later.',
                429,
                ApiErrorCodes::RATE_LIMIT_EXCEEDED
            );
        }
    }

    /**
     * Get identifier for rate limiting (user ID or IP)
     *
     * @return string
     */
    private function getRateLimitIdentifier(): string
    {
        $userId = $this->getAuthenticatedUserId();

        if ($userId) {
            return "user:{$userId}";
        }

        return 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    /**
     * Get current tenant ID
     *
     * @return int
     */
    protected function getTenantId(): int
    {
        return TenantContext::getId();
    }

    /**
     * Get request method
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    protected function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    protected function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Get query parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get integer query parameter
     *
     * @param string $key Parameter key
     * @param int|null $default Default value
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return int|null
     */
    protected function queryInt(string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
    {
        $value = $this->query($key);

        if ($value === null || $value === '') {
            return $default;
        }

        $intVal = (int) $value;

        if ($min !== null && $intVal < $min) {
            $intVal = $min;
        }

        if ($max !== null && $intVal > $max) {
            $intVal = $max;
        }

        return $intVal;
    }

    /**
     * Get a boolean query parameter from the URL
     *
     * Interprets these values as true: "1", "true", "yes", "on"
     * All other values (including empty string) are false.
     *
     * @param string $key Parameter key
     * @param bool $default Default value if parameter is not present
     * @return bool
     */
    protected function queryBool(string $key, bool $default = false): bool
    {
        $value = $this->query($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    // ============================================
    // URL HELPERS
    // ============================================

    /**
     * Convert a relative URL to absolute
     *
     * @param string|null $url The URL to convert
     * @return string|null Absolute URL or null
     */
    protected function absoluteUrl(?string $url): ?string
    {
        return UrlHelper::absolute($url);
    }

    /**
     * Convert avatar URL to absolute with fallback
     *
     * @param string|null $avatarUrl The avatar URL
     * @return string Absolute avatar URL
     */
    protected function absoluteAvatar(?string $avatarUrl): string
    {
        return UrlHelper::absoluteAvatar($avatarUrl);
    }

    /**
     * Get the base URL for API responses
     *
     * @return string Base URL
     */
    protected function getBaseUrl(): string
    {
        return UrlHelper::getBaseUrl();
    }
}
