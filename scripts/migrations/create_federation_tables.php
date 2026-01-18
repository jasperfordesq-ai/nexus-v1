<?php
/**
 * Migration: Create Federation Tables
 *
 * Creates all tables required for the multi-tenant federation system.
 * This migration is SAFE and follows the principle:
 *   - All new tables
 *   - All new columns have safe defaults (OFF/disabled)
 *   - Existing functionality remains unchanged
 *
 * Tables created:
 * - federation_system_control (master kill switch)
 * - federation_tenant_features (per-tenant feature flags)
 * - federation_tenant_whitelist (approved tenants for federation)
 * - federation_partnerships (tenant-to-tenant partnerships)
 * - federation_user_settings (user-level federation opt-in)
 * - federation_audit_log (comprehensive audit trail)
 *
 * Columns added to existing tables:
 * - users: federation_optin, appear_in_federated_search
 * - groups: allow_federated_members
 * - listings: federated_visibility
 * - events: federated_visibility
 *
 * Run: php scripts/migrations/create_federation_tables.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Nexus\Core\Database;

echo "=================================================\n";
echo "  Federation Tables Migration\n";
echo "  SAFE MODE: All features default to OFF\n";
echo "=================================================\n\n";

$db = Database::getConnection();

// Track results
$created = [];
$existed = [];
$failed = [];

/**
 * Check if a table exists
 */
