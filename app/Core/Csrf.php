<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Core;

/**
 * CSRF protection using direct implementation.
 */
class Csrf
{
    /**
     * Generate a CSRF token (or return existing one for this session).
     */
    public static function generate(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Do NOT regenerate if exists — prevents race conditions
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Alias for generate().
     */
    public static function token(): string
    {
        return self::generate();
    }

    /**
     * Verify the CSRF token from the request against the session.
     * Checks $_POST['csrf_token'], X-CSRF-TOKEN header, and JSON body.
     *
     * @param string|null $token Token to verify (auto-detected from POST/header/JSON if null)
     */
    public static function verify($token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($token === null) {
            // Check POST data first
            $token = $_POST['csrf_token'] ?? '';

            // Check X-CSRF-TOKEN header (for AJAX/API requests)
            if (empty($token)) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            }

            // Check JSON body for API requests
            if (empty($token)) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                if (stripos($contentType, 'application/json') !== false) {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $token = $input['csrf_token'] ?? '';
                }
            }
        }

        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }

        return true;
    }

    /**
     * Verify the token or stop execution with a 403 error.
     * Skips CSRF verification for Bearer token authenticated requests.
     */
    public static function verifyOrDie(): void
    {
        // Skip CSRF for Bearer token authentication
        if (self::hasBearerToken()) {
            return;
        }

        if (!self::verify()) {
            // Debug Logging — redact token values to prevent PII/secret leakage
            $sessionToken = isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 8) . '...' : 'EMPTY';
            $postToken = isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 8) . '...' : 'EMPTY';
            $sessionId = session_id();
            $match = ($sessionToken === $postToken) ? 'MATCH' : 'MISMATCH';
            \Illuminate\Support\Facades\Log::warning("CSRF FAIL | SessionID: $sessionId | TokenMatch: $match | SessionPrefix: $sessionToken | PostPrefix: $postToken");

            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Invalid CSRF Token');
            }
            if (function_exists('abort')) {
                abort(403, 'Invalid CSRF Token. Please refresh the page and try again.');
            }
            http_response_code(403);
            echo "<h1>403 Forbidden</h1><p>Invalid CSRF Token. Please refresh the page and try again.</p>";
            exit;
        }
    }

    /**
     * Verify the token or return JSON error for API endpoints.
     * Skips CSRF verification for Bearer token authenticated requests.
     *
     * @return bool True if valid
     */
    public static function verifyOrDieJson(): bool
    {
        // Skip CSRF for Bearer token authentication (API clients, mobile apps)
        if (self::hasBearerToken()) {
            return true;
        }

        if (!self::verify()) {
            // Debug logging for API CSRF failures — redact token values
            $sessionToken = isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 8) . '...' : 'EMPTY';
            $headerToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? substr($_SERVER['HTTP_X_CSRF_TOKEN'], 0, 8) . '...' : 'EMPTY';
            $postToken = isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 8) . '...' : 'EMPTY';
            $sessionId = session_id();
            \Illuminate\Support\Facades\Log::warning("CSRF API FAIL | SessionID: $sessionId | SessionPrefix: $sessionToken | HeaderPrefix: $headerToken | PostPrefix: $postToken");

            if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing' || (function_exists('app') && app()->environment('testing'))) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, json_encode(['error' => __('api.invalid_csrf_token'), 'code' => 'csrf_invalid']));
            }
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => __('api.invalid_csrf_token'), 'code' => 'csrf_invalid']);
            exit;
        }
        return true;
    }

    /**
     * Output a hidden CSRF input field.
     */
    public static function input(): string
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Alias for input().
     */
    public static function field(): string
    {
        return self::input();
    }

    /**
     * Check if current request is authenticated via Bearer token.
     */
    private static function hasBearerToken(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return !empty($authHeader) && stripos($authHeader, 'Bearer ') === 0;
    }
}
