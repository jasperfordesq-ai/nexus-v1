<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use Nexus\Core\Database;

try {
    $db = Database::getInstance();

    echo "Checking users table structure...\n\n";

    $result = $db->query('DESCRIBE users');
    $columns = $result->fetchAll();

    echo "Users table columns:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-30s %-10s %-10s\n", "Field", "Type", "Key", "Null");
    echo str_repeat("-", 80) . "\n";

    foreach ($columns as $col) {
        printf("%-20s %-30s %-10s %-10s\n",
            $col['Field'],
            $col['Type'],
            $col['Key'],
            $col['Null']
        );
    }

    echo "\n";

    // Check if id is the primary key
    $result = $db->query("SHOW KEYS FROM users WHERE Key_name = 'PRIMARY'");
    $primaryKey = $result->fetch();

    if ($primaryKey) {
        echo "✅ Primary key: " . $primaryKey['Column_name'] . "\n";
    } else {
        echo "❌ No primary key found!\n";
    }

    // Check table engine
    $result = $db->query("SHOW TABLE STATUS LIKE 'users'");
    $status = $result->fetch();

    echo "Table engine: " . $status['Engine'] . "\n";
    echo "Collation: " . $status['Collation'] . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
