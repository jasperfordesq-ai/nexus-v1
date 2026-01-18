<?php
/**
 * Migration: Organization Wallets, Admin Analytics, and User Insights
 *
 * Run this script to create the required database tables:
 *   php scripts/migrations/run_org_wallets_migration.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

use Nexus\Core\Database;

echo "Running Organization Wallets & Analytics Migration...\n\n";

try {
    $pdo = Database::getInstance();

    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/ORG_WALLETS_ANALYTICS.sql');

    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        // Extract table name for display
        if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
            echo "Creating table: {$matches[1]}... ";
        }

        $pdo->exec($statement);
        echo "OK\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
