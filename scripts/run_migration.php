<?php
/**
 * Migration Runner Script
 * Run with: php scripts/run_migration.php migrations/create_admin_actions_table.sql
 */

require_once __DIR__ . '/../bootstrap.php';

if ($argc < 2) {
    die("Usage: php scripts/run_migration.php <migration-file>\n");
}

$migrationFile = $argv[1];

if (!file_exists($migrationFile)) {
    die("Error: Migration file not found: $migrationFile\n");
}

try {
    $sql = file_get_contents($migrationFile);
    $db = \Nexus\Core\Database::getInstance();
    $db->exec($sql);
    echo "✓ Migration completed successfully: " . basename($migrationFile) . "\n";
} catch (\Exception $e) {
    die("✗ Migration failed: " . $e->getMessage() . "\n");
}
