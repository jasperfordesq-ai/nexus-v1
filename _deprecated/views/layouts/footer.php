<?php
/**
 * Layout Footer Proxy
 *
 * Always uses the 'modern' layout (legacy CivicOne theme removed).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$layout = 'modern';

$footerPath = __DIR__ . '/' . $layout . '/footer.php';

echo "<!-- Footer: {$layout} -->";

if (file_exists($footerPath)) {
    require $footerPath;
} else {
    error_log("Layout footer not found: $footerPath (layout: $layout)");
    echo "<!-- ERROR: Layout footer not found for '{$layout}'. File: {$footerPath} -->";
}
