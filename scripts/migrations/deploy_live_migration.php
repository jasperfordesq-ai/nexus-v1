<?php
/**
 * LIVE SERVER MIGRATION SCRIPT
 * Organization Wallets & Analytics
 * Date: 2026-01-07
 *
 * USAGE:
 *   php scripts/migrations/deploy_live_migration.php
 *
 * FEATURES ADDED:
 *   - Organization wallets (separate from owner's personal balance)
 *   - Organization members with roles (owner, admin, member)
 *   - Transfer request workflow with admin approval
 *   - Transaction audit log
 *   - Abuse detection alerts
 *
 * This script is SAFE to run multiple times (uses IF NOT EXISTS and INSERT IGNORE patterns)
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

use Nexus\Core\Database;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║     LIVE SERVER MIGRATION - Organization Wallets            ║\n";
echo "║                    2026-01-07                                ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = Database::getInstance();

    // =========================================================================
    // STEP 1: Create Tables
    // =========================================================================
    echo "STEP 1: Creating database tables...\n";
    echo str_repeat("-", 50) . "\n";

    $tables = [
        'org_wallets' => "
            CREATE TABLE IF NOT EXISTS org_wallets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                organization_id INT NOT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_org_wallet (tenant_id, organization_id),
                INDEX idx_org_wallet_tenant (tenant_id),
                INDEX idx_org_wallet_org (organization_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'org_members' => "
            CREATE TABLE IF NOT EXISTS org_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                organization_id INT NOT NULL,
                user_id INT NOT NULL,
                role ENUM('owner', 'admin', 'member') DEFAULT 'member',
                status ENUM('active', 'pending', 'invited', 'removed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_org_member (organization_id, user_id),
                INDEX idx_org_members_tenant (tenant_id),
                INDEX idx_org_members_user (user_id),
                INDEX idx_org_members_org (organization_id),
                INDEX idx_org_members_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'org_transfer_requests' => "
            CREATE TABLE IF NOT EXISTS org_transfer_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                organization_id INT NOT NULL,
                requester_id INT NOT NULL,
                recipient_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT,
                status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
                approved_by INT NULL,
                approved_at TIMESTAMP NULL,
                rejection_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_transfer_tenant (tenant_id),
                INDEX idx_transfer_org (organization_id),
                INDEX idx_transfer_status (status),
                INDEX idx_transfer_requester (requester_id),
                INDEX idx_transfer_recipient (recipient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'org_transactions' => "
            CREATE TABLE IF NOT EXISTS org_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                organization_id INT NOT NULL,
                transfer_request_id INT NULL,
                sender_type ENUM('organization', 'user') NOT NULL,
                sender_id INT NOT NULL,
                receiver_type ENUM('organization', 'user') NOT NULL,
                receiver_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_org_trx_tenant (tenant_id),
                INDEX idx_org_trx_org (organization_id),
                INDEX idx_org_trx_date (created_at),
                INDEX idx_org_trx_sender (sender_type, sender_id),
                INDEX idx_org_trx_receiver (receiver_type, receiver_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'abuse_alerts' => "
            CREATE TABLE IF NOT EXISTS abuse_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                alert_type VARCHAR(50) NOT NULL,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                user_id INT NULL,
                transaction_id INT NULL,
                details JSON,
                status ENUM('new', 'reviewing', 'resolved', 'dismissed') DEFAULT 'new',
                resolved_by INT NULL,
                resolved_at TIMESTAMP NULL,
                resolution_notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_abuse_tenant (tenant_id),
                INDEX idx_abuse_status (status),
                INDEX idx_abuse_severity (severity),
                INDEX idx_abuse_user (user_id),
                INDEX idx_abuse_type (alert_type),
                INDEX idx_abuse_date (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    foreach ($tables as $name => $sql) {
        echo "  Creating table: $name... ";
        try {
            $pdo->exec($sql);
            echo "OK\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "SKIPPED (already exists)\n";
            } else {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
    }

    // =========================================================================
    // STEP 2: Initialize Organization Owners
    // =========================================================================
    echo "\nSTEP 2: Initializing organization owners...\n";
    echo str_repeat("-", 50) . "\n";

    // Find organizations without owner in org_members
    $orgsWithoutOwners = Database::query(
        "SELECT vo.id, vo.name, vo.user_id, vo.tenant_id
         FROM vol_organizations vo
         WHERE NOT EXISTS (
             SELECT 1 FROM org_members om
             WHERE om.organization_id = vo.id AND om.user_id = vo.user_id
         )"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orgsWithoutOwners)) {
        echo "  All organizations already have owners in org_members.\n";
    } else {
        echo "  Found " . count($orgsWithoutOwners) . " organization(s) needing owner setup:\n";

        $added = 0;
        foreach ($orgsWithoutOwners as $org) {
            try {
                Database::query(
                    "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status, created_at)
                     VALUES (?, ?, ?, 'owner', 'active', NOW())
                     ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'",
                    [$org['tenant_id'], $org['id'], $org['user_id']]
                );
                echo "    + {$org['name']}\n";
                $added++;
            } catch (Exception $e) {
                echo "    ! Error: {$org['name']} - " . $e->getMessage() . "\n";
            }
        }
        echo "  Added $added owner(s) to org_members.\n";
    }

    // =========================================================================
    // STEP 3: Initialize Organization Wallets
    // =========================================================================
    echo "\nSTEP 3: Initializing organization wallets...\n";
    echo str_repeat("-", 50) . "\n";

    $orgsWithoutWallets = Database::query(
        "SELECT vo.id, vo.name, vo.tenant_id
         FROM vol_organizations vo
         WHERE NOT EXISTS (
             SELECT 1 FROM org_wallets ow WHERE ow.organization_id = vo.id
         )"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orgsWithoutWallets)) {
        echo "  All organizations already have wallets.\n";
    } else {
        echo "  Found " . count($orgsWithoutWallets) . " organization(s) needing wallets:\n";

        $created = 0;
        foreach ($orgsWithoutWallets as $org) {
            try {
                Database::query(
                    "INSERT INTO org_wallets (tenant_id, organization_id, balance, created_at)
                     VALUES (?, ?, 0.00, NOW())
                     ON DUPLICATE KEY UPDATE tenant_id = tenant_id",
                    [$org['tenant_id'], $org['id']]
                );
                echo "    + {$org['name']}\n";
                $created++;
            } catch (Exception $e) {
                echo "    ! Error: {$org['name']} - " . $e->getMessage() . "\n";
            }
        }
        echo "  Created $created wallet(s).\n";
    }

    // =========================================================================
    // STEP 4: Verification
    // =========================================================================
    echo "\nSTEP 4: Verification...\n";
    echo str_repeat("-", 50) . "\n";

    $counts = [
        'org_wallets' => 'Organization Wallets',
        'org_members' => 'Organization Members',
        'org_transfer_requests' => 'Transfer Requests',
        'org_transactions' => 'Transactions',
        'abuse_alerts' => 'Abuse Alerts'
    ];

    foreach ($counts as $table => $label) {
        try {
            $count = Database::query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            echo "  $label: $count rows\n";
        } catch (Exception $e) {
            echo "  $label: ERROR - " . $e->getMessage() . "\n";
        }
    }

    // Check for orgs without complete setup
    $incomplete = Database::query(
        "SELECT vo.id, vo.name,
                (SELECT COUNT(*) FROM org_members om WHERE om.organization_id = vo.id AND om.role = 'owner') as has_owner,
                (SELECT COUNT(*) FROM org_wallets ow WHERE ow.organization_id = vo.id) as has_wallet
         FROM vol_organizations vo
         HAVING has_owner = 0 OR has_wallet = 0"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($incomplete)) {
        echo "\n  WARNING: Some organizations have incomplete setup:\n";
        foreach ($incomplete as $org) {
            $issues = [];
            if ($org['has_owner'] == 0) $issues[] = 'missing owner';
            if ($org['has_wallet'] == 0) $issues[] = 'missing wallet';
            echo "    - [{$org['id']}] {$org['name']}: " . implode(', ', $issues) . "\n";
        }
    }

    // =========================================================================
    // DONE
    // =========================================================================
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║               MIGRATION COMPLETED SUCCESSFULLY              ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";

} catch (Exception $e) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║                    MIGRATION FAILED                         ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
