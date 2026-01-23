#!/usr/bin/env php
<?php
/**
 * FIX BLOG TENANT ISSUE
 * Helps diagnose and fix when blogs are in wrong tenant
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

// Color output helpers
function success($msg) { echo "\033[32m✓ {$msg}\033[0m\n"; }
function error($msg) { echo "\033[31m✗ {$msg}\033[0m\n"; }
function warn($msg) { echo "\033[33m⚠ {$msg}\033[0m\n"; }
function info($msg) { echo "\033[36m→ {$msg}\033[0m\n"; }

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         BLOG TENANT DIAGNOSTIC & FIX TOOL                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Check tenant distribution
info("Checking blog post distribution across tenants...");
echo "\n";

$stmt = Database::query("SELECT tenant_id, COUNT(*) as count, SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) as published FROM posts GROUP BY tenant_id");
$tenantCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Posts by Tenant:\n";
foreach ($tenantCounts as $row) {
    echo "  Tenant {$row['tenant_id']}: {$row['count']} total ({$row['published']} published)\n";
}
echo "\n";

// Check tenants table
info("Checking available tenants...");
echo "\n";

$stmt = Database::query("SELECT id, name, domain FROM tenants ORDER BY id");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Available Tenants:\n";
foreach ($tenants as $tenant) {
    echo "  [{$tenant['id']}] {$tenant['name']} ({$tenant['domain']})\n";
}
echo "\n";

// Analyze the situation
$totalPosts = array_sum(array_column($tenantCounts, 'count'));

if (count($tenantCounts) > 1) {
    warn("ISSUE DETECTED: Your blog posts are split across multiple tenants!");
    echo "\n";
    echo "This is why your blogs appear missing when logged into one tenant.\n";
    echo "Each tenant has its own isolated set of data.\n\n";

    // Find which tenant has the most posts
    usort($tenantCounts, function($a, $b) {
        return $b['count'] - $a['count'];
    });

    $mainTenant = $tenantCounts[0];
    echo "Your main blog tenant appears to be: Tenant {$mainTenant['tenant_id']} with {$mainTenant['count']} posts\n\n";

    // Offer solutions
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  SOLUTIONS                                                 ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";

    echo "Option 1: Access blogs from correct tenant\n";
    echo "  - Log into tenant {$mainTenant['tenant_id']} to see your {$mainTenant['count']} blog posts\n";
    echo "  - URL: http://{$tenants[$mainTenant['tenant_id']-1]['domain']}/admin/news\n\n";

    echo "Option 2: Move blogs to your primary tenant\n";
    echo "  - If you want all blogs in one tenant, we can move them\n";
    echo "  - This requires knowing which tenant should be your primary one\n\n";

    echo "Which tenant do you want to move all blogs TO?\n";
    foreach ($tenants as $tenant) {
        $postCount = 0;
        foreach ($tenantCounts as $tc) {
            if ($tc['tenant_id'] == $tenant['id']) {
                $postCount = $tc['count'];
                break;
            }
        }
        echo "  [{$tenant['id']}] {$tenant['name']} (currently has {$postCount} posts)\n";
    }
    echo "\n";
    echo "To move all blogs to a specific tenant, run:\n";
    echo "  php scripts/fix_blog_tenant.php --move-all-to=X\n";
    echo "  (where X is the tenant ID)\n\n";

} else {
    success("All {$totalPosts} blog posts are in tenant {$tenantCounts[0]['tenant_id']}");
    echo "\n";
    info("To access your blogs, make sure you're logged into tenant {$tenantCounts[0]['tenant_id']}");
    echo "\n";
}

// Handle --move-all-to parameter
$moveToTenant = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--move-all-to=') === 0) {
        $moveToTenant = (int) substr($arg, 14);
    }
}

if ($moveToTenant !== null) {
    echo "\n";
    warn("⚠️  PREPARING TO MOVE ALL BLOG POSTS TO TENANT {$moveToTenant}");
    echo "\n";

    // Verify tenant exists
    $tenantExists = false;
    foreach ($tenants as $tenant) {
        if ($tenant['id'] == $moveToTenant) {
            $tenantExists = true;
            echo "Target tenant: {$tenant['name']} ({$tenant['domain']})\n";
            break;
        }
    }

    if (!$tenantExists) {
        error("Tenant {$moveToTenant} does not exist!");
        exit(1);
    }

    echo "\n";
    echo "This will update the following:\n";
    foreach ($tenantCounts as $row) {
        if ($row['tenant_id'] != $moveToTenant) {
            echo "  - Move {$row['count']} posts from tenant {$row['tenant_id']} → tenant {$moveToTenant}\n";
        }
    }
    echo "\n";

    if (in_array('--force', $argv)) {
        $confirmed = true;
    } else {
        echo "Type 'MOVE BLOGS' to confirm: ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        $confirmed = ($line === 'MOVE BLOGS');
    }

    if (!$confirmed) {
        info("Operation cancelled");
        exit(0);
    }

    echo "\n";
    info("Creating backup before making changes...");

    // Create backup
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y_m_d_His');
    $backupFile = $backupDir . "/backup_before_blog_tenant_fix_{$timestamp}.sql";

    $dbName = getenv('DB_NAME');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    $dbHost = getenv('DB_HOST') ?: 'localhost';

    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s posts > %s 2>&1',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $sizeKB = round(filesize($backupFile) / 1024, 2);
        success("Backup created: {$backupFile} ({$sizeKB} KB)");
    } else {
        error("Failed to create backup!");
        exit(1);
    }

    echo "\n";
    info("Moving blog posts to tenant {$moveToTenant}...");

    try {
        $stmt = Database::query(
            "UPDATE posts SET tenant_id = ? WHERE tenant_id != ?",
            [$moveToTenant, $moveToTenant]
        );

        $movedCount = $stmt->rowCount();

        echo "\n";
        success("Successfully moved {$movedCount} blog posts to tenant {$moveToTenant}");
        echo "\n";

        // Verify
        $verifyStmt = Database::query("SELECT tenant_id, COUNT(*) as count FROM posts GROUP BY tenant_id");
        $verifyResults = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);

        info("Updated distribution:");
        foreach ($verifyResults as $row) {
            echo "  Tenant {$row['tenant_id']}: {$row['count']} posts\n";
        }
        echo "\n";

        success("All blog posts are now in tenant {$moveToTenant}!");
        echo "\n";
        info("To restore backup if needed:");
        echo "  mysql -u {$dbUser} -p {$dbName} < \"{$backupFile}\"\n";
        echo "\n";

    } catch (Exception $e) {
        echo "\n";
        error("Failed to move posts: " . $e->getMessage());
        echo "\n";
        warn("To restore backup:");
        echo "  mysql -u {$dbUser} -p {$dbName} < \"{$backupFile}\"\n";
        echo "\n";
        exit(1);
    }
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   DIAGNOSTIC COMPLETE                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if ($moveToTenant === null && count($tenantCounts) > 1) {
    info("Next steps:");
    echo "1. Decide which tenant should have all your blogs\n";
    echo "2. Run: php scripts/fix_blog_tenant.php --move-all-to=X\n";
    echo "3. Log into that tenant to see your blogs\n\n";
}
