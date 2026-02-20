#!/usr/bin/env php
<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Audit Moved Users - Cross-Tenant Orphaned Data Detector
 *
 * Scans all users and checks if they have orphaned data on a different
 * tenant_id than their current tenant. This happens when users are moved
 * between tenants (e.g., via admin panel) but their existing records are
 * not updated.
 *
 * Run inside Docker:
 *   docker exec nexus-php-app php scripts/audit_moved_users.php           # Report only
 *   docker exec nexus-php-app php scripts/audit_moved_users.php --fix     # Fix mismatches
 *   docker exec nexus-php-app php scripts/audit_moved_users.php --help    # Show help
 *
 * On production:
 *   sudo docker exec nexus-php-app php scripts/audit_moved_users.php
 */

// ---------------------------------------------------------------------------
// CLI argument parsing
// ---------------------------------------------------------------------------

$fixMode    = in_array('--fix', $argv);
$verbose    = in_array('--verbose', $argv) || in_array('-v', $argv);
$showHelp   = in_array('--help', $argv) || in_array('-h', $argv);
$userFilter = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--user=') === 0) {
        $userFilter = (int) substr($arg, 7);
    }
}

if ($showHelp) {
    echo <<<HELP
Usage: php scripts/audit_moved_users.php [options]

Options:
  --fix             Fix mismatched records (UPDATE tenant_id to user's current tenant)
  --verbose, -v     Show all tables checked, even if no mismatches found
  --user=ID         Only audit a single user by ID
  --help, -h        Show this help message

Examples:
  php scripts/audit_moved_users.php                 # Report only (safe, read-only)
  php scripts/audit_moved_users.php --fix           # Fix all mismatches
  php scripts/audit_moved_users.php --user=264      # Audit a single user
  php scripts/audit_moved_users.php --user=264 --fix  # Fix a single user

HELP;
    exit(0);
}

// ---------------------------------------------------------------------------
// Color output helpers
// ---------------------------------------------------------------------------

function colorize(string $text, string $color): string
{
    $colors = [
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'cyan'    => "\033[36m",
        'bold'    => "\033[1m",
        'reset'   => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function info(string $msg): void    { echo colorize("  [INFO] ", 'cyan') . $msg . "\n"; }
function warn(string $msg): void    { echo colorize("  [MISMATCH] ", 'yellow') . $msg . "\n"; }
function fixed(string $msg): void   { echo colorize("  [FIXED] ", 'green') . $msg . "\n"; }
function err(string $msg): void     { echo colorize("  [ERROR] ", 'red') . $msg . "\n"; }

// ---------------------------------------------------------------------------
// Database connection
// ---------------------------------------------------------------------------

$dbHost = getenv('DB_HOST') ?: 'db';
$dbName = getenv('DB_NAME') ?: 'nexus';
$dbUser = getenv('DB_USER') ?: 'nexus';
$dbPass = getenv('DB_PASS') ?: 'HpW4H99dd2BNXjtl5FhHlIEitzAkjmm';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    echo colorize("ERROR: ", 'red') . "Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Table/column definitions to audit
// ---------------------------------------------------------------------------
// Each entry: [table_name, user_column, description]
// Some tables have multiple user columns (e.g., messages has sender_id and receiver_id).
// We check each column independently.

$tableChecks = [
    // Core content
    ['listings',              'user_id',        'Listings (owner)'],
    ['feed_posts',            'user_id',        'Feed posts'],
    ['events',                'user_id',        'Events (creator)'],
    ['notifications',         'user_id',        'Notifications'],
    ['posts',                 'author_id',      'Blog posts (author)'],

    // Messaging
    ['messages',              'sender_id',      'Messages (sender)'],
    ['messages',              'receiver_id',    'Messages (receiver)'],

    // Transactions
    ['transactions',          'sender_id',      'Transactions (sender)'],
    ['transactions',          'receiver_id',    'Transactions (receiver)'],

    // Social
    ['reviews',               'reviewer_id',    'Reviews (reviewer)'],
    ['reviews',               'receiver_id',    'Reviews (receiver)'],
    ['connections',           'requester_id',   'Connections (requester)'],
    ['connections',           'receiver_id',    'Connections (receiver)'],

    // Exchanges
    ['exchange_requests',     'requester_id',   'Exchange requests (requester)'],
    ['exchange_requests',     'provider_id',    'Exchange requests (provider)'],

    // Gamification
    ['user_badges',           'user_id',        'User badges'],
    ['user_xp_log',           'user_id',        'XP log entries'],
    ['user_streaks',          'user_id',        'User streaks'],

    // Security & auth
    ['webauthn_credentials',  'user_id',        'WebAuthn credentials'],
    ['user_totp_settings',    'user_id',        'TOTP 2FA settings'],
    ['user_backup_codes',     'user_id',        'Backup codes'],

    // GDPR & compliance
    ['gdpr_audit_log',        'user_id',        'GDPR audit log'],
    ['user_consents',         'user_id',        'User consents'],
    ['user_legal_acceptances','user_id',        'Legal acceptances'],

    // Activity & moderation
    ['activity_log',          'user_id',        'Activity log'],
    ['abuse_alerts',          'user_id',        'Abuse alerts'],
    ['reports',               'reporter_id',    'Reports (reporter)'],
    ['admin_actions',         'admin_id',       'Admin actions (admin)'],
    ['admin_actions',         'target_user_id', 'Admin actions (target)'],

    // Push & subscriptions
    ['push_subscriptions',    'user_id',        'Push subscriptions'],
    ['newsletter_subscribers','user_id',        'Newsletter subscribers'],

    // AI & misc
    ['ai_conversations',      'user_id',        'AI conversations'],
    ['deliverables',          'owner_id',       'Deliverables (owner)'],
    ['groups',                'owner_id',       'Groups (owner)'],
];

// ---------------------------------------------------------------------------
// Discover which tables actually exist in the database
// ---------------------------------------------------------------------------

$existingTables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $existingTables[$row[0]] = true;
}

// For each table that exists, also check which columns it has.
// Some tables might not have a tenant_id column at all.
$tableColumns = [];

// ---------------------------------------------------------------------------
// Pre-check: which tables have the required columns
// ---------------------------------------------------------------------------

$validChecks = [];
$skippedTables = [];

foreach ($tableChecks as [$table, $userCol, $desc]) {
    // Table must exist
    if (!isset($existingTables[$table])) {
        $skippedTables[] = "$table (table does not exist)";
        continue;
    }

    // Cache column info per table
    if (!isset($tableColumns[$table])) {
        $cols = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        while ($col = $colStmt->fetch()) {
            $cols[$col['Field']] = true;
        }
        $tableColumns[$table] = $cols;
    }

    // User column must exist
    if (!isset($tableColumns[$table][$userCol])) {
        $skippedTables[] = "$table.$userCol (column does not exist)";
        continue;
    }

    // tenant_id column must exist (otherwise we can't check or fix)
    if (!isset($tableColumns[$table]['tenant_id'])) {
        $skippedTables[] = "$table (no tenant_id column)";
        continue;
    }

    $validChecks[] = [$table, $userCol, $desc];
}

// ---------------------------------------------------------------------------
// Load users
// ---------------------------------------------------------------------------

if ($userFilter) {
    $userStmt = $pdo->prepare(
        "SELECT id, COALESCE(NULLIF(CONCAT(first_name, ' ', last_name), ' '), email) AS name, tenant_id
         FROM users WHERE id = ?"
    );
    $userStmt->execute([$userFilter]);
} else {
    $userStmt = $pdo->query(
        "SELECT id, COALESCE(NULLIF(CONCAT(first_name, ' ', last_name), ' '), email) AS name, tenant_id
         FROM users ORDER BY id"
    );
}

$users = $userStmt->fetchAll();

if (empty($users)) {
    echo colorize("No users found", 'red');
    if ($userFilter) {
        echo " with ID $userFilter";
    }
    echo ".\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Header
// ---------------------------------------------------------------------------

echo "\n";
echo colorize("=== USER DATA TENANT AUDIT ===", 'bold') . "\n";
echo "Checking " . count($users) . " user(s) across " . count($validChecks) . " table/column checks...\n";
echo "Mode: " . ($fixMode ? colorize("FIX (will update records)", 'yellow') : colorize("REPORT ONLY (read-only)", 'green')) . "\n";

if (!empty($skippedTables)) {
    echo "\nSkipped " . count($skippedTables) . " table checks:\n";
    foreach ($skippedTables as $reason) {
        echo "  - $reason\n";
    }
}

echo "\n";

// ---------------------------------------------------------------------------
// Prepare queries for each valid check
// ---------------------------------------------------------------------------

// For each (table, userCol), we want to find records where the user appears
// on a tenant_id that differs from the user's current tenant, OR where
// tenant_id IS NULL (another form of orphaned data).

$mismatchQueries = [];
$countQueries    = [];
$fixQueries      = [];

foreach ($validChecks as [$table, $userCol, $desc]) {
    // Count records on wrong tenant
    $mismatchQueries["$table.$userCol"] = $pdo->prepare(
        "SELECT tenant_id, COUNT(*) AS cnt
         FROM `$table`
         WHERE `$userCol` = ?
           AND (tenant_id != ? OR tenant_id IS NULL)
         GROUP BY tenant_id"
    );

    // Fix query: update tenant_id to the user's current tenant
    $fixQueries["$table.$userCol"] = $pdo->prepare(
        "UPDATE `$table`
         SET tenant_id = ?
         WHERE `$userCol` = ?
           AND (tenant_id != ? OR tenant_id IS NULL)"
    );
}

// ---------------------------------------------------------------------------
// Main audit loop
// ---------------------------------------------------------------------------

$usersWithMismatches = 0;
$totalRecordsAffected = 0;
$totalRecordsFixed = 0;
$mismatchDetails = []; // For summary

foreach ($users as $user) {
    $userId   = (int) $user['id'];
    $userName = trim($user['name']) ?: "(no name)";
    $tenantId = (int) $user['tenant_id'];

    $userMismatches = [];

    foreach ($validChecks as [$table, $userCol, $desc]) {
        $key = "$table.$userCol";
        $stmt = $mismatchQueries[$key];
        $stmt->execute([$userId, $tenantId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $wrongTenant = $row['tenant_id'];
            $count       = (int) $row['cnt'];

            $tenantLabel = ($wrongTenant === null) ? 'NULL tenant_id' : "tenant $wrongTenant";
            $userMismatches[] = [
                'table'    => $table,
                'column'   => $userCol,
                'desc'     => $desc,
                'tenant'   => $tenantLabel,
                'count'    => $count,
            ];
            $totalRecordsAffected += $count;
        }
    }

    if (!empty($userMismatches)) {
        $usersWithMismatches++;
        echo colorize("User $userId", 'bold') . " ($userName, tenant $tenantId):\n";

        foreach ($userMismatches as $m) {
            warn("{$m['desc']} ({$m['table']}.{$m['column']}): {$m['count']} record(s) on {$m['tenant']}");
        }

        // Fix if requested
        if ($fixMode) {
            $pdo->beginTransaction();
            try {
                $userFixCount = 0;
                foreach ($validChecks as [$table, $userCol, $desc]) {
                    $key = "$table.$userCol";
                    $fixStmt = $fixQueries[$key];
                    $fixStmt->execute([$tenantId, $userId, $tenantId]);
                    $affected = $fixStmt->rowCount();
                    if ($affected > 0) {
                        fixed("$desc ($table.$userCol): updated $affected record(s) to tenant $tenantId");
                        $userFixCount += $affected;
                    }
                }
                $pdo->commit();
                $totalRecordsFixed += $userFixCount;
            } catch (PDOException $e) {
                $pdo->rollBack();
                err("Failed to fix user $userId: " . $e->getMessage());
            }
        }

        echo "\n";
    } elseif ($verbose) {
        echo "User $userId ($userName, tenant $tenantId): " . colorize("OK", 'green') . "\n";
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo str_repeat('=', 60) . "\n";
echo colorize("SUMMARY", 'bold') . "\n";
echo str_repeat('-', 60) . "\n";
echo "Users checked:       " . count($users) . "\n";
echo "Table checks:        " . count($validChecks) . "\n";
echo "Users with issues:   " . ($usersWithMismatches > 0
    ? colorize("$usersWithMismatches", 'yellow')
    : colorize("0", 'green')) . "\n";
echo "Records affected:    " . ($totalRecordsAffected > 0
    ? colorize("$totalRecordsAffected", 'yellow')
    : colorize("0", 'green')) . "\n";

if ($fixMode && $totalRecordsFixed > 0) {
    echo "Records fixed:       " . colorize("$totalRecordsFixed", 'green') . "\n";
}

if ($usersWithMismatches > 0 && !$fixMode) {
    echo "\n" . colorize("To fix these mismatches, re-run with --fix:", 'cyan') . "\n";
    echo "  php scripts/audit_moved_users.php --fix\n";
    echo "\nThe --fix flag will UPDATE each mismatched record's tenant_id\n";
    echo "to match the user's current tenant_id, wrapped in a transaction per user.\n";
}

if ($usersWithMismatches === 0) {
    echo "\n" . colorize("All user data is correctly scoped to their current tenant.", 'green') . "\n";
}

echo "\nDone.\n";
exit($usersWithMismatches > 0 && !$fixMode ? 1 : 0);
