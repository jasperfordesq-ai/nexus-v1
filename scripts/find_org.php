<?php
require __DIR__ . '/../vendor/autoload.php';

// Load env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr(trim($line), 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

$stmt = Nexus\Core\Database::query(
    "SELECT id, name, contact_email FROM vol_organizations WHERE contact_email LIKE ? LIMIT 5",
    ['%jasper%']
);
$orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($orgs) {
    foreach ($orgs as $org) {
        echo "ID: {$org['id']} | Name: {$org['name']} | Email: {$org['contact_email']}\n";
    }
} else {
    echo "No organizations found with 'jasper' in email.\n";
    $stmt = Nexus\Core\Database::query("SELECT id, name, contact_email FROM vol_organizations LIMIT 10");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nAll organizations:\n";
    foreach ($all as $org) {
        echo "ID: {$org['id']} | Name: {$org['name']} | Email: {$org['contact_email']}\n";
    }
}
