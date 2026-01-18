#!/usr/bin/env php
<?php
/**
 * EXPORT BLOGS TO SQL
 * Exports all blog posts from posts table as INSERT statements
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
echo "║         BLOG POST EXPORTER                                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Parse arguments
$tenantId = null;
$statusFilter = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--tenant=') === 0) {
        $tenantId = (int) substr($arg, 9);
    }
    if (strpos($arg, '--status=') === 0) {
        $statusFilter = substr($arg, 9);
    }
}

if (in_array('--help', $argv)) {
    echo "Usage: php export_blogs.php [options]\n\n";
    echo "Options:\n";
    echo "  --tenant=X         Export only posts from tenant X (default: all)\n";
    echo "  --status=STATUS    Export only posts with status (published/draft)\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php export_blogs.php\n";
    echo "  php export_blogs.php --tenant=2\n";
    echo "  php export_blogs.php --tenant=2 --status=published\n\n";
    exit(0);
}

// Build query
$query = "SELECT * FROM posts WHERE 1=1";
$params = [];

if ($tenantId !== null) {
    $query .= " AND tenant_id = ?";
    $params[] = $tenantId;
}

if ($statusFilter !== null) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY id ASC";

info("Fetching blog posts...");

try {
    $stmt = Database::query($query, $params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($posts)) {
        warn("No posts found matching criteria");
        exit(0);
    }

    $count = count($posts);
    success("Found {$count} posts to export");

    // Get column names
    $columns = array_keys($posts[0]);

    // Generate SQL
    $sql = "-- ============================================================================\n";
    $sql .= "-- Blog Posts Export\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Total Posts: {$count}\n";
    if ($tenantId !== null) {
        $sql .= "-- Tenant: {$tenantId}\n";
    }
    if ($statusFilter !== null) {
        $sql .= "-- Status: {$statusFilter}\n";
    }
    $sql .= "-- ============================================================================\n\n";

    $sql .= "-- Disable foreign key checks temporarily\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $sql .= "-- Insert blog posts\n";

    foreach ($posts as $post) {
        $values = [];
        foreach ($columns as $column) {
            $value = $post[$column];

            if ($value === null) {
                $values[] = "NULL";
            } else if (is_numeric($value)) {
                $values[] = $value;
            } else {
                // Escape single quotes and wrap in quotes
                $escaped = str_replace("'", "''", $value);
                $values[] = "'" . $escaped . "'";
            }
        }

        $sql .= "INSERT INTO posts (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
    }

    $sql .= "\n-- Re-enable foreign key checks\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n\n";

    $sql .= "-- ============================================================================\n";
    $sql .= "-- Export Complete: {$count} posts\n";
    $sql .= "-- ============================================================================\n";

    // Save to file
    $exportDir = __DIR__ . '/../exports';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }

    $timestamp = date('Y_m_d_His');
    $filename = "exported_blogs_{$timestamp}";
    if ($tenantId !== null) {
        $filename .= "_tenant{$tenantId}";
    }
    if ($statusFilter !== null) {
        $filename .= "_{$statusFilter}";
    }
    $filename .= ".sql";

    $exportFile = $exportDir . '/' . $filename;
    file_put_contents($exportFile, $sql);

    $sizeKB = round(filesize($exportFile) / 1024, 2);

    echo "\n";
    success("Export complete!");
    echo "\n";
    echo "  File: {$exportFile}\n";
    echo "  Posts: {$count}\n";
    echo "  Size: {$sizeKB} KB\n";
    echo "\n";

    info("To import on live server:");
    echo "  1. Upload this file to live server\n";
    echo "  2. Run: mysql -u user -p database < {$filename}\n";
    echo "     OR\n";
    echo "  3. Run: php scripts/import_blogs.php --file={$filename}\n";
    echo "\n";

} catch (Exception $e) {
    error("Export failed: " . $e->getMessage());
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   EXPORT COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
