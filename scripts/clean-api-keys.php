<?php
/**
 * Clean API Keys - Remove whitespace from stored keys
 * Run this once to fix keys that were saved with whitespace
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Models\AiSettings;

echo "🧹 Cleaning API Keys...\n\n";

$db = Database::getConnection();

// Get all tenants
$stmt = $db->query("SELECT DISTINCT tenant_id FROM ai_settings WHERE setting_key LIKE '%_api_key'");
$tenants = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tenants as $tenantId) {
    echo "Tenant ID: $tenantId\n";

    // Get all API keys for this tenant
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM ai_settings WHERE tenant_id = ? AND setting_key LIKE '%_api_key'");
    $stmt->execute([$tenantId]);
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($keys as $key) {
        $keyName = $key['setting_key'];
        $original = $key['setting_value'];
        $cleaned = trim($original);
        $originalLen = strlen($original);
        $cleanedLen = strlen($cleaned);

        if ($original !== $cleaned) {
            echo "  ⚠️  $keyName: Had whitespace! (was $originalLen chars, now $cleanedLen chars)\n";

            // Update the key
            $updateStmt = $db->prepare("UPDATE ai_settings SET setting_value = ? WHERE tenant_id = ? AND setting_key = ?");
            $updateStmt->execute([$cleaned, $tenantId, $keyName]);

            echo "  ✅ Cleaned $keyName\n";
        } else {
            echo "  ✓ $keyName: OK ($cleanedLen chars)\n";
        }
    }

    echo "\n";
}

echo "✅ Done! All API keys cleaned.\n";
echo "\n🔄 Now reload the AI Settings page and test the connection again.\n";
