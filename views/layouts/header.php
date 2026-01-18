<?php
/**
 * Layout Header Proxy - ISOLATED
 *
 * Routes to the correct layout header based on LayoutHelper.
 * NO FALLBACK - If the layout header doesn't exist, shows error.
 * This ensures layouts are fully isolated from each other.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LAYOUT DETECTION - CENTRALIZED & FIXED
$active_layout = \Nexus\Services\LayoutHelper::get();

// Migrate any legacy session keys (cleanup)
\Nexus\Services\LayoutHelper::migrateSessionKeys();

// Security: sanitize (already done in LayoutHelper, but double-check)
$active_layout = preg_replace('/[^a-z-]/', '', $active_layout);

// Absolute path verification
$target = __DIR__ . '/' . $active_layout . '/header.php';

// ISOLATED: No fallback to other layouts
if (file_exists($target)) {
    require_once $target;
} else {
    // Show clear error - don't silently fall back to another layout
    error_log("Layout header not found: $target (layout: $active_layout)");
    echo "<!-- ERROR: Layout header not found for '{$active_layout}'. File: {$target} -->";

    // In development, show visible error
    $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    if ($appEnv === 'development' || $appEnv === 'local') {
        echo "<div style='background:#fef3c7; color:#92400e; padding:15px; border:2px solid #f59e0b; margin:10px; font-family:sans-serif;'>";
        echo "<strong>Layout Error:</strong> Header not found for layout '{$active_layout}'<br>";
        echo "<small>Expected: {$target}</small>";
        echo "</div>";
    }
}
