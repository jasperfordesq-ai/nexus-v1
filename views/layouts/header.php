<?php
/**
 * Layout Header Proxy
 *
 * Always uses the 'modern' layout (legacy CivicOne theme removed).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$active_layout = 'modern';

$target = __DIR__ . '/' . $active_layout . '/header.php';

if (file_exists($target)) {
    require_once $target;
} else {
    error_log("Layout header not found: $target (layout: $active_layout)");
    echo "<!-- ERROR: Layout header not found for '{$active_layout}'. File: {$target} -->";
}
