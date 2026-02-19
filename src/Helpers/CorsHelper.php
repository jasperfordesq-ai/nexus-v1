<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
        'https://app.project-nexus.ie',
        'https://api.project-nexus.ie',
        'https://hour-timebank.ie',
        'https://www.hour-timebank.ie',
        'https://nexuscivic.ie',
        'https://www.nexuscivic.ie',
        'https://timebank.global',
        'https://www.timebank.global',
        'http://staging.timebank.local',
        'http://localhost:5173',
        'http://localhost:8090',
        'http://127.0.0.1:5173',
    ];

    /** Cached tenant domain origins (null = not loaded yet) */
    private static ?array $tenantDomainOrigins = null;

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
     * Checks static allowlist, subdomain patterns, AND dynamic tenant custom domains.
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

        // Direct match against static list
        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        // Parse origin to check subdomains and dynamic domains
        $originHost = parse_url($origin, PHP_URL_HOST);
        if ($originHost === null) {
            return false;
        }

        $originScheme = parse_url($origin, PHP_URL_SCHEME) ?: 'https';

        // Check subdomain matches (e.g., https://tenant.project-nexus.ie)
        foreach ($allowedOrigins as $allowed) {
            $allowedHost = parse_url($allowed, PHP_URL_HOST);
            if ($allowedHost && str_ends_with($originHost, '.' . $allowedHost)) {
                $allowedScheme = parse_url($allowed, PHP_URL_SCHEME);
                if ($allowedScheme === $originScheme) {
                    return true;
                }
            }
        }

        // Dynamic check: is this a tenant's custom domain?
        // Covers origins like https://hour-timebank.ie from tenants with custom domains.
        $tenantDomains = self::getTenantDomainOrigins();
        if (in_array($origin, $tenantDomains, true)) {
            return true;
        }

        return false;
    }

    /**
     * Get HTTPS origins for all active tenant custom domains.
     * Results are cached in Redis for 10 minutes to avoid per-request DB queries.
     *
     * @return array List of origins like ['https://hour-timebank.ie', 'https://www.hour-timebank.ie', ...]
     */
    private static function getTenantDomainOrigins(): array
    {
        if (self::$tenantDomainOrigins !== null) {
            return self::$tenantDomainOrigins;
        }

        $cacheKey = 'cors:tenant_domain_origins';
        $cacheTtl = 600; // 10 minutes

        // Try Redis cache first
        try {
            if (class_exists('\Nexus\Services\RedisCache') && \Nexus\Services\RedisCache::has($cacheKey, null)) {
                $cached = \Nexus\Services\RedisCache::get($cacheKey, null);
                if (is_array($cached)) {
                    self::$tenantDomainOrigins = $cached;
                    return $cached;
                }
            }
        } catch (\Throwable $e) {
            // Redis unavailable - fall through to DB
        }

        // Query all active tenant custom domains
        $origins = [];
        try {
            if (!class_exists('\Nexus\Core\Database', false)) {
                // Database class not loaded yet (called before autoloader)
                self::$tenantDomainOrigins = $origins;
                return $origins;
            }
            $db = \Nexus\Core\Database::getConnection();
            $stmt = $db->query(
                "SELECT domain FROM tenants WHERE domain IS NOT NULL AND domain != '' AND is_active = 1"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($rows as $domain) {
                $domain = trim($domain);
                if (empty($domain)) continue;
                // Add both with and without www
                $origins[] = 'https://' . $domain;
                if (!str_starts_with($domain, 'www.')) {
                    $origins[] = 'https://www.' . $domain;
                }
            }
        } catch (\Throwable $e) {
            // Database or class unavailable (e.g., called before autoloader) - return empty
        }

        // Cache the result
        try {
            if (class_exists('\Nexus\Services\RedisCache')) {
                \Nexus\Services\RedisCache::set($cacheKey, $origins, $cacheTtl, null);
            }
        } catch (\Throwable $e) {
            // Cache write failure is non-fatal
        }

        self::$tenantDomainOrigins = $origins;
        return $origins;
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
