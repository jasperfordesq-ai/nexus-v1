<?php
/**
 * Hero Rendering Helper
 * Include this partial in page templates to render the hero
 *
 * Usage in page templates:
 *
 * <?php
 * // Option 1: Let auto-resolve determine hero from route
 * require __DIR__ . '/../../layouts/civicone/header.php';
 * require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
 * ?>
 *
 * <?php
 * // Option 2: Override specific hero properties
 * $hero = ['title' => 'Custom Title', 'lead' => 'Custom lead paragraph'];
 * require __DIR__ . '/../../layouts/civicone/header.php';
 * require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
 * ?>
 *
 * @version 1.0.0
 * @since 2026-01-21
 */

// Auto-resolve hero if not already set
if (!isset($hero)) {
    // Use namespaced class (autoloaded via composer)
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/';
    // Remove query string
    $currentPath = strtok($currentPath, '?');

    // Strip tenant base path if present (e.g., /hour-timebank/groups -> /groups)
    if (class_exists('\Nexus\Core\TenantContext')) {
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        if (!empty($basePath) && $basePath !== '/' && strpos($currentPath, $basePath) === 0) {
            $currentPath = substr($currentPath, strlen($basePath));
            // Ensure path starts with /
            if (empty($currentPath) || $currentPath[0] !== '/') {
                $currentPath = '/' . $currentPath;
            }
        } else {
            // If getBasePath() returns empty but REQUEST_URI has a tenant prefix, try to strip it manually
            // Match pattern: /tenant-slug/route -> /route
            if (empty($basePath) && preg_match('#^/([a-z0-9-]+)/(.+)$#', $currentPath, $matches)) {
                $possibleRoutePath = '/' . $matches[2];
                $testHero = \App\Helpers\HeroResolver::resolve($possibleRoutePath);

                // If the route without tenant prefix has a title, use that path
                if ($testHero && !empty($testHero['title'])) {
                    $currentPath = $possibleRoutePath;
                }
            }
        }
    }

    // Get base configuration from route
    $hero = \App\Helpers\HeroResolver::resolve($currentPath);
}

// Allow controller/page-specific overrides to be merged
if (isset($heroOverrides) && is_array($heroOverrides)) {
    $hero = array_merge($hero ?? [], $heroOverrides);
}

// Render hero partial if config exists and has title
if (isset($hero) && is_array($hero) && !empty($hero['title'])) {
    require __DIR__ . '/page-hero.php';
}
