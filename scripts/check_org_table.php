<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use Nexus\Core\Database;

echo "vol_organizations table structure:\n";
$cols = Database::query('DESCRIBE vol_organizations')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "  {$col['Field']} - {$col['Type']}\n";
}

echo "\nSample data from vol_organizations:\n";
$orgs = Database::query('SELECT * FROM vol_organizations LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
foreach ($orgs as $org) {
    echo "  ID: {$org['id']} | Name: {$org['name']} | Email: {$org['contact_email']}\n";
    echo "    Columns: " . implode(', ', array_keys($org)) . "\n";
}
