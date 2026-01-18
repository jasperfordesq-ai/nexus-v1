<?php
/**
 * Layout Footer Proxy - ISOLATED
 *
 * Routes to the correct layout footer based on LayoutHelper.
 * NO FALLBACK - If the layout footer doesn't exist, shows error.
 * This ensures layouts are fully isolated from each other.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use centralized layout detection
$layout = \Nexus\Services\LayoutHelper::get();

// Security: sanitize
$layout = preg_replace('/[^a-z-]/', '', $layout);

// Absolute path verification
$footerPath = __DIR__ . '/' . $layout . '/footer.php';

// Output debug comment
echo "<!-- Footer: {$layout} -->";

// ISOLATED: No fallback to other layouts
if (file_exists($footerPath)) {
    require $footerPath;
} else {
    // Show clear error - don't silently fall back to another layout
    error_log("Layout footer not found: $footerPath (layout: $layout)");
    echo "<!-- ERROR: Layout footer not found for '{$layout}'. File: {$footerPath} -->";

    // In development, show visible error
    $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
    if ($appEnv === 'development' || $appEnv === 'local') {
        echo "<div style='background:#fef3c7; color:#92400e; padding:15px; border:2px solid #f59e0b; margin:10px; font-family:sans-serif;'>";
        echo "<strong>Layout Error:</strong> Footer not found for layout '{$layout}'<br>";
        echo "<small>Expected: {$footerPath}</small>";
        echo "</div>";
    }
}
