<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Core\TenantContext;
use App\Helpers\UrlHelper;

/**
 * BaseApiController - Laravel base class for all API controllers
 *
 * Provides standardized:
 * - JSON response handling with consistent envelope format (returns JsonResponse, NOT echo+exit)
 * - Authentication (Laravel Auth + legacy session fallback)
 * - Input parsing via Laravel Request
 * - Rate limiting
 * - Error handling with standardized codes
 *
 * Response envelope formats:
 * - v1 (legacy): { "success": true/false, "data": {...}, "error": "...", "code": "..." }
 * - v2 (current): { "data": {...} } or { "errors": [{code, message, field?}] }
 *
 * Key difference: methods RETURN JsonResponse instead of echo+exit.
 *
 * @package App\Http\Controllers\Api
 */
abstract class BaseApiController extends Controller
{
    /** Current API version for v2 endpoints */
    protected const API_VERSION = '2.0';

    /** API version for legacy endpoints */
    protected const API_VERSION_LEGACY = '1.0';

    /**
     * Whether this controller is a v2 API (uses respondWithData format)
     * Override in subclasses to mark as v2
     */
    protected bool $isV2Api = false;

    // ============================================
    // V2 RESPONSE METHODS
    // ============================================

    /**
     * Send a standardized data response (v2 format)
     *
     * Response format:
     * {
     *   "data": { ... },
     *   "meta": { "base_url": "...", ... }
     * }
     *
     * @param mixed $data Response data (object or array)
     * @param array|null $meta Optional metadata (base_url is added automatically)
     * @param int $status HTTP status code (default 200)
     * @return JsonResponse
     */
    protected function respondWithData($data, ?array $meta = null, int $status = 200): JsonResponse
    {
        $response = ['data' => $data];

        $baseMeta = ['base_url' => UrlHelper::getBaseUrl()];

        if ($meta !== null) {
            $response['meta'] = array_merge($baseMeta, $meta);
        } else {
            $response['meta'] = $baseMeta;
        }

        return $this->buildJsonResponse($response, $status);
    }

    /**
     * Send a standardized error response (v2 format)
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
     * @param int $status HTTP status code (default 400 — kept for backward
     *                    compatibility with ~1,900 existing callers). ALWAYS
     *                    pass an explicit status; for new code, prefer the
     *                    dedicated helpers where applicable:
     *                      - respondUnauthorized()   → 401
     *                      - respondForbidden()      → 403
     *                      - respondNotFound()       → 404
     *                      - respondServerError()    → 500
     * @return JsonResponse
     */
    protected function respondWithError(string $code, string $message, ?string $field = null, int $status = 400): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($field !== null) {
            $error['field'] = $field;
        }

