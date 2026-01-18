<?php
/**
 * Add AI Welcome Message to Database
 * This script adds the default welcome message for the AI chat interface
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$db = Database::getConnection();

echo "=== Adding AI Welcome Message ===\n\n";

// Read and execute the migration
$sql = file_get_contents(__DIR__ . '/../migrations/add_ai_welcome_message.sql');

try {
    $db->exec($sql);
    echo "✅ Migration executed successfully!\n\n";

    // Verify what was added
    $stmt = $db->query("SELECT tenant_id, setting_key FROM ai_settings WHERE setting_key = 'ai_welcome_message'");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Added welcome message for " . count($results) . " tenant(s):\n";
    foreach ($results as $row) {
        echo "  - Tenant ID: {$row['tenant_id']}\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
