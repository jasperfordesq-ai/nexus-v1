<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use Nexus\Models\SeoRedirect;

class RedirectMiddleware
{
    /**
     * Check if the current request should be redirected.
     * Call this early in the request lifecycle, after TenantContext is resolved.
     */
    public static function handle(): void
    {
        // CRITICAL FIX: Never redirect POST/PUT/DELETE requests
        // This prevents data loss when forms are submitted to URLs that might trigger a redirect
        // especially in subdirectory installations where /admin might be matched incorrectly due to prefixes.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return;
        }

        // MOBILE FIX: Skip redirect middleware for auth pages (login, register, password reset)
        // Mobile browsers may have issues with cookie handling during auth flows
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (preg_match('#/(login|register|logout|password)(/|$)#', $requestUri)) {
            return;
        }

        // SAFETY: Prevent infinite redirect loops
        static $redirectCount = 0;
        if ($redirectCount > 0) {
            return; // Already processing a redirect
        }
        $redirectCount++;

        // CRITICAL: Check for redirect loop cookie
        // MOBILE FIX: Increased threshold from 3 to 5 to prevent false positives on mobile browsers
        // which may handle cookies differently
        $loopCookie = $_COOKIE['redirect_loop_detector'] ?? '0';
        if ((int)$loopCookie >= 5) {
            error_log("REDIRECT LOOP BLOCKED: Too many redirects from " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . " on URI: " . ($_SERVER['REQUEST_URI'] ?? '/'));
            // Clear the cookie to allow user to continue after a while
            setcookie('redirect_loop_detector', '0', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            $redirectCount--;
            return;
        }

        // Get the request URI without query string
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // SAFETY: Never redirect admin pages (checking for /admin anywhere in path to handle subdirectories)
        if (strpos($requestUri, '/admin') !== false || strpos($requestUri, '/api/') !== false) {
            $redirectCount--;
            return;
        }

        // Skip common file extensions
        $extension = pathinfo($requestUri, PATHINFO_EXTENSION);
        if (in_array($extension, ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'xml', 'txt', 'pdf'])) {
            $redirectCount--;
            return;
        }

        // Check for a redirect
        try {
            $destinationUrl = SeoRedirect::checkRedirect($requestUri);
            if ($destinationUrl) {
                // SAFETY: Prevent self-redirects (exact match)
                if ($destinationUrl === $requestUri) {
                    $redirectCount--;
                    return;
                }

                // SAFETY: Prevent redirects to the same path (normalized)
                $destPath = parse_url($destinationUrl, PHP_URL_PATH);
                if ($destPath === $requestUri) {
                    $redirectCount--;
                    return;
                }

                // Loop detection cookie logic
                // MOBILE FIX: Increased expiry from 5 to 15 seconds to give mobile browsers more time
                $currentCount = isset($_COOKIE['redirect_loop_detector']) ? (int)$_COOKIE['redirect_loop_detector'] : 0;
                setcookie('redirect_loop_detector', (string)($currentCount + 1), [
                    'expires' => time() + 15,
                    'path' => '/',
                    'secure' => !empty($_SERVER['HTTPS']),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                // Perform 301 permanent redirect
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $destinationUrl);
                exit;
            }
        } catch (\Throwable $e) {
            error_log('Redirect middleware error: ' . $e->getMessage());
        }

        $redirectCount--;
    }

    /**
     * Safe redirect helper that prevents loops.
     * Use this instead of direct header() redirects in controllers.
     *
     * @param string $url The URL to redirect to
     * @param int $statusCode HTTP status code (default 302)
     */
    public static function safeRedirect(string $url, int $statusCode = 302): void
    {
        // Check for redirect loop
        // MOBILE FIX: Increased threshold from 5 to 8 to prevent false positives
        $loopCookie = $_COOKIE['redirect_loop_detector'] ?? '0';
        if ((int)$loopCookie >= 8) {
            error_log("REDIRECT LOOP BLOCKED (safeRedirect): Attempted redirect to " . $url);
            // Don't redirect, let the page render
            return;
        }

        // Get current path
        $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $targetPath = parse_url($url, PHP_URL_PATH);

        // Prevent self-redirect
        if ($currentUri === $targetPath) {
            return;
        }

        // Set loop detection cookie
        // MOBILE FIX: Increased expiry from 10 to 20 seconds
        $currentCount = (int)$loopCookie;
        setcookie('redirect_loop_detector', (string)($currentCount + 1), [
            'expires' => time() + 20,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        http_response_code($statusCode);
        header('Location: ' . $url);
        exit;
    }
}
