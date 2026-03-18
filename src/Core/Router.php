<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

/**
 *  Use AppCoreRouter instead. This class is maintained for backward compatibility only.
 */
/**
 * @deprecated Use AppCoreRouter instead. Maintained for backward compatibility.
 */
class Router
{
    protected $routes = [];

    public function get($route, $callback)
    {
        $this->addRoute('GET', $route, $callback);
    }

    public function post($route, $callback)
    {
        $this->addRoute('POST', $route, $callback);
    }

    public function add($method, $route, $callback)
    {
        $this->addRoute($method, $route, $callback);
    }

    protected function addRoute($method, $route, $callback)
    {
        // Convert route params {param} to Regex (globally)
        $routeRegex = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route);
        // Add start/end delimiters
        $routeRegex = "#^" . $routeRegex . "$#";

        $this->routes[] = [
            'method' => $method,
            'pattern' => $routeRegex,
            'callback' => $callback,
            'original_route' => $route // Store for debugging/param mapping if needed
        ];
    }

    /**
     * Cast route parameters to match the controller method's type-hints.
     * Converts string URL segments to int when the method expects int.
     */
    private static function castParamsToMethodTypes(object $controller, string $action, array $params): array
    {
        try {
            $ref = new \ReflectionMethod($controller, $action);
            $refParams = $ref->getParameters();
            foreach ($refParams as $i => $rp) {
                if (!isset($params[$i])) {
                    break;
                }
                $type = $rp->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                    if (!ctype_digit((string)$params[$i]) && $params[$i] !== '0') {
                        // Non-numeric value for int param — let TypeError be thrown
                        // so the caller's catch block returns a clean 404
                        $params[$i] = $params[$i]; // leave as-is, TypeError will fire
                    } else {
                        $params[$i] = (int)$params[$i];
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Method not found — will fail at call_user_func_array naturally
        }
        return $params;
    }

    public function hasRoute($method, $route)
    {
        foreach ($this->routes as $r) {
            if ($r['method'] === $method && $r['original_route'] === $route) {
                return true;
            }
        }
        return false;
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // 3. SLUG-AWARE ROUTING (Strip Tenant Base Path)
        // This ensures that /t/tenant/dashboard routes to /dashboard
        // HYPHEN-RESILIENT UPDATE: Uses preg_replace to safely handle hyphenated slugs
        $basePath = TenantContext::getBasePath();
        if (!empty($basePath) && $basePath !== '/') {
            // Strip the base path from the start of the URI using Regex
            $uri = preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $uri);
        }

        // Ensure root slash
        if (empty($uri) || $uri === false) {
            $uri = '/';
        }

        // NORMALIZE SLASHES (Fixes //messages issue)
        $uri = preg_replace('#/+#', '/', $uri);

        // Normalize Trailing Slash (except for root)
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }


        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches); // Remove full match

                // Build params
                $params = $matches;

                $callback = $route['callback'];

                // Cast numeric route params (e.g., {id}) to int when the
                // controller method has an int type-hint. This prevents
                // TypeErrors when non-numeric values like "abc" are passed.
                $castParams = array_values($params);

                try {
                    if (is_string($callback) && strpos($callback, '@') !== false) {
                        [$controllerClass, $action] = explode('@', $callback);
                        $controller = new $controllerClass();
                        $castParams = self::castParamsToMethodTypes($controller, $action, $castParams);
                        return call_user_func_array([$controller, $action], $castParams);
                    }

                    if (is_array($callback)) {
                        $controller = new $callback[0]();
                        $action = $callback[1];
                        $castParams = self::castParamsToMethodTypes($controller, $action, $castParams);
                        // PHP 8 Fix: Use array_values to force positional arguments
                        return call_user_func_array([$controller, $action], $castParams);
                    }

                    // PHP 8 Fix for Closures
                    return call_user_func_array($callback, $castParams);
                } catch (\TypeError $e) {
                    // Non-numeric value for an int param — return 404 JSON
                    http_response_code(404);
                    header('Content-Type: application/json');
                    echo json_encode(['errors' => [['code' => 'RESOURCE_NOT_FOUND', 'message' => 'Resource not found']]]);
                    return;
                }
            }
        }

        // 404 Handling
        http_response_code(404);

        // Log 404 error for analysis (with safety checks to prevent loops)
        $this->log404Error($uri);

        // Try to find a similar URL (fuzzy matching)
        $suggestion = null;
        if (class_exists('\Nexus\Middleware\UrlFuzzyMatcher')) {
            $suggestion = \Nexus\Middleware\UrlFuzzyMatcher::findSuggestion($uri);
        }

        // JSON Response for API
        if (strpos($uri, '/api') === 0) {
            header('Content-Type: application/json');
            $response = [
                'success' => false,
                'message' => 'API Endpoint Not Found: ' . $uri,
                'error' => '404_NOT_FOUND'
            ];
            if ($suggestion) {
                $response['suggestion'] = $suggestion;
            }
            echo json_encode($response);
            exit;
        }

        // HTML Response for Web
        $suggestedUrl = $suggestion;

        $errorPage = __DIR__ . '/../../views/404.php';

        require $errorPage;
    }

    /**
     * Log 404 error for tracking and analysis
     *
     * @param string $uri The requested URI that returned 404
     * @return void
     */
    protected function log404Error($uri)
    {
        try {
            // SAFETY: Prevent infinite loops by skipping admin routes and the 404 tracking page itself
            if (strpos($uri, '/admin-legacy/404-errors') !== false) {
                return; // Never log the 404 tracking admin page
            }

            // SAFETY: Skip if we're already logging a 404 (prevent recursion)
            static $isLogging = false;
            if ($isLogging) {
                return;
            }
            $isLogging = true;

            // Only log if the Error404Log model exists (table might not be created yet)
            if (!class_exists('\Nexus\Models\Error404Log')) {
                $isLogging = false;
                return;
            }

            // Skip logging for static files and common bot requests
            $skipPatterns = [
                '/\.(?:css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|map|xml|txt)$/i',
                '/wp-login\.php/i',
                '/wp-admin/i',
                '/xmlrpc\.php/i',
                '/\.env$/i',
                '/phpmyadmin/i',
                '/\.git\//i',
                '/\.well-known\//i',
                '/favicon\.ico$/i',
                '/robots\.txt$/i',
                '/sitemap\.xml$/i'
            ];

            foreach ($skipPatterns as $pattern) {
                if (preg_match($pattern, $uri)) {
                    $isLogging = false;
                    return; // Don't log these
                }
            }

            // Get request information
            $referer = $_SERVER['HTTP_REFERER'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $ipAddress = ClientIp::get();
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            // Log the 404 error
            \Nexus\Models\Error404Log::log($uri, $referer, $userAgent, $ipAddress, $userId);

            $isLogging = false;
        } catch (\Exception $e) {
            // Silently fail - don't break the application if logging fails
            error_log('Failed to log 404 error: ' . $e->getMessage());
            $isLogging = false;
        }
    }
}
