#!/usr/bin/env php
<?php
/**
 * IMPORT BLOGS FROM SQL
 * Safely imports blog posts with backup and duplicate checking
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
echo "║         BLOG POST IMPORTER                                 ║\n";
echo "║         ⚠️  IMPORTS DATA INTO DATABASE                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Parse arguments
$importFile = null;
$force = in_array('--force', $argv);
$skipBackup = in_array('--skip-backup', $argv);

foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $importFile = substr($arg, 7);
    }
}

if (in_array('--help', $argv) || !$importFile) {
    echo "Usage: php import_blogs.php --file=export_file.sql [options]\n\n";
    echo "Options:\n";
    echo "  --file=filename    SQL file to import (required)\n";
    echo "  --skip-backup      Skip automatic backup (NOT RECOMMENDED)\n";
    echo "  --force            Skip confirmations (DANGEROUS)\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php import_blogs.php --file=exported_blogs_2026_01_12_153045.sql\n\n";
    echo "⚠️  WARNING: This will INSERT data into your database!\n\n";
    exit(0);
}

// Find import file
$importPath = null;
if (file_exists($importFile)) {
    $importPath = $importFile;
} else {
    $possiblePaths = [
        __DIR__ . '/../exports/' . $importFile,
        __DIR__ . '/' . $importFile,
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $importPath = $path;
            break;
        }
    }
}

if (!$importPath) {
    error("Import file not found: {$importFile}");
    echo "\n";
    info("Looking in:");
    echo "  - " . __DIR__ . "/../exports/\n";
    echo "  - Current directory\n";
    echo "\n";
    exit(1);
}

// Check environment
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'production' && !$force) {
    warn("⚠️  You are importing on PRODUCTION environment!");
    echo "\n";
}

// Show import info
$importSize = round(filesize($importPath) / 1024, 2);
info("Import File Information:");
echo "  File: " . basename($importPath) . "\n";
echo "  Size: {$importSize} KB\n";
echo "\n";

// Check current posts count
info("Checking current database state...");
try {
    $stmt = Database::query("SELECT COUNT(*) as count FROM posts");
    $currentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Current posts in database: {$currentCount}\n";
    echo "\n";
} catch (Exception $e) {
    error("Cannot check database: " . $e->getMessage());
    exit(1);
}

// Preview import file
info("Analyzing import file...");
$content = file_get_contents($importPath);
$insertCount = preg_match_all('/INSERT INTO posts/i', $content);
echo "  Found approximately {$insertCount} INSERT statements\n";
echo "\n";

if (!$force) {
    warn("This will add {$insertCount} blog posts to your database");
    echo "\n";

    if ($currentCount > 0) {
        warn("Your database already has {$currentCount} posts!");
        echo "This import may create duplicates if these posts already exist.\n\n";
    }

    echo "Do you want to continue? Type 'yes': ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));

    if ($line !== 'yes') {
        info("Import cancelled");
        exit(0);
    }
}

// Create backup
if (!$skipBackup) {
    echo "\n";
    info("Creating backup before import...");

    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y_m_d_His');
    $backupFile = $backupDir . "/backup_before_blog_import_{$timestamp}.sql";

    $dbName = getenv('DB_NAME');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    $dbHost = getenv('DB_HOST') ?: 'localhost';

    $command = "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} posts > \"{$backupFile}\" 2>&1";
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $backupSize = round(filesize($backupFile) / 1024, 2);
        success("Backup created: " . basename($backupFile) . " ({$backupSize} KB)");
    } else {
        error("Failed to create backup!");
        if (!$force) {
            echo "\n";
            echo "Continue without backup? (NOT RECOMMENDED) Type 'yes': ";
            $line = trim(fgets($handle));
            if ($line !== 'yes') {
                error("Aborted - backup failed");
                exit(1);
            }
        }
    }
}

// Execute import
echo "\n";
info("Importing blog posts...");

try {
    $pdo = Database::getInstance();
    $pdo->beginTransaction();

    // Execute the SQL file
    $sql = file_get_contents($importPath);

    // Split by semicolons and execute each statement
    $statements = explode(';', $sql);
    $executedCount = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement);
            if (stripos($statement, 'INSERT INTO posts') === 0) {
                $executedCount++;
            }
        } catch (Exception $e) {
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                warn("Skipped duplicate: " . substr($statement, 0, 100) . "...");
            } else {
                throw $e;
            }
        }
    }

    $pdo->commit();

    echo "\n";
    success("Import successful!");
    echo "\n";

    // Check new count
    $stmt = Database::query("SELECT COUNT(*) as count FROM posts");
    $newCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    info("Results:");
    echo "  Posts before: {$currentCount}\n";
    echo "  Posts after: {$newCount}\n";
    echo "  Posts added: " . ($newCount - $currentCount) . "\n";
    echo "\n";

    if (isset($backupFile)) {
        info("Backup available at: {$backupFile}");
        echo "\n";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "\n";
    error("Import failed: " . $e->getMessage());
    echo "\n";

    if (isset($backupFile) && file_exists($backupFile)) {
        warn("To restore from backup:");
        echo "  mysql -u {$dbUser} -p {$dbName} < \"{$backupFile}\"\n";
        echo "  OR\n";
        echo "  php scripts/restore_database.php --file=" . basename($backupFile) . "\n";
        echo "\n";
    }

    exit(1);
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   IMPORT COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

info("Next steps:");
echo "1. Visit http://hour-timebank.ie/admin/news to verify blogs\n";
echo "2. Check that all posts are displaying correctly\n";
echo "3. Verify frontend blog pages work\n\n";