        return $this->buildJsonResponse(['errors' => [$error]], $status);
    }

    /**
     * Semantic error helpers — prefer these over respondWithError() with magic
     * status numbers. Each helper sets a consistent default error code for the
     * failure class while still allowing caller customization.
     */
    protected function respondUnauthorized(string $message = 'Authentication required', string $code = 'AUTH_REQUIRED'): JsonResponse
    {
        return $this->respondWithError($code, $message, null, 401);
    }

    protected function respondForbidden(string $message = 'Access denied', string $code = 'FORBIDDEN'): JsonResponse
    {
        return $this->respondWithError($code, $message, null, 403);
    }

    protected function respondNotFound(string $message = 'Resource not found', string $code = 'NOT_FOUND'): JsonResponse
    {
        return $this->respondWithError($code, $message, null, 404);
    }

    protected function respondServerError(string $message = 'Internal server error', string $code = 'SERVER_ERROR'): JsonResponse
    {
        return $this->respondWithError($code, $message, null, 500);
    }

    /**
     * Send multiple errors in a single response (v2 format)
     *
     * @param array $errors Array of errors, each with 'code', 'message', optional 'field'
     * @param int $status HTTP status code (default 400)
     * @return JsonResponse
     */
    protected function respondWithErrors(array $errors, int $status = 400): JsonResponse
    {
        return $this->buildJsonResponse(['errors' => $errors], $status);
    }

    /**
     * Send a standardized collection response with cursor-based pagination (v2 format)
     *
     * Response format:
     * {
     *   "data": [ ... ],
     *   "meta": {
     *     "cursor": "abc123",
     *     "per_page": 20,
     *     "has_more": true
     *   }
     * }
     *
     * @param array $items Array of items
     * @param string|null $cursor Cursor for next page (null if no more items)
     * @param int $perPage Items per page
     * @param bool $hasMore Whether more items exist
     * @return JsonResponse
     */
    protected function respondWithCollection(array $items, ?string $cursor = null, int $perPage = 20, bool $hasMore = false, array $extraMeta = []): JsonResponse
    {
        $meta = [
            'base_url' => UrlHelper::getBaseUrl(),
            'per_page' => $perPage,
            'has_more' => $hasMore,
        ];

        if ($cursor !== null) {
            $meta['cursor'] = $cursor;
        }

        if ($extraMeta) {
            $meta = array_merge($meta, $extraMeta);
        }

        return $this->buildJsonResponse([
            'data' => $items,
            'meta' => $meta,
        ]);
    }

    /**
     * Send a collection response with offset-based pagination metadata (v2 format)
     *
     * Response format:
     * {
     *   "data": [ ... ],
     *   "meta": {
     *     "current_page": 1,
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
     * @return JsonResponse
     */
    protected function respondWithPaginatedCollection(array $items, int $total, int $page = 1, int $perPage = 20): JsonResponse
    {
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        return $this->buildJsonResponse([
            'data' => $items,
            'meta' => [
                'base_url' => UrlHelper::getBaseUrl(),
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    // ============================================
    // V1 (LEGACY) RESPONSE METHODS
    // ============================================

    /**
     * Send a standardized success response (v1 format)
     *
     * Standard envelope format:
     * {
     *   "success": true,
     *   "data": { ... },
     *   "meta": { "pagination": ... }
     * }
     *
     * @param mixed $data Response data
     * @param array|null $meta Optional metadata (pagination, etc.)
     * @param int $status HTTP status code (default 200)
     * @return JsonResponse
     */
    protected function success($data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return $this->buildJsonResponse($response, $status);
    }

    /**
     * Send a standardized error response (v1 format)
     *
     * Standard envelope format:
     * {
     *   "success": false,
     *   "error": "Human readable message",
     *   "code": "ERROR_CODE",
     *   "details": { ... }
     * }
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param string|null $code Error code for programmatic handling
     * @param array|null $details Additional error details
     * @return JsonResponse
     */
    protected function error(string $message, int $status = 400, ?string $code = null, ?array $details = null): JsonResponse
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

        return $this->buildJsonResponse($response, $status);
    }

    /**
     * Send a 201 Created response with optional location header
     *
     * @param mixed $data Created resource data
     * @param string|null $location URL of created resource
     * @return JsonResponse
     */
    protected function created($data = null, ?string $location = null): JsonResponse
    {
        $response = $this->success($data, null, 201);

        if ($location !== null) {
            $response->header('Location', $location);
        }

        return $response;
    }

    /**
     * Send a 204 No Content response
     *
     * @return JsonResponse
     */
    protected function noContent(): JsonResponse
    {
        // Send empty JSON object {} instead of null for 204 responses.
        // The frontend API client's unwrap logic ('data' in data) throws
        // TypeError when data is null, so we avoid that by sending {}.
        return $this->buildJsonResponse(new \stdClass(), 204);
    }

    // ============================================
    // INPUT METHODS (Laravel Request-based)
    // ============================================

    /**
     * Get input data from the current request (JSON body, form data, or query)
     *
     * @param string $key Input key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        return request()->input($key, $default);
    }

    /**
     * Get all input data from the current request
     *
     * @return array
     */
    protected function getAllInput(): array
    {
        return request()->all();
    }

    /**
     * Get a required input value or return an error response
     *
     * @param string $key Input key
     * @param string|null $errorMessage Custom error message
     * @return mixed The input value (never null/empty when returned)
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function requireInput(string $key, ?string $errorMessage = null)
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            $message = $errorMessage ?? __('api.missing_required_field', ['field' => $key]);
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->error($message, 400, 'VALIDATION_REQUIRED_FIELD', ['field' => $key])
            );
        }

        return $value;
    }

    /**
     * Get integer input with validation and optional min/max clamping
     *
     * @param string $key Input key
     * @param int|null $default Default value
     * @param int|null $min Minimum value (clamps if below)
     * @param int|null $max Maximum value (clamps if above)
     * @return int|null
     */
    protected function inputInt(string $key, ?int $default = null, ?int $min = null, ?int $max = null): ?int
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_scalar($value) || (is_string($value) && !is_numeric($value))) {
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

    /**
     * Get query parameter from URL
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function query(string $key, $default = null)
    {
        return request()->query($key, $default);
    }

    /**
     * Get integer query parameter with optional min/max clamping
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

        if (!is_scalar($value) || (is_string($value) && !is_numeric($value))) {
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
    // AUTHENTICATION METHODS
    // ============================================

    /**
     * Get current user ID (requires authentication)
     *
     * Checks Laravel Auth guard first, then falls back to legacy session.
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not authenticated
     */
    protected function getUserId(): int
    {
        $userId = $this->resolveUserId();

        if ($userId === null) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->error(__('api.auth_required'), 401, 'AUTH_REQUIRED')
            );
        }

        return $userId;
    }

    /**
     * Get current user ID if authenticated (optional auth)
     *
     * @return int|null User ID or null if not authenticated
     */
    protected function getOptionalUserId(): ?int
    {
        return $this->resolveUserId();
    }

    /**
     * Require authentication and return the user ID
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not authenticated
     */
    protected function requireAuth(): int
    {
        return $this->getUserId();
    }

    /**
     * Alias for requireAuth() — used by group controllers.
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not authenticated
     */
    protected function requireUserId(): int
    {
        return $this->getUserId();
    }

    /**
     * Alias for respondWithData() — used by group controllers.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse($data, int $status = 200): JsonResponse
    {
        return $this->respondWithData($data, null, $status);
    }

    /**
     * Alias for error() — used by group controllers.
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return $this->error($message, $status);
    }

    /**
     * Require admin role
     *
     * Accepts admin, tenant_admin, super_admin, and god roles.
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not admin
     */
    protected function requireAdmin(): int
    {
        $userId = $this->requireAuth();
        $user = $this->resolveUser();

        $role = $user->role ?? 'member';

        if (!in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'])) {
            if (!($user->is_super_admin ?? false) && !($user->is_tenant_super_admin ?? false)) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    $this->error(__('api.admin_access_required'), 403, 'AUTH_INSUFFICIENT_PERMISSIONS')
                );
            }
        }

        return $userId;
    }

    /**
     * Require broker-or-admin role.
     *
     * Accepts the same admin role/flag combinations as requireAdmin() PLUS
     * role='broker'. Used by endpoints behind the 'broker-or-admin' route
     * middleware (/v2/admin/broker/*, /v2/admin/vetting/*, /v2/admin/insurance/*,
     * and the broker-relevant subset of /v2/admin/users/* and /v2/admin/crm/*).
     *
     * Defence-in-depth: route middleware already gates entry, this controller
     * helper is the second-layer check matching requireAdmin()'s pattern.
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not broker or admin
     */
    protected function requireBrokerOrAdmin(): int
    {
        $userId = $this->requireAuth();
        $user = $this->resolveUser();

        $role = $user->role ?? 'member';

        // 'broker' and 'coordinator' are operational roles that share the
        // same panel as admins. Notification recipient queries throughout
        // the codebase include 'coordinator' alongside 'broker' — granting
        // them parity here so /broker/* deep-links from those bells work.
        if (in_array($role, ['broker', 'coordinator'], true)) {
            return $userId;
        }

        if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)) {
            return $userId;
        }

        if (($user->is_admin ?? false) || ($user->is_super_admin ?? false) || ($user->is_tenant_super_admin ?? false) || ($user->is_god ?? false)) {
            return $userId;
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            $this->error(__('api.admin_access_required'), 403, 'AUTH_INSUFFICIENT_PERMISSIONS')
        );
    }

    /**
     * Require super-admin role (tenant-scoped or platform-wide).
     *
     * Accepts both platform super-admins (is_super_admin, role=super_admin|god)
     * AND tenant-scoped super-admins (is_tenant_super_admin). This is the right
     * check for endpoints that operate within the caller's own tenant — the
     * middleware stack still pins operations to the caller's tenant_id.
     *
     * For endpoints that act ACROSS tenants (platform-wide config, tenant
     * creation, federation whitelist, cross-tenant user moves), use
     * requirePlatformSuperAdmin() instead — otherwise a tenant super-admin can
     * reach platform-level controls and laterally move into other tenants.
     *
     * @return int User ID
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not super admin
     */
    protected function requireSuperAdmin(): int
    {
        $userId = $this->requireAuth();
        $user = $this->resolveUser();

        $role = $user->role ?? 'member';

        if (in_array($role, ['super_admin', 'god'])) {
            return $userId;
        }

        if ($user->is_super_admin ?? false) {
            return $userId;
        }

        if ($user->is_tenant_super_admin ?? false) {
            return $userId;
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            $this->error(__('api.super_admin_required'), 403, 'AUTH_INSUFFICIENT_PERMISSIONS')
        );
    }

    /**
     * Require PLATFORM super-admin (cross-tenant). Rejects tenant super-admins.
     *
     * Use for: creating tenants, federation whitelist mutation, god-mode
     * queries that span tenants, cross-tenant user moves, platform-global
     * config. A tenant super-admin must NEVER reach these endpoints —
     * otherwise a compromised tenant admin account becomes a platform
     * compromise.
     *
     * @return int User ID
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not platform super admin
     */
    protected function requirePlatformSuperAdmin(): int
    {
        $userId = $this->requireAuth();
        $user = $this->resolveUser();

        $role = $user->role ?? 'member';

        // Explicitly NOT accepting is_tenant_super_admin.
        if (in_array($role, ['super_admin', 'god'], true) || ($user->is_super_admin ?? false) || ($user->is_god ?? false)) {
            return $userId;
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            $this->error(__('api.super_admin_required'), 403, 'AUTH_INSUFFICIENT_PERMISSIONS')
        );
    }

    /**
     * Get current tenant ID from TenantContext
     *
     * @return int
     */
    protected function getTenantId(): int
    {
        return TenantContext::getId();
    }

    /**
     * Resolve the authenticated user from a Bearer token on public routes.
     *
     * Public routes (outside auth:sanctum middleware) don't run the Authenticate
     * middleware, so Auth::user() is null even when a valid token is sent. This
     * method replicates the same hybrid auth logic as Authenticate middleware:
     *   1. Try Sanctum guard (native Sanctum tokens)
     *   2. Try Sanctum PersonalAccessToken lookup
     *   3. Try legacy JWT validation (most users still have JWT tokens)
     *
     * @return int|null User ID or null if no valid token
     */
    protected function resolveSanctumUserOptionally(): ?int
    {
        // 1. Try the Sanctum guard directly
        try {
            $user = Auth::guard('api')->user();
            if ($user) {
                return (int) $user->id;
            }
        } catch (\Throwable $e) {
            // Invalid token — try fallbacks
        }

        $bearer = request()->bearerToken();
        if (!$bearer) {
            return null;
        }

        // 2. Try Sanctum PersonalAccessToken lookup
        try {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
            if ($token && $token->tokenable) {
                return (int) $token->tokenable->id;
            }
        } catch (\Throwable $e) {
            // Not a Sanctum token — try legacy JWT
        }

        // 3. Try legacy JWT validation (same logic as Authenticate middleware)
        try {
            $tokenService = app(\App\Services\TokenService::class);
            $payload = $tokenService->validateToken($bearer);
            if ($payload) {
                $userId = (int) ($payload['user_id'] ?? $payload['sub'] ?? 0);
                if ($userId) {
                    return $userId;
                }
            }
        } catch (\Throwable $e) {
            // Invalid JWT — ignore
        }

        return null;
    }

    // ============================================
    // RATE LIMITING
    // ============================================

    /**
     * Apply rate limiting to an endpoint
     *
     * Returns a 429 response with Retry-After header when limit exceeded.
     *
     * @param string $action Action identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $windowSeconds Time window in seconds
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if rate limited
     */
    protected function rateLimit(string $action, int $maxAttempts = 60, int $windowSeconds = 60): void
    {
        $userId = $this->getOptionalUserId();
        $identifier = $userId ? "user:{$userId}" : 'ip:' . request()->ip();
        $key = "api:{$action}:{$identifier}";

        $executed = RateLimiter::attempt(
            $key,
            $maxAttempts,
            function () {
                // no-op — we just want to record the attempt
            },
            $windowSeconds
        );

        if (!$executed) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->error(
                    __('api.rate_limit_exceeded'),
                    429,
                    'RATE_LIMIT_EXCEEDED'
                )->header('Retry-After', (string) max(1, $retryAfter))
                 ->header('X-RateLimit-Limit', (string) $maxAttempts)
                 ->header('X-RateLimit-Remaining', '0')
                 ->header('X-RateLimit-Reset', (string) (time() + $retryAfter))
            );
        }
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    /**
     * Get HTTP request method
     *
     * @return string Uppercase method (GET, POST, PUT, DELETE, etc.)
     */
    protected function getMethod(): string
    {
        return strtoupper(request()->method());
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    protected function isPost(): bool
    {
        return request()->isMethod('POST');
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    protected function isGet(): bool
    {
        return request()->isMethod('GET');
    }

    // ============================================
    // CURSOR HELPERS
    // ============================================

    /**
     * Generate a cursor from an ID for cursor-based pagination
     *
     * @param int|string $id The ID to encode as a cursor
     * @return string The base64-encoded cursor
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


    // ============================================
    // ADMIN TENANT FILTER
    // ============================================

    /**
     * Resolve which tenant ID to filter admin listings by.
     *
     * - Regular admins: always scoped to their own tenant
     * - Super admins: defaults to current tenant, supports ?tenant_id=N or ?tenant_id=all
     */
    protected function resolveAdminTenantFilter(bool $isSuperAdmin, int $tenantId): ?int
    {
        $filterTenantIdRaw = request()->query('tenant_id');

        if ($isSuperAdmin) {
            if ($filterTenantIdRaw === 'all') {
                return null;
            }
            if ($filterTenantIdRaw !== null && is_numeric($filterTenantIdRaw)) {
                return (int) $filterTenantIdRaw;
            }
            return $tenantId;
        }

        return $tenantId;
    }

    // ============================================
    // PRIVATE HELPERS
    // ============================================

    /**
     * Build a JsonResponse with standard headers
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    private function buildJsonResponse($data, int $status = 200): JsonResponse
    {
        $version = $this->isV2Api ? self::API_VERSION : self::API_VERSION_LEGACY;

        $response = response()->json($data, $status, [
            'API-Version' => $version,
        ], JSON_UNESCAPED_UNICODE);

        // Add tenant header
        $tenantId = TenantContext::getId();
        if ($tenantId) {
            $response->header('X-Tenant-ID', (string) $tenantId);
        }

        return $response;
    }

    /**
     * Resolve the current user ID from Laravel Auth or legacy session
     *
     * @return int|null
     */
    private function resolveUserId(): ?int
    {
        // Try Laravel Auth first (covers Sanctum, Passport, session guard)
        try {
            $user = Auth::user();
            if ($user) {
                return (int) $user->id;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BaseApiController] Auth user resolution failed', [
                'error' => $e->getMessage(),
            ]);
        }

        // Legacy session fallback
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        return null;
    }

    /**
     * Resolve the current authenticated user object
     *
     * @return object The user object
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException if not authenticated
     */
    private function resolveUser(): object
    {
        $user = Auth::user();

        if ($user) {
            return $user;
        }

        // Legacy fallback: build a minimal user object from session + DB
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
            $row = \Illuminate\Support\Facades\DB::selectOne(
                "SELECT id, role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, TenantContext::getId()]
            );

            if ($row) {
                return $row;
            }
        }

        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            $this->error(__('api.auth_required'), 401, 'AUTH_REQUIRED')
        );
    }
}
