<?php
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;

$db = Database::getConnection();
$tenantId = 2;

echo "=== All API Keys in Database (Tenant $tenantId) ===\n\n";

$stmt = $db->prepare("SELECT setting_key, setting_value FROM ai_settings WHERE tenant_id = ? AND setting_key LIKE '%_api_key' ORDER BY setting_key");
$stmt->execute([$tenantId]);
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($keys as $key) {
    $keyName = $key['setting_key'];
    $value = $key['setting_value'];
    $len = strlen($value);
    $preview = substr($value, 0, 20) . '...' . substr($value, -10);

    echo "Key: $keyName\n";
    echo "  Length: $len chars\n";
    echo "  Preview: $preview\n";
    echo "  Starts with: " . substr($value, 0, 15) . "\n";
    echo "\n";
}
