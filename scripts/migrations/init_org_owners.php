<?php
/**
 * Migration: Initialize org_members for existing organizations
 *
 * This script adds organization owners to the org_members table
 * for organizations that existed before the org wallet feature.
 *
 * Run: php scripts/migrations/init_org_owners.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

use Nexus\Core\Database;

echo "=== Initialize Organization Owners Migration ===\n\n";

// Step 1: Find all organizations and their owners not yet in org_members
echo "Step 1: Finding organizations without org_members entries...\n";

$orgs = Database::query(
    "SELECT vo.id, vo.name, vo.user_id as owner_id, vo.tenant_id, u.email as owner_email
     FROM vol_organizations vo
     JOIN users u ON vo.user_id = u.id
     WHERE NOT EXISTS (
         SELECT 1 FROM org_members om
         WHERE om.organization_id = vo.id AND om.user_id = vo.user_id
     )"
)->fetchAll(PDO::FETCH_ASSOC);

if (empty($orgs)) {
    echo "No organizations need migration - all owners already in org_members.\n";
    exit(0);
}

echo "Found " . count($orgs) . " organization(s) to migrate:\n\n";

foreach ($orgs as $org) {
    echo "  - [{$org['id']}] {$org['name']} (Owner: {$org['owner_email']})\n";
}

echo "\nStep 2: Adding owners to org_members table...\n";

$migrated = 0;
$errors = 0;

foreach ($orgs as $org) {
    try {
        Database::query(
            "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
             VALUES (?, ?, ?, 'owner', 'active', NOW())
             ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'",
            [$org['tenant_id'], $org['id'], $org['owner_id']]
        );

        echo "  + Added owner for: {$org['name']}\n";
        $migrated++;
    } catch (Exception $e) {
        echo "  ! Error for {$org['name']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// Step 3: Create org_wallets for organizations that don't have one
echo "\nStep 3: Creating org_wallets for organizations without wallets...\n";

$orgsWithoutWallets = Database::query(
    "SELECT vo.id, vo.name, vo.tenant_id
     FROM vol_organizations vo
     WHERE NOT EXISTS (
         SELECT 1 FROM org_wallets ow WHERE ow.organization_id = vo.id
     )"
)->fetchAll(PDO::FETCH_ASSOC);

$walletsCreated = 0;
foreach ($orgsWithoutWallets as $org) {
    try {
        Database::query(
            "INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
             VALUES (?, ?, 0.00, NOW())
             ON DUPLICATE KEY UPDATE tenant_id = tenant_id",
            [$org['tenant_id'], $org['id']]
        );
        echo "  + Created wallet for: {$org['name']}\n";
        $walletsCreated++;
    } catch (Exception $e) {
        echo "  ! Error creating wallet for {$org['name']}: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migration Complete ===\n";
echo "Owners migrated: $migrated\n";
echo "Wallets created: $walletsCreated\n";
echo "Errors: $errors\n";
