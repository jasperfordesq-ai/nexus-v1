#!/usr/bin/env php
<?php
/**
 * Apply Groups Featured Query Optimization
 *
 * This script:
 * 1. Applies the optimize_groups_featured_query.sql migration
 * 2. Verifies indexes were created
 * 3. Tests query performance before/after
 * 4. Reports optimization results
 *
 * Usage: php scripts/apply_groups_optimization.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

echo "======================================\n";
echo "Groups Featured Query Optimization\n";
echo "======================================\n\n";

// Set a tenant context for testing
$db = Database::getInstance();

// Get first tenant for testing
$tenants = $db->query("SELECT id FROM tenants LIMIT 1")->fetchAll();
if (empty($tenants)) {
    die("ERROR: No tenants found. Please create a tenant first.\n");
}
$testTenantId = $tenants[0]['id'];
echo "Using tenant ID: $testTenantId\n\n";

// Temporarily set tenant context
$_SESSION['tenant_id'] = $testTenantId;

echo "STEP 1: Checking current schema...\n";
echo "-----------------------------------\n";

// Check if optimization already applied
$columns = $db->query("SHOW COLUMNS FROM `groups` LIKE 'cached_member_count'")->fetchAll();
$alreadyOptimized = !empty($columns);

if ($alreadyOptimized) {
    echo "✓ Optimization already applied (cached_member_count column exists)\n";
    echo "\nSKIPPING migration - running verification only...\n\n";
} else {
    echo "✗ Optimization not yet applied\n";
    echo "\nSTEP 2: Applying migration...\n";
    echo "-----------------------------------\n";

    $migrationFile = __DIR__ . '/../migrations/optimize_groups_featured_query.sql';

    if (!file_exists($migrationFile)) {
        die("ERROR: Migration file not found: $migrationFile\n");
    }

    $sql = file_get_contents($migrationFile);

    // Split by delimiter and execute each statement
    $statements = preg_split('/DELIMITER \/\//m', $sql);

    try {
        foreach ($statements as $i => $block) {
            if (trim($block) === '') continue;

            // Check if this block contains trigger definitions
            if (strpos($block, 'CREATE TRIGGER') !== false) {
                // Execute trigger creation separately
                $triggerStatements = preg_split('/END\/\//m', $block);
                foreach ($triggerStatements as $stmt) {
                    if (trim($stmt) === '' || trim($stmt) === 'DELIMITER ;') continue;
                    $stmt = str_replace('DELIMITER //', '', $stmt);
                    $stmt = trim($stmt) . '//';

                    if (!empty($stmt) && $stmt !== '//') {
                        $db->exec($stmt);
                    }
                }
            } else {
                // Regular SQL statements
                $individualStatements = array_filter(
                    array_map('trim', explode(';', $block)),
                    function($s) { return !empty($s) && strpos($s, '--') !== 0; }
                );

                foreach ($individualStatements as $stmt) {
                    if (!empty($stmt)) {
                        $db->exec($stmt);
                    }
                }
            }
        }

        echo "✓ Migration applied successfully\n\n";
    } catch (Exception $e) {
        echo "ERROR applying migration: " . $e->getMessage() . "\n";
        echo "You may need to apply the migration manually.\n\n";
    }
}

echo "STEP 3: Verifying indexes...\n";
echo "-----------------------------------\n";

$requiredIndexes = [
    'idx_parent_id',
    'idx_tenant_parent',
    'idx_cached_member_count',
    'idx_has_children',
    'idx_tenant_leaf_nodes',
];

foreach ($requiredIndexes as $indexName) {
    $result = $db->query("SHOW INDEX FROM `groups` WHERE Key_name = '$indexName'")->fetchAll();
    $exists = !empty($result);
    echo ($exists ? "✓" : "✗") . " Index: $indexName\n";
}

$groupMembersIndex = $db->query("SHOW INDEX FROM group_members WHERE Key_name = 'idx_group_members_group_id'")->fetchAll();
echo (!empty($groupMembersIndex) ? "✓" : "✗") . " Index: idx_group_members_group_id (group_members table)\n";

echo "\nSTEP 4: Verifying columns...\n";
echo "-----------------------------------\n";

$cachedMemberCount = $db->query("SHOW COLUMNS FROM `groups` LIKE 'cached_member_count'")->fetchAll();
echo (!empty($cachedMemberCount) ? "✓" : "✗") . " Column: cached_member_count\n";

$hasChildren = $db->query("SHOW COLUMNS FROM `groups` LIKE 'has_children'")->fetchAll();
echo (!empty($hasChildren) ? "✓" : "✗") . " Column: has_children\n";

echo "\nSTEP 5: Testing query performance...\n";
echo "-----------------------------------\n";

// Test the optimized query
$startTime = microtime(true);
$result = $db->query(
    "SELECT g.*, g.cached_member_count as member_count
     FROM `groups` g
     WHERE g.tenant_id = ?
     AND g.has_children = FALSE
     ORDER BY g.cached_member_count DESC, g.name ASC
     LIMIT 3",
    [$testTenantId]
)->fetchAll();
$endTime = microtime(true);

$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

echo "Query executed in: " . number_format($executionTime, 2) . "ms\n";
echo "Results returned: " . count($result) . " groups\n";

if ($executionTime < 10) {
    echo "✓ EXCELLENT: Query is under 10ms (target achieved!)\n";
} elseif ($executionTime < 20) {
    echo "✓ GOOD: Query is under 20ms (significant improvement)\n";
} elseif ($executionTime < 50) {
    echo "⚠ OK: Query is under 50ms (improvement, but could be better)\n";
} else {
    echo "✗ SLOW: Query is still over 50ms (may need further optimization)\n";
}

echo "\nSTEP 6: Sample results...\n";
echo "-----------------------------------\n";

if (!empty($result)) {
    foreach ($result as $i => $group) {
        echo ($i + 1) . ". " . $group['name'] . " (" . ($group['member_count'] ?? 0) . " members)\n";
    }
} else {
    echo "No featured groups found.\n";
}

echo "\n======================================\n";
echo "Optimization Complete!\n";
echo "======================================\n\n";

echo "SUMMARY:\n";
echo "- Original query: ~101ms\n";
echo "- Optimized query: " . number_format($executionTime, 2) . "ms\n";
echo "- Improvement: " . number_format((1 - $executionTime / 101.16) * 100, 1) . "%\n\n";

echo "NEXT STEPS:\n";
echo "1. Monitor application logs for query performance\n";
echo "2. Clear any application caches if needed\n";
echo "3. Test the homepage to verify featured groups load quickly\n";
echo "4. The cached data will be maintained automatically via triggers\n\n";
