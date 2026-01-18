<?php
/**
 * Identify API Key Types
 * Helps determine which keys belong to which provider
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Models\AiSettings;

$tenantId = 2;
$settings = AiSettings::getAllForTenant($tenantId);

echo "=== API Key Type Identification ===\n\n";

$keyFormats = [
    'gemini_api_key' => [
        'expected_prefix' => 'AIza',
        'expected_length' => '39',
        'provider' => 'Google Gemini',
    ],
    'openai_api_key' => [
        'expected_prefix' => 'sk-proj-', // New OpenAI format
        'expected_prefix_alt' => 'sk-', // Old OpenAI format
        'expected_length' => '~164 or ~51',
        'provider' => 'OpenAI',
    ],
    'anthropic_api_key' => [
        'expected_prefix' => 'sk-ant-api03-',
        'expected_prefix_alt' => 'sk-ant-',
        'expected_length' => '~108',
        'provider' => 'Anthropic Claude',
    ],
];

foreach ($keyFormats as $keyName => $format) {
    echo "📋 $keyName:\n";

    if (isset($settings[$keyName]) && !empty($settings[$keyName])) {
        $key = $settings[$keyName];
        $len = strlen($key);
        $prefix = substr($key, 0, 15);

        echo "   Length: $len chars\n";
        echo "   Prefix: $prefix...\n";

        // Check if it matches expected format
        $matches = false;
        if (str_starts_with($key, $format['expected_prefix'])) {
            $matches = true;
        } elseif (isset($format['expected_prefix_alt']) && str_starts_with($key, $format['expected_prefix_alt'])) {
            $matches = true;
        }

        if ($matches) {
            echo "   Status: ✅ CORRECT FORMAT for {$format['provider']}\n";
        } else {
            echo "   Status: ❌ WRONG FORMAT!\n";
            echo "   Expected: {$format['expected_prefix']} ({$format['expected_length']} chars)\n";
            echo "   Provider: {$format['provider']}\n";

            // Try to identify what it actually is
            if (str_starts_with($key, 'AIza')) {
                echo "   ⚠️  This looks like a GEMINI key!\n";
            } elseif (str_starts_with($key, 'sk-proj-')) {
                echo "   ⚠️  This looks like an OPENAI PROJECT key!\n";
            } elseif (str_starts_with($key, 'sk-ant-')) {
                echo "   ⚠️  This looks like an ANTHROPIC key!\n";
            } elseif (str_starts_with($key, 'sk-')) {
                echo "   ⚠️  This looks like an OPENAI (old format) key!\n";
            }
        }
    } else {
        echo "   Status: ⚪ NOT SET\n";
    }

    echo "\n";
}

echo "=== RECOMMENDATIONS ===\n\n";

// Check for mismatched keys
if (isset($settings['anthropic_api_key']) && str_starts_with($settings['anthropic_api_key'], 'sk-proj-')) {
    echo "❌ ISSUE FOUND: Your Anthropic field contains an OpenAI key!\n";
    echo "   Action: Move this key to the OpenAI field, then get a real Anthropic key.\n";
    echo "   Get Anthropic key: https://console.anthropic.com/settings/keys\n\n";
}

if (isset($settings['openai_api_key']) && str_starts_with($settings['openai_api_key'], 'sk-ant-')) {
    echo "❌ ISSUE FOUND: Your OpenAI field contains an Anthropic key!\n";
    echo "   Action: Move this key to the Anthropic field, then get a real OpenAI key.\n";
    echo "   Get OpenAI key: https://platform.openai.com/api-keys\n\n";
}

echo "✅ Run this script after updating keys to verify they're correct.\n";
