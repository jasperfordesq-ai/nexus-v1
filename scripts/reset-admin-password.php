<?php
// Reset admin password for testing
require_once __DIR__ . '/../vendor/autoload.php';

use Nexus\Core\Database;

Database::connect();

$userId = 14; // jasper@hour-timebank.ie
$password = 'AdminTest123!';
$hash = password_hash($password, PASSWORD_BCRYPT);

Database::query(
    "UPDATE users SET password_hash = ? WHERE id = ?",
    [$hash, $userId]
);

echo "Password reset for user ID $userId to: $password\n";
