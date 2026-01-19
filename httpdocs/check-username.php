<?php
/**
 * Simple Username Diagnostic
 */

// Load the application bootstrap
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Username Check</title>";
echo "<style>body{font-family:monospace;padding:20px;max-width:800px;margin:0 auto;background:#fff;}pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow-x:auto;border:1px solid #ddd;}h1{color:#333;}.error{color:#d9534f;font-weight:bold;}.warning{color:#f0ad4e;font-weight:bold;}.success{color:#5cb85c;font-weight:bold;}</style></head><body>";

echo "<h1>Username Diagnostic Tool</h1>";
echo "<pre>";

echo "=== SESSION DATA ===\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "User Name: '" . ($_SESSION['user_name'] ?? 'NOT SET') . "'\n";
echo "User Email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "\n";
echo "User Role: " . ($_SESSION['user_role'] ?? 'NOT SET') . "\n";
echo "Tenant ID: " . ($_SESSION['tenant_id'] ?? 'NOT SET') . "\n";
echo "\n";

// If logged in, fetch user data from database
if (!empty($_SESSION['user_id'])) {
    try {
        $userId = (int)$_SESSION['user_id'];

        // Use the Database class from the framework
        $stmt = \Nexus\Core\Database::query(
            "SELECT id, first_name, last_name, email, profile_type, organization_name, role, avatar_url, tenant_id FROM users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        if ($user) {
            echo "=== DATABASE USER RECORD ===\n";
            echo "ID: " . $user['id'] . "\n";
            echo "First Name: '" . ($user['first_name'] ?? 'NULL') . "'\n";
            echo "Last Name: '" . ($user['last_name'] ?? 'NULL') . "'\n";
            echo "Email: " . $user['email'] . "\n";
            echo "Profile Type: " . ($user['profile_type'] ?? 'individual') . "\n";
            echo "Organization Name: '" . ($user['organization_name'] ?? 'NULL') . "'\n";
            echo "Role: " . ($user['role'] ?? 'member') . "\n";
            echo "Tenant ID: " . $user['tenant_id'] . "\n";
            echo "\n";

            echo "=== COMPUTED VALUES ===\n";
            $firstName = $user['first_name'] ?? '';
            $lastName = $user['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            echo "Full Name (first + last): '" . $fullName . "'\n";
            echo "Length: " . strlen($fullName) . " characters\n\n";

            // Organization name logic
            if ($user['profile_type'] === 'organisation' && !empty($user['organization_name'])) {
                echo "Display Name (with org logic): '" . $user['organization_name'] . "'\n\n";
            } else {
                echo "Display Name (standard): '" . $fullName . "'\n\n";
            }

            echo "=== DIAGNOSIS ===\n";
            if (empty($firstName) && empty($lastName)) {
                echo "<span class='error'>❌ PROBLEM FOUND: Both first_name and last_name are EMPTY!</span>\n";
                echo "   This is why you see 'User' or 'Guest' in the mobile app.\n\n";
                echo "   <strong>Solution: Update your profile at /settings</strong>\n";
            } elseif (empty($firstName)) {
                echo "<span class='warning'>⚠️  WARNING: first_name is empty</span>\n";
            } elseif (empty($lastName)) {
                echo "<span class='warning'>⚠️  WARNING: last_name is empty</span>\n";
            } else {
                echo "<span class='success'>✓ first_name and last_name are populated in DB</span>\n";
            }

            // Check session vs database
            $sessionName = $_SESSION['user_name'] ?? '';

            if ($fullName !== $sessionName) {
                echo "<span class='warning'>⚠️  SESSION MISMATCH!</span>\n";
                echo "   Session name: '" . $sessionName . "'\n";
                echo "   Database name: '" . $fullName . "'\n\n";
                echo "   <strong>Solution: Log out and log back in to refresh session</strong>\n";
            } else {
                if (!empty($fullName)) {
                    echo "<span class='success'>✓ Session matches database</span>\n";
                }
            }

        } else {
            echo "<span class='error'>ERROR: User not found in database!</span>\n";
        }
    } catch (Exception $e) {
        echo "<span class='error'>ERROR: " . htmlspecialchars($e->getMessage()) . "</span>\n";
        echo "Stack trace:\n" . htmlspecialchars($e->getTraceAsString()) . "\n";
    }
} else {
    echo "<span class='warning'>NOT LOGGED IN - Please log in first</span>\n";
}

echo "</pre>";

echo "<p><a href='/' style='display:inline-block;padding:10px 20px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>&larr; Back to site</a></p>";
echo "</body></html>";
