<?php

namespace App\Helpers;

/**
 * Hero Resolver
 * Resolves hero configuration for CivicOne pages based on current route
 *
 * Follows Section 9C of CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md
 *
 * @version 1.0.0
 * @since 2026-01-21
 */
class HeroResolver
{
    /**
     * Hero configuration from config/heroes.php
     *
     * @var array
     */
    protected static $config;

    /**
     * Get hero configuration for current page
     *
     * @param string $currentPath Current request path (e.g., '/members')
     * @param array $overrides Controller-provided overrides (e.g., ['title' => $member['name']])
     * @return array|null Hero configuration or null if no hero should be shown
     */
    public static function resolve(string $currentPath, array $overrides = []): ?array
    {
        // Load config on first use
        if (self::$config === null) {
            $configFile = __DIR__ . '/../../config/heroes.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [];
            }
        }

        // Normalize path (remove trailing slash except for root)
        $normalizedPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');

        // Try exact match first
        $heroConfig = self::$config[$normalizedPath] ?? null;

        // If no exact match, try route pattern matching
        if ($heroConfig === null) {
            $heroConfig = self::matchRoutePattern($normalizedPath);
        }

        // Fall back to default if still no match
        if ($heroConfig === null) {
            $heroConfig = self::$config['_default'] ?? null;
        }

        // If still no config, return null (no hero)
        if ($heroConfig === null) {
            return null;
        }

        // Merge overrides (controller-provided values take precedence)
        $heroConfig = array_merge($heroConfig, $overrides);

        // Validate and set defaults
        $heroConfig['variant'] = $heroConfig['variant'] ?? 'page';
        $heroConfig['title'] = $heroConfig['title'] ?? null;
        $heroConfig['lead'] = $heroConfig['lead'] ?? null;
        $heroConfig['cta'] = $heroConfig['cta'] ?? null;

        // Validate variant
        if (!in_array($heroConfig['variant'], ['page', 'banner'])) {
            $heroConfig['variant'] = 'page';
        }

        // Banner hero validation (Section 9C.4 BH-008: max ONE primary CTA)
        if ($heroConfig['variant'] === 'banner') {
            if (isset($heroConfig['cta']) && !is_array($heroConfig['cta'])) {
                // Invalid CTA format, remove it
                $heroConfig['cta'] = null;
            }
            if (isset($heroConfig['cta']) && (!isset($heroConfig['cta']['text']) || !isset($heroConfig['cta']['url']))) {
                // CTA missing required fields
                $heroConfig['cta'] = null;
            }
        } else {
            // Page hero must NOT have CTA (Section 9C.3 PH-006)
            $heroConfig['cta'] = null;
        }

        return $heroConfig;
    }

    /**
     * Match route pattern for dynamic routes
     *
     * @param string $path Current path
     * @return array|null Matched hero config or null
     */
    protected static function matchRoutePattern(string $path): ?array
    {
        // Pattern matching for dynamic routes
        // e.g., /members/123 -> /members/show
        // e.g., /groups/456 -> /groups/show
        // e.g., /events/789 -> /events/show

        $patterns = [
            '#^/members/\d+$#' => '/members/show',
            '#^/members/[a-z0-9-]+$#' => '/members/show',
            '#^/groups/\d+$#' => '/groups/show',
            '#^/groups/[a-z0-9-]+$#' => '/groups/show',
            '#^/groups/\d+/edit$#' => '/groups/edit',
            '#^/volunteering/\d+$#' => '/volunteering/show',
            '#^/volunteering/[a-z0-9-]+$#' => '/volunteering/show',
            '#^/listings/\d+$#' => '/listings/show',
            '#^/listings/[a-z0-9-]+$#' => '/listings/show',
            '#^/listings/\d+/edit$#' => '/listings/edit',
            '#^/events/\d+$#' => '/events/show',
            '#^/events/[a-z0-9-]+$#' => '/events/show',
            '#^/events/\d+/edit$#' => '/events/edit',
            '#^/profile/[a-z0-9-]+$#' => '/profile/show',
            '#^/help/.+$#' => '/help', // Help article pages
        ];

        foreach ($patterns as $pattern => $routeKey) {
            if (preg_match($pattern, $path)) {
                return self::$config[$routeKey] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if hero should be shown for current page
     *
     * @param string $currentPath Current request path
     * @return bool True if hero should be shown
     */
    public static function shouldShow(string $currentPath): bool
    {
        $heroConfig = self::resolve($currentPath);
        return $heroConfig !== null && isset($heroConfig['title']);
    }

    /**
     * Get hero variant for current page
     *
     * @param string $currentPath Current request path
     * @return string 'page' or 'banner'
     */
    public static function getVariant(string $currentPath): string
    {
        $heroConfig = self::resolve($currentPath);
        return $heroConfig['variant'] ?? 'page';
    }

    /**
     * Clear cached config (for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$config = null;
    }
}
