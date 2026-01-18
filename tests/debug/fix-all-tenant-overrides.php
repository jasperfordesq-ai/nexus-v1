<?php
/**
 * FIX ALL TENANT LAYOUT OVERRIDES - BATCH SCRIPT
 *
 * This script disables the forced 'civicone' layout overrides in all bridge files
 * that were bypassing the layout lockdown system.
 */

$bridgeFiles = [
    'views/wallet/index.php',
    'views/feed/index.php',
    'views/volunteering/show.php',
    'views/volunteering/my_applications.php',
    'views/volunteering/index.php',
    'views/volunteering/dashboard.php',
    'views/events/show.php',
    'views/events/index.php',
    'views/events/create.php',
    'views/notifications/index.php',
    'views/resources/index.php',
    'views/profile/edit.php',
    'views/messages/thread.php',
    'views/messages/index.php',
    'views/listings/index.php',
    'views/listings/edit.php',
    'views/listings/create.php',
    'views/groups/show.php',
    'views/groups/invite.php',
    'views/groups/index.php',
    'views/groups/edit.php',
];

$totalFixed = 0;
$totalFailed = 0;

foreach ($bridgeFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;

    if (!file_exists($fullPath)) {
        echo "⚠️  SKIP: $file (file not found)\n";
        $totalFailed++;
        continue;
    }

    $content = file_get_contents($fullPath);

    // Check if file has the tenant override
    if (strpos($content, 'public-sector-demo') === false) {
        echo "⏭️  SKIP: $file (no tenant override found)\n";
        continue;
    }

    // Find and comment out the tenant override block
    $pattern = '/\/\/ Force civicone for public-sector-demo tenant\s*\nif \(\s*\(isset\(\$_SERVER\[\'REQUEST_URI\'\]\).*?public-sector-demo.*?\n\) \{\s*\n\s*\$layout = \'civicone\';\s*\n\}/s';

    $replacement = '// LOCKDOWN: Tenant forced layouts REMOVED
// Force civicone for public-sector-demo tenant - DISABLED FOR LOCKDOWN
// This was bypassing the layout lockdown system and causing random layout switching
// All tenants should respect the global layout lockdown: \'modern\' by default
// if (
//     (isset($_SERVER[\'REQUEST_URI\']) && strpos($_SERVER[\'REQUEST_URI\'], \'public-sector-demo\') !== false) ||
//     (class_exists(\'\Nexus\Core\TenantContext\') && (\Nexus\Core\TenantContext::get()[\'slug\'] ?? \'\') === \'public-sector-demo\')
// ) {
//     $layout = \'civicone\';
// }';

    $newContent = preg_replace($pattern, $replacement, $content);

    if ($newContent !== $content) {
        file_put_contents($fullPath, $newContent);
        echo "✅ FIXED: $file\n";
        $totalFixed++;
    } else {
        echo "⚠️  WARN: $file (pattern not matched - manual review needed)\n";
        $totalFailed++;
    }
}

echo "\n";
echo "========================================\n";
echo "BATCH FIX SUMMARY\n";
echo "========================================\n";
echo "Total Files: " . count($bridgeFiles) . "\n";
echo "✅ Fixed: $totalFixed\n";
echo "❌ Failed/Skipped: $totalFailed\n";
echo "\n";

if ($totalFixed > 0) {
    echo "🎉 Successfully disabled $totalFixed tenant layout overrides!\n";
    echo "The layout lockdown is now more secure.\n";
} else {
    echo "⚠️  No files were fixed. Manual review recommended.\n";
}

echo "========================================\n";
