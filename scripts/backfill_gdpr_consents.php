<?php
/**
 * Backfill GDPR Consents for Existing Users
 *
 * This script creates consent records for all existing users who have
 * already agreed to terms during registration (before GDPR integration).
 *
 * Usage: php scripts/backfill_gdpr_consents.php
 */

require_once __DIR__ . '/../httpdocs/bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

echo "=== GDPR Consent Backfill Script ===\n\n";

// Get all tenants
$tenants = Database::query("SELECT id, name FROM tenants WHERE is_active = 1")->fetchAll();

$totalUsers = 0;
$totalConsents = 0;

foreach ($tenants as $tenant) {
    $tenantId = $tenant['id'];
    $tenantName = $tenant['name'];

    echo "Processing Tenant: {$tenantName} (ID: {$tenantId})\n";
    echo str_repeat("-", 50) . "\n";

    // Get all approved users for this tenant who don't have consent records yet
    $users = Database::query(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.created_at
         FROM users u
         WHERE u.tenant_id = ?
         AND (u.is_approved = 1 OR u.is_approved IS NULL)
         AND NOT EXISTS (
             SELECT 1 FROM user_consents uc
             WHERE uc.user_id = u.id
             AND uc.tenant_id = u.tenant_id
             AND uc.consent_type = 'terms_of_service'
         )",
        [$tenantId]
    )->fetchAll();

    $userCount = count($users);
    echo "Found {$userCount} users without consent records\n";

    if ($userCount === 0) {
        echo "Skipping - no users to process\n\n";
        continue;
    }

    $consentText = "I have read and agree to the Terms of Service and Privacy Policy. I understand that as a member, I will be automatically subscribed to the community newsletter.";
    $consentVersion = '1.0';
    $consentHash = hash('sha256', $consentText);

    $consentsCreated = 0;

    foreach ($users as $user) {
        $userId = $user['id'];
        $createdAt = $user['created_at'];

        // Create consent records for: terms_of_service, privacy_policy, marketing_email
        $consentTypes = ['terms_of_service', 'privacy_policy', 'marketing_email'];

        foreach ($consentTypes as $consentType) {
            // Check if this specific consent already exists
            $existing = Database::query(
                "SELECT id FROM user_consents WHERE user_id = ? AND tenant_id = ? AND consent_type = ?",
                [$userId, $tenantId, $consentType]
            )->fetch();

            if ($existing) {
                continue; // Skip if already exists
            }

            // Insert consent record
            Database::query(
                "INSERT INTO user_consents
                 (user_id, tenant_id, consent_type, consent_given, consent_text, consent_version,
                  consent_hash, ip_address, user_agent, source, given_at, created_at)
                 VALUES (?, ?, ?, 1, ?, ?, ?, '127.0.0.1', 'Backfill Script', 'backfill', ?, NOW())",
                [
                    $userId,
                    $tenantId,
                    $consentType,
                    $consentText,
                    $consentVersion,
                    $consentHash,
                    $createdAt // Use original registration date
                ]
            );

            $consentsCreated++;
        }

        // Also ensure user is in newsletter_subscribers if not already
        $existingSub = Database::query(
            "SELECT id FROM newsletter_subscribers WHERE tenant_id = ? AND email = ?",
            [$tenantId, strtolower(trim($user['email']))]
        )->fetch();

        if (!$existingSub) {
            $unsubscribeToken = bin2hex(random_bytes(32));
            Database::query(
                "INSERT INTO newsletter_subscribers
                 (tenant_id, email, first_name, last_name, user_id, source, unsubscribe_token, status, confirmed_at, created_at)
                 VALUES (?, ?, ?, ?, ?, 'backfill', ?, 'active', ?, NOW())",
                [
                    $tenantId,
                    strtolower(trim($user['email'])),
                    $user['first_name'],
                    $user['last_name'],
                    $userId,
                    $unsubscribeToken,
                    $createdAt
                ]
            );
        }
    }

    $totalUsers += $userCount;
    $totalConsents += $consentsCreated;

    echo "Created {$consentsCreated} consent records for {$userCount} users\n\n";
}

echo "=== COMPLETE ===\n";
echo "Total users processed: {$totalUsers}\n";
echo "Total consent records created: {$totalConsents}\n";
