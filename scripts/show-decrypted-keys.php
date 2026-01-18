<?php
require_once __DIR__ . '/../bootstrap.php';

use Nexus\Models\AiSettings;

$tenantId = 2;

echo "=== Decrypted API Keys (Tenant $tenantId) ===\n\n";

$settings = AiSettings::getAllForTenant($tenantId);

$keyNames = ['gemini_api_key', 'openai_api_key', 'anthropic_api_key'];

foreach ($keyNames as $keyName) {
    if (isset($settings[$keyName]) && !empty($settings[$keyName])) {
        $key = $settings[$keyName];
        $len = strlen($key);
        $first15 = substr($key, 0, 15);
        $last10 = substr($key, -10);

        echo "Key: $keyName\n";
        echo "  Length: $len chars\n";
        echo "  Preview: $first15...$last10\n";

        // Identify format
        if (str_starts_with($key, 'AIza')) {
            echo "  Format: ✅ Google Gemini\n";
        } elseif (str_starts_with($key, 'sk-proj-')) {
            echo "  Format: ⚠️  OpenAI (Project Key)\n";
        } elseif (str_starts_with($key, 'sk-ant-api')) {
            echo "  Format: ✅ Anthropic Claude (Modern)\n";
        } elseif (str_starts_with($key, 'sk-ant-')) {
            echo "  Format: ✅ Anthropic Claude (Legacy)\n";
        } elseif (str_starts_with($key, 'sk-')) {
            echo "  Format: ⚠️  OpenAI (Legacy Key)\n";
        } else {
            echo "  Format: ❓ Unknown\n";
        }

        echo "\n";
    } else {
        echo "Key: $keyName\n";
        echo "  Status: NOT SET\n\n";
    }
}

echo "=== DIAGNOSIS ===\n\n";

if (isset($settings['anthropic_api_key']) && !empty($settings['anthropic_api_key'])) {
    $anthropicKey = $settings['anthropic_api_key'];

    if (str_starts_with($anthropicKey, 'sk-ant-')) {
        echo "✅ Anthropic key format is CORRECT!\n";
        echo "   If testing still fails, the key may be:\n";
        echo "   1. Expired or revoked\n";
        echo "   2. From a different Anthropic account\n";
        echo "   3. Has usage limits that are exceeded\n\n";
        echo "   Try generating a NEW key at: https://console.anthropic.com/settings/keys\n";
    } else {
        echo "❌ Anthropic key format is WRONG!\n";
        echo "   Current format: " . substr($anthropicKey, 0, 20) . "...\n";
        echo "   Expected format: sk-ant-api03-...\n";
    }
}
