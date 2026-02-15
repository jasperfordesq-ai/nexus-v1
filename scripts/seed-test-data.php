<?php
/**
 * Seed Test Data for Admin Panel Testing
 *
 * Creates realistic seed data in tenant_id=2 for admin panel pages.
 * Safe to re-run (uses INSERT IGNORE and existence checks).
 *
 * Usage:
 *   Via Docker mysql: docker exec -i nexus-php-db mysql -unexus -pnexus_secret nexus < scripts/seed-test-data.sql
 *   Via PHP:          docker exec nexus-php-app php scripts/seed-test-data.php
 */

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$tenantId = 2;

echo "=== Project NEXUS Seed Data ===\n";
echo "Target: tenant_id=$tenantId\n";
echo "Running SQL seed file...\n\n";

$sqlFile = __DIR__ . '/seed-test-data.sql';
if (!file_exists($sqlFile)) {
    echo "ERROR: seed-test-data.sql not found at $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);

// Split on semicolons (simple approach for our controlled SQL)
$statements = array_filter(array_map('trim', explode(';', $sql)));

$executed = 0;
$errors = 0;
foreach ($statements as $stmt) {
    if (empty($stmt) || strpos($stmt, '--') === 0) {
        continue;
    }
    try {
        Database::query($stmt);
        $executed++;
    } catch (\Exception $e) {
        // Skip SET statements and variable references that PDO can't handle
        if (strpos($stmt, 'SET @') === 0 || strpos($stmt, 'SELECT') === 0) {
            $executed++;
            continue;
        }
        echo "ERROR in statement: " . substr($stmt, 0, 80) . "...\n";
        echo "  " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "Executed $executed statements ($errors errors)\n";
echo "\nPreferred method: docker exec -i nexus-php-db mysql -unexus -pnexus_secret nexus < scripts/seed-test-data.sql\n";
