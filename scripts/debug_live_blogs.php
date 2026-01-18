#!/usr/bin/env php
<?php
/**
 * DEBUG LIVE SERVER BLOGS
 * Comprehensive diagnostic for missing blogs on live server
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
echo "║         LIVE SERVER BLOG DIAGNOSTIC                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 1. Check if posts table exists
info("Step 1: Checking if posts table exists...");
try {
    $stmt = Database::query("SHOW TABLES LIKE 'posts'");
    $exists = $stmt->fetch();

    if ($exists) {
        success("posts table exists");
    } else {
        error("posts table does NOT exist!");
        echo "\n";
        echo "The posts table is missing from the database.\n";
        echo "You need to run the migration that creates the posts table.\n";
        exit(1);
    }
} catch (Exception $e) {
    error("Error checking table: " . $e->getMessage());
    exit(1);
}

// 2. Check table structure
echo "\n";
info("Step 2: Checking posts table structure...");
$stmt = Database::query("SHOW COLUMNS FROM posts");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requiredColumns = ['id', 'tenant_id', 'author_id', 'title', 'slug', 'content', 'status', 'created_at'];
$foundColumns = array_column($columns, 'Field');

echo "Columns found: " . implode(', ', $foundColumns) . "\n";

$missingColumns = array_diff($requiredColumns, $foundColumns);
if (!empty($missingColumns)) {
    warn("Missing columns: " . implode(', ', $missingColumns));
} else {
    success("All required columns present");
}

// 3. Check total posts count
echo "\n";
info("Step 3: Checking total posts in database...");
$stmt = Database::query("SELECT COUNT(*) as count FROM posts");
$totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($totalCount == 0) {
    error("ZERO posts found in database!");
    echo "\n";
    echo "The posts table exists but contains no data.\n";
    echo "This confirms your blogs are missing from the live server.\n\n";
} else {
    success("Found {$totalCount} total posts");
}

// 4. Check posts by tenant
echo "\n";
info("Step 4: Checking posts by tenant...");
$stmt = Database::query("SELECT tenant_id, COUNT(*) as count, SUM(CASE WHEN status='published' THEN 1 ELSE 0 END) as published FROM posts GROUP BY tenant_id");
$tenantCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenantCounts)) {
    warn("No posts in any tenant");
} else {
    echo "Posts by tenant:\n";
    foreach ($tenantCounts as $row) {
        echo "  Tenant {$row['tenant_id']}: {$row['count']} total ({$row['published']} published)\n";
    }
}

// 5. Check tenant 2 specifically
echo "\n";
info("Step 5: Checking tenant 2 (hOUR Timebank) specifically...");
$stmt = Database::query("SELECT COUNT(*) as count FROM posts WHERE tenant_id = 2");
$tenant2Count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($tenant2Count == 0) {
    error("Tenant 2 has ZERO posts!");
    echo "\n";
    echo "Your local server has 52 posts in tenant 2.\n";
    echo "Your live server has 0 posts in tenant 2.\n\n";
    echo "This means the posts were either:\n";
    echo "  1. Never migrated to live server\n";
    echo "  2. Deleted from live server\n";
    echo "  3. Lost during a migration or deployment\n\n";
} else {
    success("Tenant 2 has {$tenant2Count} posts");

    // Show sample posts
    echo "\n";
    info("Sample posts from tenant 2:");
    $stmt = Database::query("SELECT id, title, status, created_at FROM posts WHERE tenant_id = 2 ORDER BY created_at DESC LIMIT 10");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($posts as $post) {
        echo "  [{$post['id']}] {$post['title']} ({$post['status']}) - {$post['created_at']}\n";
    }
}

// 6. Check for backups
echo "\n";
info("Step 6: Checking for database backups...");
$backupDir = __DIR__ . '/../backups';
if (is_dir($backupDir)) {
    $backups = glob($backupDir . '/*.sql');
    rsort($backups);
    $backups = array_slice($backups, 0, 5);

    if (!empty($backups)) {
        success("Found " . count($backups) . " backup files (showing 5 most recent):");
        foreach ($backups as $backup) {
            $size = round(filesize($backup) / (1024 * 1024), 2);
            $time = date('Y-m-d H:i:s', filemtime($backup));
            echo "  - " . basename($backup) . " ({$size} MB, {$time})\n";
        }
        echo "\n";
        info("You can restore from a backup if posts were recently deleted.");
    } else {
        warn("No backup files found");
    }
} else {
    warn("Backup directory does not exist");
}

// 7. Check recent migrations
echo "\n";
info("Step 7: Checking recent migrations...");
try {
    $stmt = Database::query("SELECT migration, executed_at FROM migrations ORDER BY executed_at DESC LIMIT 10");
    $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($migrations)) {
        echo "Recent migrations:\n";
        foreach ($migrations as $migration) {
            echo "  - {$migration['migration']} (executed: {$migration['executed_at']})\n";
        }
    } else {
        warn("No migrations found in migrations table");
    }
} catch (Exception $e) {
    warn("Cannot check migrations: " . $e->getMessage());
}

// 8. Environment check
echo "\n";
info("Step 8: Checking environment...");
$env = getenv('APP_ENV') ?: 'unknown';
$dbName = getenv('DB_NAME') ?: 'unknown';
$dbHost = getenv('DB_HOST') ?: 'unknown';

echo "Environment: {$env}\n";
echo "Database: {$dbName}\n";
echo "Host: {$dbHost}\n";

// 9. Generate summary
echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  DIAGNOSTIC SUMMARY                                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if ($totalCount == 0) {
    error("DIAGNOSIS: All blog posts are missing from live server database");
    echo "\n";
    echo "CAUSE: Posts table exists but contains no data.\n\n";

    echo "SOLUTIONS:\n\n";

    echo "Solution 1: Export posts from local and import to live\n";
    echo "  1. On LOCAL server, run:\n";
    echo "     php scripts/export_blogs.php\n";
    echo "  2. Upload the generated SQL file to live server\n";
    echo "  3. On LIVE server, run:\n";
    echo "     php scripts/import_blogs.php --file=exported_blogs.sql\n\n";

    echo "Solution 2: Use seed generator to export structure + data\n";
    echo "  1. Go to http://localhost/admin/seed-generator\n";
    echo "  2. Download 'Production Script' as SQL\n";
    echo "  3. Extract only the posts INSERT statements\n";
    echo "  4. Run on live server\n\n";

    echo "Solution 3: Manual SQL export/import\n";
    echo "  1. mysqldump posts table from local:\n";
    echo "     mysqldump -u user -p database posts > posts.sql\n";
    echo "  2. Import to live:\n";
    echo "     mysql -u user -p database < posts.sql\n\n";

} else if ($tenant2Count == 0) {
    warn("DIAGNOSIS: Posts exist but not in tenant 2");
    echo "\n";
    echo "Total posts: {$totalCount}\n";
    echo "Posts in tenant 2: 0\n\n";

    echo "CAUSE: Posts are in wrong tenant or were deleted from tenant 2.\n\n";

    echo "SOLUTIONS:\n\n";
    echo "Check which tenant has the posts and move them if needed.\n";

} else {
    success("DIAGNOSIS: Posts table is healthy and populated");
    echo "\n";
    echo "Total posts: {$totalCount}\n";
    echo "Posts in tenant 2: {$tenant2Count}\n\n";

    echo "If you still can't see blogs at http://hour-timebank.ie/admin/news,\n";
    echo "the issue is likely with:\n";
    echo "  - Routes not configured correctly\n";
    echo "  - Admin authentication/session issues\n";
    echo "  - View files missing\n";
    echo "  - PHP errors (check error logs)\n\n";
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   DIAGNOSTIC COMPLETE                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
