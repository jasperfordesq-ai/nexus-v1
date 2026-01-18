#!/usr/bin/env php
<?php
/**
 * DATABASE RESTORE TOOL
 * Safely restore a database backup with verification
 */

// Color output helpers
function success($msg) { echo "\033[32m✓ {$msg}\033[0m\n"; }
function error($msg) { echo "\033[31m✗ {$msg}\033[0m\n"; }
function warn($msg) { echo "\033[33m⚠ {$msg}\033[0m\n"; }
function info($msg) { echo "\033[36m→ {$msg}\033[0m\n"; }

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         DATABASE RESTORE TOOL                              ║\n";
echo "║         ⚠️  USE WITH EXTREME CAUTION                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Parse command line arguments
$backupFile = null;
$force = in_array('--force', $argv);

foreach ($argv as $arg) {
    if (strpos($arg, '--file=') === 0) {
        $backupFile = substr($arg, 7);
    }
}

if (in_array('--help', $argv) || !$backupFile) {
    echo "Usage: php restore_database.php --file=backup_file.sql [options]\n\n";
    echo "Options:\n";
    echo "  --file=filename    Backup file to restore (required)\n";
    echo "  --force            Skip safety confirmations (DANGEROUS)\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php restore_database.php --file=backup_2026_01_12_153045.sql\n\n";
    echo "⚠️  WARNING: This will REPLACE your current database!\n";
    echo "   Always create a backup of the current state first.\n\n";
    exit(0);
}

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!getenv($name)) {
            putenv("{$name}={$value}");
        }
    }
}

$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbHost = getenv('DB_HOST') ?: 'localhost';

if (!$dbName || !$dbUser || !$dbPass) {
    error("Database credentials not found in .env file");
    exit(1);
}

// Find backup file
$backupPath = null;
if (file_exists($backupFile)) {
    $backupPath = $backupFile;
} else {
    $possiblePaths = [
        __DIR__ . '/../backups/' . $backupFile,
        __DIR__ . '/' . $backupFile,
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $backupPath = $path;
            break;
        }
    }
}

if (!$backupPath) {
    error("Backup file not found: {$backupFile}");
    echo "\n";
    info("Looking in:");
    echo "  - " . __DIR__ . "/../backups/\n";
    echo "  - Current directory\n";
    echo "\n";
    exit(1);
}

// Check environment
$env = getenv('APP_ENV') ?: 'production';
if ($env === 'production' && !$force) {
    warn("⚠️  WARNING: You are restoring on PRODUCTION environment!");
    echo "\n";
}

// Show backup info
$backupSize = round(filesize($backupPath) / (1024 * 1024), 2);
$backupTime = date('Y-m-d H:i:s', filemtime($backupPath));

info("Backup Information:");
echo "  File: " . basename($backupPath) . "\n";
echo "  Size: {$backupSize} MB\n";
echo "  Created: {$backupTime}\n";
echo "  Target Database: {$dbName}\n";
echo "\n";

warn("⚠️  THIS WILL COMPLETELY REPLACE YOUR CURRENT DATABASE!");
echo "\n";

if (!$force) {
    echo "Do you want to create a backup of the CURRENT database before restoring? (HIGHLY RECOMMENDED)\n";
    echo "Type 'yes' to create backup first, 'no' to skip, or 'cancel' to abort: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));

    if ($line === 'cancel') {
        info("Restore cancelled by user");
        exit(0);
    }

    if ($line === 'yes') {
        info("Creating backup of current database...");

        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $currentBackup = $backupDir . "/backup_before_restore_{$timestamp}.sql";

        $command = "mysqldump -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} > \"{$currentBackup}\" 2>&1";
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($currentBackup)) {
            $currentSize = round(filesize($currentBackup) / (1024 * 1024), 2);
            success("Current database backed up: " . basename($currentBackup) . " ({$currentSize} MB)");
            echo "\n";
        } else {
            error("Failed to create backup of current database!");
            echo "\n";
            if (!$force) {
                echo "Continue without backup? (NOT RECOMMENDED) Type 'yes': ";
                $line = trim(fgets($handle));
                if ($line !== 'yes') {
                    error("Aborted - backup failed");
                    exit(1);
                }
            }
        }
    }

    echo "\n";
    warn("FINAL CONFIRMATION");
    echo "This will PERMANENTLY REPLACE all data in '{$dbName}' database.\n";
    echo "Type 'I UNDERSTAND THE RISK' to proceed: ";
    $line = trim(fgets($handle));
    if ($line !== 'I UNDERSTAND THE RISK') {
        info("Restore cancelled by user");
        exit(0);
    }
}

echo "\n";
info("Starting restore...");

// Execute restore
$command = "mysql -h {$dbHost} -u {$dbUser} -p{$dbPass} {$dbName} < \"{$backupPath}\" 2>&1";
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "\n";
    success("Database restored successfully!");
    echo "\n";
    info("Restored from: " . basename($backupPath));
    info("Database: {$dbName}");
    echo "\n";
} else {
    echo "\n";
    error("Restore failed!");
    echo "\n";
    if (!empty($output)) {
        error("Error output:");
        foreach ($output as $line) {
            echo "  {$line}\n";
        }
        echo "\n";
    }

    if (isset($currentBackup) && file_exists($currentBackup)) {
        warn("You can restore your previous state with:");
        echo "  mysql -u {$dbUser} -p {$dbName} < \"{$currentBackup}\"\n";
        echo "\n";
    }

    exit(1);
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   RESTORE COMPLETE                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
