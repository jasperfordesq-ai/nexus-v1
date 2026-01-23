<?php

namespace Nexus\Core;

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

        // Ensure root slash
        if ($uri === '' || $uri === false) {
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

                if (is_string($callback) && strpos($callback, '@') !== false) {
                    [$controllerClass, $action] = explode('@', $callback);
                    $controller = new $controllerClass();
                    return call_user_func_array([$controller, $action], array_values($params));
                }

                if (is_array($callback)) {
                    $controller = new $callback[0]();
                    $action = $callback[1];
                    // PHP 8 Fix: Use array_values to force positional arguments
                    // This prevents "Unknown named parameter" errors if Controller var names don't match Route names
                    return call_user_func_array([$controller, $action], array_values($params));
                }

                // PHP 8 Fix for Closures
                return call_user_func_array($callback, array_values($params));
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
        require __DIR__ . '/../../views/404.php';
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
            if (strpos($uri, '/admin/404-errors') !== false) {
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
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
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
