<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use Nexus\Core\Database;

echo "=== Organization 7 Status ===\n\n";

// Check org wallet
$wallet = Database::query(
    "SELECT * FROM org_wallets WHERE organization_id = 7"
)->fetch(PDO::FETCH_ASSOC);

if ($wallet) {
    echo "Wallet: EXISTS\n";
    echo "  Balance: {$wallet['balance']} credits\n";
    echo "  Created: {$wallet['created_at']}\n";
} else {
    echo "Wallet: NOT FOUND\n";
}

echo "\n";

// Check members
$members = Database::query(
    "SELECT om.*, u.email, u.first_name, u.last_name
     FROM org_members om
     JOIN users u ON om.user_id = u.id
     WHERE om.organization_id = 7"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Members: " . count($members) . "\n";
foreach ($members as $m) {
    echo "  - {$m['first_name']} {$m['last_name']} ({$m['email']}) - Role: {$m['role']}, Status: {$m['status']}\n";
}
