<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

class Csrf
{
    /**
     * Generate a new token if one doesn't exist, and return it.
     */
    public static function generate()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // UNIQUE FIX: Do NOT regenerate if exists. 
        // Only generate if completely missing. 
        // This prevents race conditions or multi-calls from invalidating previous forms.
        if (empty($_SESSION['csrf_token'])) {
            // Use cryptographically secure random bytes (PHP 7+ always has random_bytes)
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify the token from the request against the session.
     * Checks $_POST['csrf_token'], X-CSRF-TOKEN header, and JSON body.
     */
    public static function verify($token = null)
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
     *
     * NOTE: Skips CSRF verification for Bearer token authenticated requests.
     */
    public static function verifyOrDie()
    {
        // Skip CSRF for Bearer token authentication
        if (self::hasBearerToken()) {
            return;
        }

        if (!self::verify()) {
            // Debug Logging
            $sessionToken = $_SESSION['csrf_token'] ?? 'EMPTY';
            $postToken = $_POST['csrf_token'] ?? 'EMPTY';
            $sessionId = session_id();
            error_log("CSRF FAIL | SessionID: $sessionId | SessionToken: $sessionToken | PostToken: $postToken");

            http_response_code(403);
            die("<h1>403 Forbidden</h1><p>Invalid CSRF Token. Please refresh the page and try again.</p>");
        }
    }

    /**
     * Helper to output a hidden input field.
     */
    public static function input()
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Alias for input() - outputs a hidden CSRF field.
     */
    public static function field()
    {
        return self::input();
    }

    /**
     * Alias for generate() - returns the CSRF token value.
     */
    public static function token()
    {
        return self::generate();
    }

    /**
     * Check if current request is authenticated via Bearer token.
     * Bearer-authenticated requests don't need CSRF protection because:
     * 1. The token itself proves the request came from an authorized client
     * 2. CSRF attacks rely on browser automatically sending cookies, but Bearer tokens
     *    must be explicitly attached by JavaScript/native code
     */
    private static function hasBearerToken(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return !empty($authHeader) && stripos($authHeader, 'Bearer ') === 0;
    }

    /**
     * Verify the token or return JSON error for API endpoints.
     * Returns true if valid, dies with JSON response if invalid.
     *
     * NOTE: Skips CSRF verification for Bearer token authenticated requests.
     * Bearer tokens are not vulnerable to CSRF attacks because they must be
     * explicitly attached to requests (not automatically sent like cookies).
     */
    public static function verifyOrDieJson()
    {
        // Skip CSRF for Bearer token authentication (API clients, mobile apps)
        if (self::hasBearerToken()) {
            return true;
        }

        if (!self::verify()) {
            // Debug logging for API CSRF failures
            $sessionToken = $_SESSION['csrf_token'] ?? 'EMPTY';
            $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'EMPTY';
            $postToken = $_POST['csrf_token'] ?? 'EMPTY';
            $sessionId = session_id();
            error_log("CSRF API FAIL | SessionID: $sessionId | SessionToken: $sessionToken | HeaderToken: $headerToken | PostToken: $postToken");

            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token', 'code' => 'csrf_invalid']);
            exit;
        }
        return true;
    }
}
