<?php
// Simple hero debug - accessible at /hero-debug.php
$_SERVER['REQUEST_URI'] = '/hour-timebank/groups';
require __DIR__ . '/../vendor/autoload.php';

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set CivicOne layout explicitly
$_SESSION['nexus_active_layout'] = 'civicone';

echo "<!DOCTYPE html><html><head><title>Hero Debug</title></head><body>";
echo "<h1>Hero System Debug</h1>";
echo "<pre>";

echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Layout: " . (\Nexus\Services\LayoutHelper::get()) . "\n\n";

// Test path processing
$currentPath = $_SERVER['REQUEST_URI'];
$currentPath = strtok($currentPath, '?');
echo "Path after strtok: " . $currentPath . "\n";

// Test tenant base path
$basePath = \Nexus\Core\TenantContext::getBasePath();
echo "TenantContext base path: '" . $basePath . "'\n";

// Test resolution with and without stripping
echo "\n--- Testing /hour-timebank/groups ---\n";
$hero1 = \App\Helpers\HeroResolver::resolve('/hour-timebank/groups');
echo "Title: " . ($hero1['title'] ?? 'NONE') . "\n";

echo "\n--- Testing /groups ---\n";
$hero2 = \App\Helpers\HeroResolver::resolve('/groups');
echo "Title: " . ($hero2['title'] ?? 'NONE') . "\n";

echo "</pre>";

echo "<hr>";
echo "<h2>Now test the actual render-hero.php logic:</h2>";

// Simulate what render-hero.php does
ob_start();
$hero = null;
require __DIR__ . '/../views/layouts/civicone/partials/render-hero.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";

if (strpos($output, 'HERO RENDERED') !== false) {
    echo "<p style='color:green; font-weight:bold;'>✓ HERO RENDERED SUCCESSFULLY</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>✗ HERO NOT RENDERED</p>";
}

echo "</body></html>";
