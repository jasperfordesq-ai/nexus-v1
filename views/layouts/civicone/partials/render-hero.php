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

        // DEBUG: Log base path
        error_log("HERO DEBUG: TenantContext::getBasePath() = '" . $basePath . "'");

        if (!empty($basePath) && $basePath !== '/' && strpos($currentPath, $basePath) === 0) {
            $currentPath = substr($currentPath, strlen($basePath));
            // Ensure path starts with /
            if (empty($currentPath) || $currentPath[0] !== '/') {
                $currentPath = '/' . $currentPath;
            }
            error_log("HERO DEBUG: Stripped base path, new currentPath = " . $currentPath);
        } else {
            error_log("HERO DEBUG: Not stripping base path. Empty: " . (empty($basePath) ? 'YES' : 'NO') . ", Is root: " . ($basePath === '/' ? 'YES' : 'NO') . ", Starts with base: " . (strpos($currentPath, $basePath) === 0 ? 'YES' : 'NO'));

            // If getBasePath() returns empty but REQUEST_URI has a tenant prefix, try to strip it manually
            // Match pattern: /tenant-slug/route -> /route
            if (empty($basePath) && preg_match('#^/([a-z0-9-]+)/(.+)$#', $currentPath, $matches)) {
                // Check if this looks like a tenant path by trying both versions
                $possibleTenantPath = '/' . $matches[1];
                $possibleRoutePath = '/' . $matches[2];

                error_log("HERO DEBUG: Trying to resolve without tenant prefix: " . $possibleRoutePath);
                $testHero = \App\Helpers\HeroResolver::resolve($possibleRoutePath);

                // If the route without tenant prefix has a title, use that path
                if ($testHero && !empty($testHero['title'])) {
                    $currentPath = $possibleRoutePath;
                    error_log("HERO DEBUG: Using stripped path: " . $currentPath);
                }
            }
        }
    }

    // DEBUG: Log what path we're using
    error_log("HERO DEBUG: REQUEST_URI = " . ($_SERVER['REQUEST_URI'] ?? 'NOT SET'));
    error_log("HERO DEBUG: currentPath after processing = " . $currentPath);

    // Get base configuration from route
    $hero = \App\Helpers\HeroResolver::resolve($currentPath);

    // DEBUG: Log resolution result
    error_log("HERO DEBUG: Hero resolved = " . ($hero !== null ? 'YES' : 'NO'));
    if ($hero) {
        error_log("HERO DEBUG: Title = " . ($hero['title'] ?? 'NO TITLE'));
    }
}

// Allow controller/page-specific overrides to be merged
if (isset($heroOverrides) && is_array($heroOverrides)) {
    $hero = array_merge($hero ?? [], $heroOverrides);
}

// DEBUG: Log before rendering
error_log("HERO DEBUG: About to render - hero exists: " . (isset($hero) ? 'YES' : 'NO') . ", is array: " . (is_array($hero ?? null) ? 'YES' : 'NO') . ", has title: " . (!empty($hero['title'] ?? null) ? 'YES' : 'NO'));

// VISIBLE DEBUG
echo '<!-- HERO DEBUG START -->';
echo '<!-- REQUEST_URI: ' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'NOT SET') . ' -->';
echo '<!-- currentPath: ' . htmlspecialchars($currentPath ?? 'NOT SET') . ' -->';
echo '<!-- hero isset: ' . (isset($hero) ? 'YES' : 'NO') . ' -->';
echo '<!-- hero is_array: ' . (is_array($hero ?? null) ? 'YES' : 'NO') . ' -->';
echo '<!-- hero has title: ' . (!empty($hero['title'] ?? null) ? 'YES' : 'NO') . ' -->';
if ($hero) {
    echo '<!-- hero title value: ' . htmlspecialchars($hero['title'] ?? 'NONE') . ' -->';
}
echo '<!-- HERO DEBUG END -->';

// Render hero partial if config exists and has title
if (isset($hero) && is_array($hero) && !empty($hero['title'])) {
    error_log("HERO DEBUG: RENDERING HERO NOW");
    echo '<!-- HERO RENDERING NOW -->';
    require __DIR__ . '/page-hero.php';
    echo '<!-- HERO RENDERED -->';
} else {
    error_log("HERO DEBUG: NOT RENDERING - condition failed");
    echo '<!-- HERO NOT RENDERING - condition failed -->';
}
