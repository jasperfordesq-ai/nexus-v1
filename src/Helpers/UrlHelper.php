<?php

namespace Nexus\Helpers;

use Nexus\Core\TenantContext;

/**
 * URL Helper
 *
 * Provides utilities for safe URL handling and redirect validation
 */
class UrlHelper
{
    /**
     * Default allowed hosts for redirects.
     * Can be overridden via ALLOWED_HOSTS environment variable.
     */
    private static array $defaultHosts = [
        'project-nexus.ie',
        'www.project-nexus.ie',
        'hour-timebank.ie',
        'www.hour-timebank.ie',
        'staging.timebank.local',
    ];

    private static ?array $allowedHosts = null;

    /**
     * Get allowed hosts from environment or defaults.
     */
    private static function getConfiguredHosts(): array
    {
        if (self::$allowedHosts === null) {
            $envHosts = getenv('ALLOWED_HOSTS') ?: ($_ENV['ALLOWED_HOSTS'] ?? '');
            if (!empty($envHosts)) {
                self::$allowedHosts = array_map('trim', explode(',', $envHosts));
            } else {
                self::$allowedHosts = self::$defaultHosts;
            }
        }
        return self::$allowedHosts;
    }

    /**
     * Validate and sanitize a redirect URL to prevent open redirect attacks.
     *
     * Only allows:
     * - Relative URLs (starting with /)
     * - URLs on allowed hosts
     *
     * @param string|null $url The URL to validate
     * @param string $fallback Fallback URL if validation fails (must be relative)
     * @return string Safe URL to redirect to
     */
    public static function safeRedirect(?string $url, string $fallback = '/dashboard'): string
    {
        // Null or empty - use fallback
        if ($url === null || trim($url) === '') {
            return self::ensureRelative($fallback);
        }

        $url = trim($url);

        // Block javascript:, data:, vbscript:, etc.
        if (preg_match('/^[a-z]+:/i', $url)) {
            $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

            // Only allow http and https schemes
            if (!in_array($scheme, ['http', 'https'], true)) {
                return self::ensureRelative($fallback);
            }

            // For http/https, validate the host
            $host = parse_url($url, PHP_URL_HOST);
            if ($host === null || !self::isAllowedHost($host)) {
                return self::ensureRelative($fallback);
            }
        }

        // Prevent protocol-relative URLs (//evil.com)
        if (str_starts_with($url, '//')) {
            return self::ensureRelative($fallback);
        }

        // Relative URLs are safe
        if (str_starts_with($url, '/')) {
            // Extra safety: prevent /\ or encoded variants that could be misinterpreted
            if (preg_match('#^/[\\\\]#', $url)) {
                return self::ensureRelative($fallback);
            }
            return $url;
        }

        // Anything else (e.g., "evil.com/path") - use fallback
        return self::ensureRelative($fallback);
    }

    /**
     * Get safe redirect URL from HTTP_REFERER.
     *
     * @param string $fallback Fallback URL if referer is invalid
     * @return string Safe URL to redirect to
     */
    public static function safeReferer(string $fallback = '/dashboard'): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        return self::safeRedirect($referer, $fallback);
    }

    /**
     * Check if a host is in the allowed list.
     *
     * @param string $host Host to check
     * @return bool True if allowed
     */
    public static function isAllowedHost(string $host): bool
    {
        $host = strtolower($host);
        $allowedHosts = self::getConfiguredHosts();

        // Direct match
        if (in_array($host, $allowedHosts, true)) {
            return true;
        }

        // Check for subdomain matches (e.g., tenant.project-nexus.ie)
        foreach ($allowedHosts as $allowed) {
            if (str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a host to the allowed list dynamically.
     *
     * @param string $host Host to add
     */
    public static function addAllowedHost(string $host): void
    {
        $host = strtolower(trim($host));
        $hosts = self::getConfiguredHosts();
        if (!empty($host) && !in_array($host, $hosts, true)) {
            self::$allowedHosts[] = $host;
        }
    }

    /**
     * Ensure a fallback URL is relative.
     *
     * @param string $url URL to check
     * @return string Relative URL
     */
    private static function ensureRelative(string $url): string
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }
        return '/dashboard';
    }
}