function tableExists($db, $table) {
    try {
        $result = $db->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if a column exists in a table
 */
function columnExists($db, $table, $column) {
    try {
        $result = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// FEDERATION SYSTEM CONTROL (Master Kill Switch)
// ============================================================

echo "[1/8] Creating federation_system_control table...\n";
if (!tableExists($db, 'federation_system_control')) {
    try {
        $db->exec("
            CREATE TABLE federation_system_control (
                id INT UNSIGNED NOT NULL DEFAULT 1,

                -- Master controls (ALL DEFAULT OFF)
                federation_enabled TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Master switch: 0 = ALL federation disabled globally',
                whitelist_mode_enabled TINYINT(1) NOT NULL DEFAULT 1
                    COMMENT 'Only whitelisted tenants can use federation',
                max_federation_level TINYINT UNSIGNED NOT NULL DEFAULT 0
                    COMMENT 'Maximum federation level any tenant can use (0-4)',

                -- Feature kill switches (ALL DEFAULT OFF)
                cross_tenant_profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
                cross_tenant_messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
                cross_tenant_transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
                cross_tenant_listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
                cross_tenant_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
                cross_tenant_groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

                -- Emergency lockdown
                emergency_lockdown_active TINYINT(1) NOT NULL DEFAULT 0,
                emergency_lockdown_reason TEXT NULL,
                emergency_lockdown_at TIMESTAMP NULL,
                emergency_lockdown_by INT UNSIGNED NULL,

                -- Audit
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT UNSIGNED NULL,

                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insert default row with everything OFF
        $db->exec("
            INSERT INTO federation_system_control (id, federation_enabled, whitelist_mode_enabled, max_federation_level)
            VALUES (1, 0, 1, 0)
        ");

        $created[] = 'federation_system_control';
        echo "   ✓ Created federation_system_control table (ALL features OFF by default)\n";
    } catch (Exception $e) {
        $failed['federation_system_control'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_system_control';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION TENANT FEATURES
// ============================================================

echo "[2/8] Creating federation_tenant_features table...\n";
if (!tableExists($db, 'federation_tenant_features')) {
    try {
        $db->exec("
            CREATE TABLE federation_tenant_features (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                feature_key VARCHAR(100) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT UNSIGNED NULL,

                UNIQUE KEY unique_tenant_feature (tenant_id, feature_key),
                INDEX idx_tenant (tenant_id),
                INDEX idx_feature (feature_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_tenant_features';
        echo "   ✓ Created federation_tenant_features table\n";
    } catch (Exception $e) {
        $failed['federation_tenant_features'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_tenant_features';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION TENANT WHITELIST
// ============================================================

echo "[3/8] Creating federation_tenant_whitelist table...\n";
if (!tableExists($db, 'federation_tenant_whitelist')) {
    try {
        $db->exec("
            CREATE TABLE federation_tenant_whitelist (
                tenant_id INT UNSIGNED PRIMARY KEY,
                approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_by INT UNSIGNED NOT NULL,
                notes VARCHAR(500) NULL,

                INDEX idx_approved_at (approved_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_tenant_whitelist';
        echo "   ✓ Created federation_tenant_whitelist table\n";
    } catch (Exception $e) {
        $failed['federation_tenant_whitelist'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_tenant_whitelist';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION PARTNERSHIPS
// ============================================================

echo "[4/8] Creating federation_partnerships table...\n";
if (!tableExists($db, 'federation_partnerships')) {
    try {
        $db->exec("
            CREATE TABLE federation_partnerships (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                partner_tenant_id INT UNSIGNED NOT NULL,

                -- Partnership status
                status ENUM('pending', 'active', 'suspended', 'terminated') NOT NULL DEFAULT 'pending',
                federation_level TINYINT UNSIGNED NOT NULL DEFAULT 1
                    COMMENT '1=Discovery, 2=Social, 3=Economic, 4=Integrated',

                -- Permission flags (ALL DEFAULT OFF)
                profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
                messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
                transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
                listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
                events_enabled TINYINT(1) NOT NULL DEFAULT 0,
                groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

                -- Request tracking
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                requested_by INT UNSIGNED NULL,
                approved_at TIMESTAMP NULL,
                approved_by INT UNSIGNED NULL,
                terminated_at TIMESTAMP NULL,
                terminated_by INT UNSIGNED NULL,
                termination_reason VARCHAR(500) NULL,

                -- Notes and metadata
                notes TEXT NULL,

                -- Timestamps
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

                -- Constraints
                UNIQUE KEY unique_partnership (tenant_id, partner_tenant_id),
                INDEX idx_status (status),
                INDEX idx_tenant (tenant_id),
                INDEX idx_partner (partner_tenant_id),
                INDEX idx_level (federation_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_partnerships';
        echo "   ✓ Created federation_partnerships table\n";
    } catch (Exception $e) {
        $failed['federation_partnerships'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_partnerships';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION USER SETTINGS
// ============================================================

echo "[5/8] Creating federation_user_settings table...\n";
if (!tableExists($db, 'federation_user_settings')) {
    try {
        $db->exec("
            CREATE TABLE federation_user_settings (
                user_id INT UNSIGNED PRIMARY KEY,

                -- Master opt-in (user must explicitly enable federation)
                federation_optin TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'User has explicitly opted into federation',

                -- Visibility settings (ALL DEFAULT OFF)
                profile_visible_federated TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Profile visible to partner timebanks',
                messaging_enabled_federated TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Can receive messages from partner timebanks',
                transactions_enabled_federated TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Can transact with partner timebank members',

                -- Discovery preferences (ALL DEFAULT OFF)
                appear_in_federated_search TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Appear in federated member search',
                show_skills_federated TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Show skills in federated profile',
                show_location_federated TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Show location in federated profile',

                -- Service type preferences
                service_reach ENUM('local_only', 'remote_ok', 'travel_ok') NOT NULL DEFAULT 'local_only'
                    COMMENT 'How far user is willing to provide services',
                travel_radius_km INT UNSIGNED NULL DEFAULT NULL
                    COMMENT 'Maximum travel distance in km (NULL = no limit)',

                -- Timestamps
                opted_in_at TIMESTAMP NULL
                    COMMENT 'When user first opted in',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_optin (federation_optin),
                INDEX idx_searchable (appear_in_federated_search),
                INDEX idx_service_reach (service_reach)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_user_settings';
        echo "   ✓ Created federation_user_settings table\n";
    } catch (Exception $e) {
        $failed['federation_user_settings'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_user_settings';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION AUDIT LOG
// ============================================================

echo "[6/8] Creating federation_audit_log table...\n";
if (!tableExists($db, 'federation_audit_log')) {
    try {
        $db->exec("
            CREATE TABLE federation_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                -- Action info
                action_type VARCHAR(100) NOT NULL,
                category VARCHAR(50) NOT NULL,
                level ENUM('debug', 'info', 'warning', 'critical') NOT NULL DEFAULT 'info',

                -- Tenant context
                source_tenant_id INT UNSIGNED NULL
                    COMMENT 'Tenant initiating the action',
                target_tenant_id INT UNSIGNED NULL
                    COMMENT 'Target tenant if applicable',

                -- Actor info
                actor_user_id INT UNSIGNED NULL,
                actor_name VARCHAR(200) NULL,
                actor_email VARCHAR(255) NULL,

                -- Additional context
                data JSON NULL
                    COMMENT 'Additional context data in JSON format',

                -- Request metadata
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,

                -- Timestamp
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                -- Indexes for efficient querying
                INDEX idx_action_type (action_type),
                INDEX idx_category (category),
                INDEX idx_level (level),
                INDEX idx_source_tenant (source_tenant_id),
                INDEX idx_target_tenant (target_tenant_id),
                INDEX idx_actor (actor_user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_level_created (level, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_audit_log';
        echo "   ✓ Created federation_audit_log table\n";
    } catch (Exception $e) {
        $failed['federation_audit_log'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_audit_log';
    echo "   → Table already exists\n";
}

// ============================================================
// FEDERATION REPUTATION (Portable Trust Scores)
// ============================================================

echo "[7/8] Creating federation_reputation table...\n";
if (!tableExists($db, 'federation_reputation')) {
    try {
        $db->exec("
            CREATE TABLE federation_reputation (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                -- The user and their home tenant
                user_id INT UNSIGNED NOT NULL,
                home_tenant_id INT UNSIGNED NOT NULL,

                -- Aggregated reputation scores
                trust_score DECIMAL(5,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Overall trust score (0-100)',
                reliability_score DECIMAL(5,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Based on completed vs cancelled',
                responsiveness_score DECIMAL(5,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Based on response times',
                review_score DECIMAL(5,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Average of ratings received',

                -- Activity counts (for verification)
                total_transactions INT UNSIGNED NOT NULL DEFAULT 0,
                successful_transactions INT UNSIGNED NOT NULL DEFAULT 0,
                reviews_received INT UNSIGNED NOT NULL DEFAULT 0,
                reviews_given INT UNSIGNED NOT NULL DEFAULT 0,
                hours_given DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                hours_received DECIMAL(10,2) NOT NULL DEFAULT 0.00,

                -- Verification status
                is_verified TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'Has home tenant verified this user',
                verified_at TIMESTAMP NULL,
                verified_by INT UNSIGNED NULL,

                -- Federation visibility
                share_reputation TINYINT(1) NOT NULL DEFAULT 0
                    COMMENT 'User consents to sharing reputation',

                -- Last sync
                last_calculated_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY unique_user_tenant (user_id, home_tenant_id),
                INDEX idx_trust_score (trust_score),
                INDEX idx_verified (is_verified),
                INDEX idx_share (share_reputation)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'federation_reputation';
        echo "   ✓ Created federation_reputation table\n";
    } catch (Exception $e) {
        $failed['federation_reputation'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'federation_reputation';
    echo "   → Table already exists\n";
}

// ============================================================
// EXISTING TABLE MODIFICATIONS (Safe additions only)
// ============================================================

echo "[8/8] Adding federation columns to existing tables...\n";

// Add federation columns to users table
$userColumns = [
    'federation_optin' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'User opted into federation\'',
    'federated_profile_visible' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Profile visible to partner timebanks\'',
];

echo "   Checking users table...\n";
foreach ($userColumns as $column => $definition) {
    if (tableExists($db, 'users') && !columnExists($db, 'users', $column)) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
            echo "      ✓ Added '{$column}' column to users table (default: OFF)\n";
            $created[] = "users.{$column}";
        } catch (Exception $e) {
            echo "      ✗ Failed to add '{$column}': " . $e->getMessage() . "\n";
            $failed["users.{$column}"] = $e->getMessage();
        }
    } else {
        echo "      → Column '{$column}' already exists or table missing\n";
        $existed[] = "users.{$column}";
    }
}

// Add federation columns to groups table
$groupColumns = [
    'allow_federated_members' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Allow members from partner timebanks\'',
    'federated_visibility' => 'ENUM(\'none\', \'listed\', \'joinable\') NOT NULL DEFAULT \'none\' COMMENT \'Federation visibility level\'',
];

echo "   Checking groups table...\n";
foreach ($groupColumns as $column => $definition) {
    if (tableExists($db, 'groups') && !columnExists($db, 'groups', $column)) {
        try {
            $db->exec("ALTER TABLE `groups` ADD COLUMN {$column} {$definition}");
            echo "      ✓ Added '{$column}' column to groups table (default: OFF/none)\n";
            $created[] = "groups.{$column}";
        } catch (Exception $e) {
            echo "      ✗ Failed to add '{$column}': " . $e->getMessage() . "\n";
            $failed["groups.{$column}"] = $e->getMessage();
        }
    } else {
        echo "      → Column '{$column}' already exists or table missing\n";
        $existed[] = "groups.{$column}";
    }
}

// Add federation columns to listings table
$listingColumns = [
    'federated_visibility' => 'ENUM(\'none\', \'listed\', \'bookable\') NOT NULL DEFAULT \'none\' COMMENT \'Federation visibility level\'',
    'service_type' => 'ENUM(\'physical_only\', \'remote_only\', \'hybrid\', \'location_dependent\') NOT NULL DEFAULT \'physical_only\' COMMENT \'Service delivery type\'',
];

echo "   Checking listings table...\n";
foreach ($listingColumns as $column => $definition) {
    if (tableExists($db, 'listings') && !columnExists($db, 'listings', $column)) {
        try {
            $db->exec("ALTER TABLE listings ADD COLUMN {$column} {$definition}");
            echo "      ✓ Added '{$column}' column to listings table (default: none/physical_only)\n";
            $created[] = "listings.{$column}";
        } catch (Exception $e) {
            echo "      ✗ Failed to add '{$column}': " . $e->getMessage() . "\n";
            $failed["listings.{$column}"] = $e->getMessage();
        }
    } else {
        echo "      → Column '{$column}' already exists or table missing\n";
        $existed[] = "listings.{$column}";
    }
}

// Add federation columns to events table
$eventColumns = [
    'federated_visibility' => 'ENUM(\'none\', \'listed\', \'joinable\') NOT NULL DEFAULT \'none\' COMMENT \'Federation visibility level\'',
    'allow_remote_attendance' => 'TINYINT(1) NOT NULL DEFAULT 0 COMMENT \'Event can be attended remotely\'',
];

echo "   Checking events table...\n";
foreach ($eventColumns as $column => $definition) {
    if (tableExists($db, 'events') && !columnExists($db, 'events', $column)) {
        try {
            $db->exec("ALTER TABLE events ADD COLUMN {$column} {$definition}");
            echo "      ✓ Added '{$column}' column to events table (default: none/OFF)\n";
            $created[] = "events.{$column}";
        } catch (Exception $e) {
            echo "      ✗ Failed to add '{$column}': " . $e->getMessage() . "\n";
            $failed["events.{$column}"] = $e->getMessage();
        }
    } else {
        echo "      → Column '{$column}' already exists or table missing\n";
        $existed[] = "events.{$column}";
    }
}

// ============================================================
// SUMMARY
// ============================================================

echo "\n=================================================\n";
echo "  Federation Migration Complete!\n";
echo "=================================================\n\n";

echo "IMPORTANT: All federation features are DISABLED by default.\n";
echo "To enable federation, a Super Admin must:\n";
echo "  1. Set federation_enabled = 1 in federation_system_control\n";
echo "  2. Whitelist specific tenants (if whitelist_mode_enabled = 1)\n";
echo "  3. Enable specific tenant features in federation_tenant_features\n\n";

echo "Created: " . count($created) . " items\n";
if (!empty($created)) {
    foreach ($created as $item) {
        echo "  ✓ {$item}\n";
    }
}

echo "\nAlready existed: " . count($existed) . " items\n";

if (!empty($failed)) {
    echo "\nFailed: " . count($failed) . " items\n";
    foreach ($failed as $item => $error) {
        echo "  ✗ {$item}: {$error}\n";
    }
}

echo "\n";

// ============================================================
// VERIFICATION
// ============================================================

echo "=================================================\n";
echo "  Verification\n";
echo "=================================================\n\n";

// Check that system control has safe defaults
try {
    $control = $db->query("SELECT * FROM federation_system_control WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if ($control) {
        echo "Federation System Control Status:\n";
        echo "  federation_enabled: " . ($control['federation_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "  whitelist_mode_enabled: " . ($control['whitelist_mode_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "  emergency_lockdown_active: " . ($control['emergency_lockdown_active'] ? 'ON' : 'OFF') . "\n";
        echo "  cross_tenant_profiles_enabled: " . ($control['cross_tenant_profiles_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "  cross_tenant_messaging_enabled: " . ($control['cross_tenant_messaging_enabled'] ? 'ON' : 'OFF') . "\n";
        echo "  cross_tenant_transactions_enabled: " . ($control['cross_tenant_transactions_enabled'] ? 'ON' : 'OFF') . "\n";

        // Confirm everything is OFF
        $allOff = (
            $control['federation_enabled'] == 0 &&
            $control['cross_tenant_profiles_enabled'] == 0 &&
            $control['cross_tenant_messaging_enabled'] == 0 &&
            $control['cross_tenant_transactions_enabled'] == 0 &&
            $control['cross_tenant_listings_enabled'] == 0 &&
            $control['cross_tenant_events_enabled'] == 0 &&
            $control['cross_tenant_groups_enabled'] == 0
        );

        if ($allOff) {
            echo "\n  ✓ VERIFIED: All federation features are safely OFF\n";
        } else {
            echo "\n  ⚠ WARNING: Some features are enabled - please verify this is intentional\n";
        }
    }
} catch (Exception $e) {
    echo "Could not verify system control: " . $e->getMessage() . "\n";
}

echo "\n";
