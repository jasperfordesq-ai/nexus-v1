<?php
/**
 * Generate a test JWT token for API testing
 * Usage: php scripts/generate-test-token.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Services\TokenService;
use Nexus\Core\Database;

// Get an admin user
$stmt = Database::query(
    "SELECT id, email, role, tenant_id FROM users WHERE role IN ('admin', 'tenant_admin') LIMIT 1"
);
$user = $stmt->fetch();

if (!$user) {
    echo "ERROR: No admin users found in database\n";
    exit(1);
}

// Generate token
$token = TokenService::generateAccessToken((int)$user['id'], (int)$user['tenant_id'], $user['role']);

echo "=" . str_repeat("=", 70) . "\n";
echo "TEST TOKEN GENERATED\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "User ID: {$user['id']}\n";
echo "Email: {$user['email']}\n";
echo "Role: {$user['role']}\n";
echo "Tenant ID: {$user['tenant_id']}\n";
echo "\n";
echo "Token:\n";
echo $token . "\n";
echo "\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "Export commands:\n";
echo "  export TOKEN=\"{$token}\"\n";
echo "  export TENANT_ID=\"{$user['tenant_id']}\"\n";
echo "=" . str_repeat("=", 70) . "\n";
