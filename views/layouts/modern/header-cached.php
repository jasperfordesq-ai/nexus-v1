<?php
/**
 * Modern Layout Header - Cached Version
 *
 * This wrapper provides optional caching around the full header.
 * When caching is enabled and conditions are met, the header HTML is cached
 * for 5 minutes to improve performance.
 *
 * Cache is bypassed for:
 * - Non-GET requests (POST, PUT, DELETE, etc.)
 * - Requests with query parameters (to avoid caching dynamic states)
 * - Flash messages in session (need to be shown immediately)
 */

// Check if caching should be used
$useCache = \Nexus\Services\LayoutCache::isEnabled()
    && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && empty($_GET) // No query parameters
    && empty($_SESSION['success'])
    && empty($_SESSION['error'])
    && empty($_SESSION['layout_switch_error']);

if ($useCache) {
    // Try to get from cache or generate
    $cachedHeader = \Nexus\Services\LayoutCache::remember('modern-header', function() {
        ob_start();
        require __DIR__ . '/header.php';
        return ob_get_clean();
    });

    echo $cachedHeader;
} else {
    // Render header directly (no caching)
    require __DIR__ . '/header.php';
}
