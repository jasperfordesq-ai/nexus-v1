<?php
/**
 * Setup 404 Error Tracking System
 * Run this script to create the database table and add initial redirects
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Models\SeoRedirect;

echo "=== Setting Up 404 Error Tracking System ===\n\n";

$db = Database::getInstance();

// Create the 404 error tracking table
echo "1. Creating error_404_log table...\n";
try {
    $sql = file_get_contents(__DIR__ . '/../migrations/2026_01_11_create_404_tracking_table.sql');

    // Split by semicolon to execute multiple statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }

    echo "   ✓ Table created successfully\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "   ✓ Table already exists\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Create redirects for known broken URLs
echo "\n2. Creating redirects for known 404 errors...\n";

$redirects = [
    // Singular to plural listings
    [
        'source' => '/listing/chess-play/',
        'destination' => '/listings',
        'note' => 'Old singular listing URL format'
    ],
    // Help volunteering guide
    [
        'source' => '/help/volunteering-guide',
        'destination' => '/help/volunteering-overview',
        'note' => 'Redirect to actual volunteering help page'
    ],
    // Forum to discussions
    [
        'source' => '/groups/gardening/forum/gardening/',
        'destination' => '/groups',
        'note' => 'Old forum URL structure'
    ],
    // Generic forum pattern redirects
    [
        'source' => '/forum',
        'destination' => '/groups',
        'note' => 'Redirect old forum to groups'
    ]
];

foreach ($redirects as $redirect) {
    try {
        $redirectId = SeoRedirect::create($redirect['source'], $redirect['destination']);
        if ($redirectId) {
            echo "   ✓ Created redirect: {$redirect['source']} → {$redirect['destination']}\n";
        } else {
            echo "   ✓ Redirect already exists: {$redirect['source']}\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ Failed to create redirect for {$redirect['source']}: " . $e->getMessage() . "\n";
    }
}

echo "\n3. Verifying table structure...\n";
try {
    $stmt = $db->query("DESCRIBE error_404_log");
    $columns = $stmt->fetchAll();
    echo "   ✓ Table has " . count($columns) . " columns\n";

    foreach ($columns as $column) {
        echo "      - {$column['Field']} ({$column['Type']})\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error verifying table: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "\nNext steps:\n";
echo "1. Visit /admin/404-errors to view the 404 tracking dashboard\n";
echo "2. Monitor broken links and create additional redirects as needed\n";
echo "3. The system will automatically log new 404 errors as they occur\n";
