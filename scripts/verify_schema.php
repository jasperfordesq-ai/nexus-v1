<?php
/**
 * Database Schema Verification Script
 * Checks that all required tables and columns exist for org wallets feature
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load env
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

TenantContext::setById(1);

echo "=== DATABASE SCHEMA VERIFICATION ===" . PHP_EOL . PHP_EOL;

// Tables to check
$requiredTables = [
    'org_wallets',
    'org_members',
    'org_transfer_requests',
    'org_transactions',
    'abuse_alerts'
];

$missingTables = [];
$existingTables = [];

echo "--- CHECKING REQUIRED TABLES ---" . PHP_EOL;
foreach ($requiredTables as $table) {
    try {
        $exists = Database::query("SHOW TABLES LIKE '$table'")->fetch();
        if ($exists) {
            $existingTables[] = $table;
            echo "[EXISTS] $table" . PHP_EOL;
        } else {
            $missingTables[] = $table;
            echo "[MISSING] $table" . PHP_EOL;
        }
    } catch (Exception $e) {
        $missingTables[] = $table;
        echo "[ERROR] $table - " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "--- CHECKING users.notification_preferences COLUMN ---" . PHP_EOL;
try {
    $col = Database::query("SHOW COLUMNS FROM users LIKE 'notification_preferences'")->fetch();
    if ($col) {
        echo "[EXISTS] users.notification_preferences ({$col['Type']})" . PHP_EOL;
    } else {
        echo "[MISSING] users.notification_preferences" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . PHP_EOL;
}

// Show structure of existing tables
foreach ($existingTables as $table) {
    echo PHP_EOL . "--- " . strtoupper($table) . " TABLE STRUCTURE ---" . PHP_EOL;
    try {
        $cols = Database::query("DESCRIBE $table")->fetchAll();
        foreach ($cols as $c) {
            echo "  - {$c['Field']} ({$c['Type']})" . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== SUMMARY ===" . PHP_EOL;
echo "Tables found: " . count($existingTables) . "/" . count($requiredTables) . PHP_EOL;
if (!empty($missingTables)) {
    echo "Missing tables: " . implode(', ', $missingTables) . PHP_EOL;
    echo PHP_EOL . "To create missing tables, run the migration:" . PHP_EOL;
    echo "  scripts/migrations/ORG_WALLETS_ANALYTICS.sql" . PHP_EOL;
}

echo PHP_EOL . "=== VERIFICATION COMPLETE ===" . PHP_EOL;
