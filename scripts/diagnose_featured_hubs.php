<?php
/**
 * Diagnostic script for Featured Local Hubs
 *
 * Run from command line: php scripts/diagnose_featured_hubs.php
 * Or via web: /scripts/diagnose_featured_hubs.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\GroupType;
use Nexus\Services\OptimizedGroupQueries;
use Nexus\Services\SmartGroupRankingService;

// First, find which tenants have hub groups
echo "=== CHECKING ALL TENANTS FOR HUB GROUPS ===\n\n";
$tenantsWithHubs = Database::query(
    "SELECT g.tenant_id, t.name as tenant_name, COUNT(*) as hub_count
     FROM `groups` g
     JOIN tenants t ON t.id = g.tenant_id
     JOIN group_types gt ON gt.id = g.type_id AND gt.is_hub = 1
     GROUP BY g.tenant_id, t.name
     ORDER BY hub_count DESC"
)->fetchAll();

if (empty($tenantsWithHubs)) {
    echo "No tenants have hub groups!\n";
    echo "Checking group_types for is_hub flag...\n";
    $hubTypes = Database::query("SELECT * FROM group_types WHERE is_hub = 1")->fetchAll();
    print_r($hubTypes);
    exit;
}

foreach ($tenantsWithHubs as $t) {
    echo "Tenant {$t['tenant_id']} ({$t['tenant_name']}): {$t['hub_count']} hub groups\n";
}
echo "\n";

// Use tenant with most hubs
$tenantId = $tenantsWithHubs[0]['tenant_id'];
TenantContext::setById($tenantId);

echo "=== FEATURED LOCAL HUBS DIAGNOSTIC ===\n\n";
echo "Tenant ID: $tenantId\n\n";

// 1. Check hub type
echo "--- 1. HUB TYPE ---\n";
$hubType = GroupType::getHubType();
if (!$hubType) {
    echo "ERROR: No hub type found! Check group_types table for is_hub = 1\n";
    exit(1);
}
echo "Hub Type ID: {$hubType['id']}\n";
echo "Hub Type Name: {$hubType['name']}\n\n";

// 2. Count total hub groups
echo "--- 2. TOTAL HUB GROUPS ---\n";
$totalHubs = Database::query(
    "SELECT COUNT(*) as count FROM `groups` WHERE tenant_id = ? AND type_id = ?",
    [$tenantId, $hubType['id']]
)->fetch();
echo "Total hub groups: {$totalHubs['count']}\n\n";

// 3. Count leaf hub groups (no children)
echo "--- 3. LEAF HUB GROUPS (no children) ---\n";
$leafHubs = Database::query(
    "SELECT COUNT(*) as count
     FROM `groups` g
     WHERE g.tenant_id = ?
     AND g.type_id = ?
     AND NOT EXISTS (
         SELECT 1 FROM `groups` child
         WHERE child.parent_id = g.id AND child.tenant_id = ?
     )",
    [$tenantId, $hubType['id'], $tenantId]
)->fetch();
echo "Leaf hub groups: {$leafHubs['count']}\n\n";

// 4. Currently featured groups
echo "--- 4. CURRENTLY FEATURED GROUPS ---\n";
$featured = Database::query(
    "SELECT g.id, g.name, g.cached_member_count, g.parent_id, p.name as parent_name
     FROM `groups` g
     LEFT JOIN `groups` p ON p.id = g.parent_id
     WHERE g.tenant_id = ? AND g.is_featured = 1
     ORDER BY g.cached_member_count DESC",
    [$tenantId]
)->fetchAll();
echo "Currently featured: " . count($featured) . "\n";
foreach ($featured as $g) {
    echo "  - [{$g['id']}] {$g['name']} (members: {$g['cached_member_count']}, parent: {$g['parent_name']})\n";
}
echo "\n";

// 5. What getLeafGroups returns
echo "--- 5. OptimizedGroupQueries::getLeafGroups() RESULT ---\n";
$leafGroups = OptimizedGroupQueries::getLeafGroups($tenantId, $hubType['id'], 10);
echo "Returned: " . count($leafGroups) . " groups\n";
foreach ($leafGroups as $g) {
    echo "  - [{$g['id']}] {$g['name']} (members: {$g['member_count']})\n";
}
echo "\n";

// 6. Top 10 leaf hubs by member count (raw query)
echo "--- 6. TOP 10 LEAF HUBS BY MEMBER COUNT (raw query) ---\n";
$topLeaf = Database::query(
    "SELECT g.id, g.name, g.cached_member_count,
            COUNT(DISTINCT gm.user_id) as live_member_count,
            p.name as parent_name
     FROM `groups` g
     LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
     LEFT JOIN `groups` p ON p.id = g.parent_id
     WHERE g.tenant_id = ?
     AND g.type_id = ?
     AND NOT EXISTS (
         SELECT 1 FROM `groups` child
         WHERE child.parent_id = g.id AND child.tenant_id = ?
     )
     GROUP BY g.id, g.name, g.cached_member_count, p.name
     ORDER BY live_member_count DESC
     LIMIT 10",
    [$tenantId, $hubType['id'], $tenantId]
)->fetchAll();
echo "Found: " . count($topLeaf) . " leaf hubs\n";
foreach ($topLeaf as $g) {
    echo "  - [{$g['id']}] {$g['name']} (cached: {$g['cached_member_count']}, live: {$g['live_member_count']}, parent: {$g['parent_name']})\n";
}
echo "\n";

// 7. Check hierarchy depth
echo "--- 7. HUB HIERARCHY STRUCTURE ---\n";
$hierarchy = Database::query(
    "SELECT
        CASE
            WHEN g.parent_id IS NULL THEN 'Level 0 (Root)'
            WHEN p.parent_id IS NULL THEN 'Level 1'
            WHEN gp.parent_id IS NULL THEN 'Level 2'
            ELSE 'Level 3+'
        END as level,
        COUNT(*) as count
     FROM `groups` g
     LEFT JOIN `groups` p ON p.id = g.parent_id
     LEFT JOIN `groups` gp ON gp.id = p.parent_id
     WHERE g.tenant_id = ? AND g.type_id = ?
     GROUP BY level
     ORDER BY level",
    [$tenantId, $hubType['id']]
)->fetchAll();
foreach ($hierarchy as $h) {
    echo "  {$h['level']}: {$h['count']} groups\n";
}
echo "\n";

// 8. Simulate running the ranking update
echo "--- 8. SIMULATING RANKING UPDATE (dry run) ---\n";
echo "Would mark these 6 groups as featured:\n";
$wouldFeature = array_slice($leafGroups, 0, 6);
foreach ($wouldFeature as $g) {
    echo "  - [{$g['id']}] {$g['name']} (members: {$g['member_count']})\n";
}
echo "\n";

echo "=== DIAGNOSIS COMPLETE ===\n";

// Option to run the update
echo "\n--- RUNNING RANKING UPDATE ---\n";
$stats = SmartGroupRankingService::updateFeaturedLocalHubs($tenantId, 6);
echo "Update completed!\n";
echo "Cleared: {$stats['cleared']} flags\n";
echo "Featured: {$stats['featured']} groups\n";
echo "Groups marked as featured:\n";
foreach ($stats['groups'] as $g) {
    echo "  - [{$g['id']}] {$g['name']} (members: {$g['member_count']})\n";
}
