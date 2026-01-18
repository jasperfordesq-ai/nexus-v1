<?php
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Models\AiSettings;

$tenantId = 2;
$settings = AiSettings::getAllForTenant($tenantId);

echo "=== API Key Diagnostic ===\n\n";

$keys = ['gemini_api_key', 'anthropic_api_key', 'openai_api_key'];

foreach ($keys as $keyName) {
    if (isset($settings[$keyName]) && !empty($settings[$keyName])) {
        $key = $settings[$keyName];
        $len = strlen($key);
        $first20 = substr($key, 0, 20);
        $last10 = substr($key, -10);

        echo "$keyName:\n";
        echo "  Length: $len chars\n";
        echo "  First 20: $first20\n";
        echo "  Last 10: $last10\n";
        echo "  Starts with 'sk-' or 'AIza': " . (preg_match('/^(sk-|AIza)/', $key) ? 'YES ✓' : 'NO ✗') . "\n";
        echo "\n";
    } else {
        echo "$keyName: NOT SET\n\n";
    }
}
