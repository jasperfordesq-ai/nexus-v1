#!/usr/bin/env php
<?php
/**
 * Diagnose Missing Blogs
 * Checks database for blog-related tables and data
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           BLOG DIAGNOSTIC TOOL                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = Database::getInstance();

    // Check for blog-related tables
    echo "→ Checking for blog-related tables...\n\n";

    $stmt = Database::query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $blogTables = [];
    foreach ($tables as $table) {
        if (stripos($table, 'blog') !== false || stripos($table, 'post') !== false) {
            $blogTables[] = $table;
        }
    }

    if (empty($blogTables)) {
        echo "❌ NO BLOG TABLES FOUND!\n\n";
        echo "→ Possible table names that might contain blogs:\n";
        echo "  - blog_posts\n";
        echo "  - blogs\n";
        echo "  - posts\n";
        echo "  - articles\n";
        echo "  - content\n\n";
        echo "→ All tables in database:\n";
        foreach ($tables as $table) {
            echo "  - {$table}\n";
        }
    } else {
        echo "✓ Found blog-related tables:\n";
        foreach ($blogTables as $table) {
            echo "  - {$table}\n";

            // Check row count
            $countStmt = Database::query("SELECT COUNT(*) as count FROM `{$table}`");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "    Records: {$count}\n";

            // Show structure
            $structStmt = Database::query("SHOW COLUMNS FROM `{$table}`");
            $columns = $structStmt->fetchAll(PDO::FETCH_ASSOC);
            echo "    Columns: ";
            $colNames = array_column($columns, 'Field');
            echo implode(', ', $colNames) . "\n\n";
        }
    }

    // Check recent migrations
    echo "\n→ Checking recent migrations...\n\n";

    if (in_array('migrations', $tables)) {
        $migrationsStmt = Database::query("
            SELECT migration, executed_at
            FROM migrations
            ORDER BY executed_at DESC
            LIMIT 10
        ");
        $migrations = $migrationsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($migrations)) {
            echo "✓ Recent migrations:\n";
            foreach ($migrations as $migration) {
                echo "  - {$migration['migration']} (executed: {$migration['executed_at']})\n";
            }
        }
    } else {
        echo "⚠️  No migrations table found\n";
    }

    // Check if data was deleted recently
    echo "\n→ Recommendations:\n\n";
    echo "1. Check your hosting provider's automated backups\n";
    echo "2. Check MySQL binary logs for DELETE/DROP commands\n";
    echo "3. Look for backups in:\n";
    echo "   - /backups/\n";
    echo "   - ~/backups/\n";
    echo "   - /var/backups/mysql/\n";
    echo "4. Contact hosting support for point-in-time restore\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   DIAGNOSTIC COMPLETE                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
