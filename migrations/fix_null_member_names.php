<?php
/**
 * Migration: Fix NULL Member Names
 *
 * This migration:
 * 1. Identifies users with NULL or empty first_name or last_name
 * 2. Reports affected users
 * 3. Optionally sets default values for NULL names
 *
 * Usage: php migrations/fix_null_member_names.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

echo "=== Fix NULL Member Names Migration ===\n\n";

try {
    // Step 1: Check for users with NULL or empty names
    echo "Step 1: Checking for users with NULL or empty names...\n";

    $sql = "
        SELECT
            id,
            tenant_id,
            first_name,
            last_name,
            email,
            organization_name,
            profile_type,
            created_at,
            is_approved
        FROM users
        WHERE
            (first_name IS NULL OR first_name = '' OR TRIM(first_name) = '')
            OR
            (last_name IS NULL OR last_name = '' OR TRIM(last_name) = '')
        ORDER BY created_at DESC
    ";

    $affectedUsers = Database::query($sql)->fetchAll();

    if (empty($affectedUsers)) {
        echo "✓ No users found with NULL or empty names. All good!\n";
        exit(0);
    }

    echo "Found " . count($affectedUsers) . " users with NULL or empty names:\n\n";

    // Display affected users
    foreach ($affectedUsers as $user) {
        echo "---\n";
        echo "ID: {$user['id']}\n";
        echo "Email: {$user['email']}\n";
        echo "First Name: " . ($user['first_name'] ?: '[EMPTY]') . "\n";
        echo "Last Name: " . ($user['last_name'] ?: '[EMPTY]') . "\n";
        echo "Profile Type: {$user['profile_type']}\n";
        echo "Organization Name: " . ($user['organization_name'] ?: '[EMPTY]') . "\n";
        echo "Created: {$user['created_at']}\n";
        echo "Approved: " . ($user['is_approved'] ? 'Yes' : 'No') . "\n";
    }

    echo "\n---\n\n";

    // Step 2: Ask for confirmation
    echo "Do you want to fix these users? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "Migration cancelled.\n";
        exit(0);
    }

    // Step 3: Fix the users
    echo "\nStep 2: Fixing users...\n";

    $fixedCount = 0;
    $skippedCount = 0;

    foreach ($affectedUsers as $user) {
        $firstName = trim($user['first_name'] ?? '');
        $lastName = trim($user['last_name'] ?? '');
        $email = $user['email'];
        $profileType = $user['profile_type'];
        $orgName = trim($user['organization_name'] ?? '');

        // Strategy 1: If it's an organization profile and has org name, we're OK
        if ($profileType === 'organisation' && !empty($orgName)) {
            echo "  → User {$user['id']} ({$email}): Organization profile with valid org name - skipping\n";
            $skippedCount++;
            continue;
        }

        // Strategy 2: Try to extract name from email if both names are missing
        $needsFix = false;
        $newFirstName = $firstName;
        $newLastName = $lastName;

        if (empty($firstName)) {
            // Try to extract from email (e.g., john.doe@example.com -> John)
            $emailParts = explode('@', $email);
            $localPart = $emailParts[0];
            $nameParts = preg_split('/[._-]/', $localPart);

            if (!empty($nameParts[0])) {
                $newFirstName = ucfirst(strtolower($nameParts[0]));
                $needsFix = true;
            } else {
                $newFirstName = 'Member';
                $needsFix = true;
            }
        }

        if (empty($lastName)) {
            // Try to extract from email (e.g., john.doe@example.com -> Doe)
            $emailParts = explode('@', $email);
            $localPart = $emailParts[0];
            $nameParts = preg_split('/[._-]/', $localPart);

            if (!empty($nameParts[1])) {
                $newLastName = ucfirst(strtolower($nameParts[1]));
                $needsFix = true;
            } else {
                // Use first 4 characters of user ID as fallback
                $newLastName = substr($user['id'], 0, 4);
                $needsFix = true;
            }
        }

        if ($needsFix) {
            // Update the user
            $updateSql = "
                UPDATE users
                SET
                    first_name = ?,
                    last_name = ?
                WHERE id = ?
            ";

            Database::query($updateSql, [$newFirstName, $newLastName, $user['id']]);

            echo "  ✓ Fixed User {$user['id']} ({$email}): {$newFirstName} {$newLastName}\n";
            $fixedCount++;
        } else {
            echo "  → User {$user['id']} ({$email}): Already has valid names - skipping\n";
            $skippedCount++;
        }
    }

    echo "\n=== Migration Complete ===\n";
    echo "Fixed: $fixedCount users\n";
    echo "Skipped: $skippedCount users\n";
    echo "\nAll member names should now display correctly on /members page.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
