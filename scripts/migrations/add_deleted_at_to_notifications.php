<?php
/**
 * Add deleted_at column to notifications table
 * This enables soft delete functionality for notifications
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

echo "=== Add deleted_at Column to Notifications Table ===\n\n";

try {
    $db = Database::getInstance();

    // Check if column already exists
    $result = $db->query("SHOW COLUMNS FROM notifications LIKE 'deleted_at'");
    $columnExists = $result->fetch();

    if ($columnExists) {
        echo "✓ Column 'deleted_at' already exists in notifications table\n\n";

        // Show current table structure
        echo "Current table structure:\n";
        $columns = $db->query("DESCRIBE notifications")->fetchAll();
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
        exit(0);
    }

    echo "Adding 'deleted_at' column to notifications table...\n";

    // Add the column
    $sql = "ALTER TABLE notifications
            ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
            AFTER is_read";

    $db->exec($sql);
    echo "✓ Successfully added 'deleted_at' column to notifications table\n";

    // Add index for better query performance
    $indexSql = "ALTER TABLE notifications
                 ADD INDEX idx_deleted_at (deleted_at)";

    try {
        $db->exec($indexSql);
        echo "✓ Successfully added index on 'deleted_at' column\n";
    } catch (Exception $e) {
        echo "⚠ Warning: Could not add index (might already exist): " . $e->getMessage() . "\n";
    }

    echo "\n";
    echo "✓ Migration completed successfully!\n";
    echo "\nThe notifications table now supports soft deletes.\n";
    echo "Updated queries will use 'WHERE deleted_at IS NULL' to filter out soft-deleted records.\n";

    // Show updated table structure
    echo "\nUpdated table structure:\n";
    $columns = $db->query("DESCRIBE notifications")->fetchAll();
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
