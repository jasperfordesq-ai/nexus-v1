<?php
/**
 * Create Sessions Table Migration
 *
 * Run this script to create the sessions table for tracking active users
 * and providing real-time analytics in the Enterprise dashboard.
 *
 * Usage:
 *   php scripts/migrations/create_sessions_table.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Nexus\Core\Database;

echo "=== Sessions Table Migration ===\n\n";

try {
    $db = Database::getInstance();

    // Check if table already exists
    $tableExists = $db->query("SHOW TABLES LIKE 'sessions'")->fetch();

    if ($tableExists) {
        echo "✓ Sessions table already exists.\n";
        echo "  If you want to recreate it, please drop it first.\n\n";

        // Show table info
        echo "Current table structure:\n";
        $columns = $db->query("DESCRIBE sessions")->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        exit(0);
    }

    echo "Creating sessions table...\n";

    // Read SQL file
    $sqlFile = __DIR__ . '/../../migrations/create_sessions_table.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Execute the SQL
    $db->exec($sql);

    echo "✓ Sessions table created successfully!\n\n";

    // Verify table was created
    $tableExists = $db->query("SHOW TABLES LIKE 'sessions'")->fetch();
    if (!$tableExists) {
        throw new Exception("Table creation verification failed");
    }

    // Show table structure
    echo "Table structure:\n";
    $columns = $db->query("DESCRIBE sessions")->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n✓ Migration completed successfully!\n";
    echo "\nThe Enterprise dashboard real-time features will now work correctly.\n";

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
