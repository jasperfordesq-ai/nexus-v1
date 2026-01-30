<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiAuth;
use Nexus\Core\Csrf;
use Nexus\Core\TenantContext;
use Nexus\Core\RateLimiter;

/**
 * BaseApiController - Base class for all API controllers
 *
 * Provides standardized:
 * - JSON response handling with consistent envelope format
 * - Authentication (session + Bearer token)
 * - CSRF verification
 * - Rate limiting
 * - Input parsing (JSON body + POST)
 * - Error handling
 *
 * All API controllers should extend this class to eliminate code duplication
 * and ensure consistent behavior across the API.
 *
 * @package Nexus\Controllers\Api
 */
abstract class BaseApiController
{
    use ApiAuth;

    /**
     * Cached input data from request body
     */
    private ?array $inputData = null;

    // ============================================
    // RESPONSE METHODS
    // ============================================

    /**
     * Send a JSON response and exit
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return never
     */
    protected function jsonResponse($data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
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
        http_response_code(204);
        exit;
    }

    /**
     * Send a paginated response
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
            $this->error($message, 400, 'VALIDATION_ERROR', ['field' => $key]);
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
     *
     * @return int User ID
     */
    protected function requireAdmin(): int
    {
        $userId = $this->requireAuth();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $role = $_SESSION['user_role'] ?? 'member';

        if (!in_array($role, ['admin', 'super_admin', 'god'])) {
            $this->error('Admin access required', 403, 'FORBIDDEN');
        }

        return $userId;
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
            $this->error('Rate limit exceeded. Please try again later.', 429, 'RATE_LIMITED');
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
}
