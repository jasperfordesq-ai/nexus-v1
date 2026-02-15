<?php
// Admin SEO Dispatcher
// Modern layout (default)
$modernPath = dirname(__DIR__, 2) . '/modern/admin-legacy/seo/index.php';
if (file_exists($modernPath)) {
    require $modernPath;
    return;
}

echo "View not found: Admin SEO";
