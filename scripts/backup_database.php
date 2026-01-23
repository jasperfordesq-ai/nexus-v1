#!/usr/bin/env php
<?php
/**
 * MANUAL DATABASE BACKUP TOOL
 * Creates a complete backup of your database on demand
 */

// Color output helpers
function success($msg) { echo "\033[32m✓ {$msg}\033[0m\n"; }
function error($msg) { echo "\033[31m✗ {$msg}\033[0m\n"; }
function warn($msg) { echo "\033[33m⚠ {$msg}\033[0m\n"; }
function info($msg) { echo "\033[36m→ {$msg}\033[0m\n"; }

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         DATABASE BACKUP TOOL                               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

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

// Parse command line arguments
$description = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--description=') === 0) {
        $description = substr($arg, 14);
    }
}

if (in_array('--help', $argv)) {
    echo "Usage: php backup_database.php [options]\n\n";
    echo "Options:\n";
    echo "  --description=\"text\"   Add a description to the backup filename\n";
    echo "  --help                 Show this help message\n\n";
    echo "Examples:\n";
    echo "  php backup_database.php\n";
    echo "  php backup_database.php --description=\"before_major_update\"\n\n";
    exit(0);
}

// Create backup directory
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
    info("Created backup directory: {$backupDir}");
}

// Generate backup filename
$timestamp = date('Y_m_d_His');
$descPart = $description ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $description) : '';
$backupFile = $backupDir . "/backup_{$timestamp}{$descPart}.sql";

info("Starting backup...");
info("Database: {$dbName}");
info("Output: " . basename($backupFile));

// Create backup using mysqldump with properly escaped credentials
$command = sprintf(
    'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    escapeshellarg($dbPass),
    escapeshellarg($dbName),
    escapeshellarg($backupFile)
);
exec($command, $output, $returnCode);

if ($returnCode === 0 && file_exists($backupFile)) {
    $sizeKB = round(filesize($backupFile) / 1024, 2);
    $sizeMB = round(filesize($backupFile) / (1024 * 1024), 2);

    echo "\n";
    success("Backup created successfully!");
    echo "\n";
    echo "  File: {$backupFile}\n";
    echo "  Size: {$sizeMB} MB ({$sizeKB} KB)\n";
    echo "  Time: " . date('F j, Y g:i A') . "\n";
    echo "\n";

    info("To restore this backup, run:");
    echo "  mysql -u {$dbUser} -p {$dbName} < \"{$backupFile}\"\n";
    echo "\n";

    // List recent backups
    $backups = glob($backupDir . '/backup_*.sql');
    rsort($backups);
    $backups = array_slice($backups, 0, 5);

    if (count($backups) > 1) {
        info("Recent backups:");
        foreach ($backups as $backup) {
            $backupSize = round(filesize($backup) / (1024 * 1024), 2);
            $backupTime = date('Y-m-d H:i:s', filemtime($backup));
            echo "  - " . basename($backup) . " ({$backupSize} MB, {$backupTime})\n";
        }
        echo "\n";
    }

} else {
    echo "\n";
    error("Backup failed!");
    echo "\n";
    if (!empty($output)) {
        error("Error output:");
        foreach ($output as $line) {
            echo "  {$line}\n";
        }
        echo "\n";
    }
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   BACKUP COMPLETE                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
