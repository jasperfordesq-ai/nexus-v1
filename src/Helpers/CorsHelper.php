<?php

namespace Nexus\Helpers;

/**
 * CORS Helper
 *
 * Provides secure Cross-Origin Resource Sharing (CORS) header management.
 * Validates origins against an allowlist instead of using wildcard (*).
 */
class CorsHelper
{
    /**
     * Default allowed origins for CORS requests.
     * Can be overridden via ALLOWED_ORIGINS environment variable.
     */
    private static array $defaultOrigins = [
        'https://project-nexus.ie',
        'https://www.project-nexus.ie',
        'https://hour-timebank.ie',
        'https://www.hour-timebank.ie',
        'http://staging.timebank.local',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ];

    private static ?array $allowedOrigins = null;

    /**
     * Get allowed origins from environment or defaults.
     */
    private static function getConfiguredOrigins(): array
    {
        if (self::$allowedOrigins === null) {
            $envOrigins = getenv('ALLOWED_ORIGINS') ?: ($_ENV['ALLOWED_ORIGINS'] ?? '');
            if (!empty($envOrigins)) {
                self::$allowedOrigins = array_map('trim', explode(',', $envOrigins));
            } else {
                self::$allowedOrigins = self::$defaultOrigins;
            }
        }
        return self::$allowedOrigins;
    }

    /**
     * Set CORS headers for the current request.
     * Validates the Origin header against allowed origins.
     *
     * @param array $additionalOrigins Additional allowed origins for this request
     * @param array $methods Allowed HTTP methods (default: GET, POST, OPTIONS)
     * @param array $headers Allowed request headers
     * @return bool True if origin was allowed, false if blocked
     */
    public static function setHeaders(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization']
    ): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // No origin header - not a CORS request (same-origin or non-browser client)
        if (empty($origin)) {
            return true;
        }

        // Build complete allowlist
        $allowedOrigins = array_merge(self::getConfiguredOrigins(), $additionalOrigins);

        // Check if origin is allowed
        if (!self::isOriginAllowed($origin, $allowedOrigins)) {
            return false;
        }

        // Set CORS headers with the specific origin (not wildcard)
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $methods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $headers));
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');

        return true;
    }

    /**
     * Handle preflight OPTIONS request.
     * Sets CORS headers and exits with 204 No Content.
     *
     * @param array $additionalOrigins Additional allowed origins
     * @param array $methods Allowed HTTP methods
     * @param array $headers Allowed request headers
     * @param int $maxAge Cache duration for preflight response in seconds
     */
    public static function handlePreflight(
        array $additionalOrigins = [],
        array $methods = ['GET', 'POST', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization'],
        int $maxAge = 86400
    ): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            return;
        }

        if (self::setHeaders($additionalOrigins, $methods, $headers)) {
            header('Access-Control-Max-Age: ' . $maxAge);
        }

        http_response_code(204);
        exit;
    }

    /**
     * Check if an origin is in the allowed list.
     *
     * @param string $origin Origin to check
     * @param array $allowedOrigins List of allowed origins
     * @return bool True if allowed
     */
    public static function isOriginAllowed(string $origin, array $allowedOrigins = []): bool
    {
        if (empty($allowedOrigins)) {
            $allowedOrigins = self::getConfiguredOrigins();
        }

        // Direct match
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Parse origin to check subdomains
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost === null) {
            return false;
        }

        // Check subdomain matches (e.g., https://tenant.project-nexus.ie)
        foreach ($allowedOrigins as $allowed) {
            $allowedHost = parse_url($allowed, PHP_URL_HOST);
            if ($allowedHost && str_ends_with($originHost, '.' . $allowedHost)) {
                // Ensure scheme matches
                $allowedScheme = parse_url($allowed, PHP_URL_SCHEME);
                $originScheme = parse_url($origin, PHP_URL_SCHEME);
                if ($allowedScheme === $originScheme) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add an origin to the allowed list dynamically.
     *
     * @param string $origin Origin to add (must include scheme, e.g., https://example.com)
     */
    public static function addAllowedOrigin(string $origin): void
    {
        $origin = rtrim($origin, '/');
        $origins = self::getConfiguredOrigins();
        if (!empty($origin) && !in_array($origin, $origins, true)) {
            self::$allowedOrigins[] = $origin;
        }
    }

    /**
     * Get the list of allowed origins.
     *
     * @return array List of allowed origins
     */
    public static function getAllowedOrigins(): array
    {
        return self::getConfiguredOrigins();
    }
}
