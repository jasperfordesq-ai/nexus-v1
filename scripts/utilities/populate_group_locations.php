<?php
/**
 * Utility Script: Populate Group Locations from Parent Groups
 *
 * This script analyzes groups with empty location fields and populates them
 * based on their parent group names (which typically contain county names).
 *
 * Usage:
 *   php populate_group_locations.php --dry-run    # Preview changes without saving
 *   php populate_group_locations.php --execute    # Actually update the database
 *   php populate_group_locations.php --report     # Show detailed analysis report
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

// Load the application bootstrap
$basePath = dirname(dirname(__DIR__));
require_once $basePath . '/vendor/autoload.php';

// Load environment
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    $dotenv = Dotenv\Dotenv::createImmutable($basePath);
    $dotenv->load();
}

use Nexus\Core\Database;

// Parse command line arguments
$mode = 'dry-run'; // Default to safe mode
if (in_array('--execute', $argv)) {
    $mode = 'execute';
} elseif (in_array('--report', $argv)) {
    $mode = 'report';
}

echo "=================================================================\n";
echo "  Group Location Population Script\n";
echo "  Mode: " . strtoupper($mode) . "\n";
echo "=================================================================\n\n";

// Irish county mappings - extract county name from parent group name
$countyPatterns = [
    'Cork' => 'Cork, Ireland',
    'Dublin' => 'Dublin, Ireland',
    'Galway' => 'Galway, Ireland',
    'Limerick' => 'Limerick, Ireland',
    'Kerry' => 'Kerry, Ireland',
    'Mayo' => 'Mayo, Ireland',
    'Clare' => 'Clare, Ireland',
    'Tipperary' => 'Tipperary, Ireland',
    'Waterford' => 'Waterford, Ireland',
    'Wexford' => 'Wexford, Ireland',
    'Wicklow' => 'Wicklow, Ireland',
    'Kilkenny' => 'Kilkenny, Ireland',
    'Kildare' => 'Kildare, Ireland',
    'Meath' => 'Meath, Ireland',
    'Louth' => 'Louth, Ireland',
    'Westmeath' => 'Westmeath, Ireland',
    'Offaly' => 'Offaly, Ireland',
    'Laois' => 'Laois, Ireland',
    'Carlow' => 'Carlow, Ireland',
    'Longford' => 'Longford, Ireland',
    'Cavan' => 'Cavan, Ireland',
    'Monaghan' => 'Monaghan, Ireland',
    'Donegal' => 'Donegal, Ireland',
    'Sligo' => 'Sligo, Ireland',
    'Leitrim' => 'Leitrim, Ireland',
    'Roscommon' => 'Roscommon, Ireland',
    'Antrim' => 'Antrim, Northern Ireland',
    'Armagh' => 'Armagh, Northern Ireland',
    'Down' => 'Down, Northern Ireland',
    'Derry' => 'Derry, Northern Ireland',
    'Fermanagh' => 'Fermanagh, Northern Ireland',
    'Tyrone' => 'Tyrone, Northern Ireland',
];

/**
 * Extract location from a group name
 */
function extractLocationFromName($name, $countyPatterns) {
    foreach ($countyPatterns as $county => $location) {
        // Check if county name appears in the group name
        if (stripos($name, $county) !== false) {
            return $location;
        }
    }
    return null;
}

// Fetch all groups with their parent info
$sql = "SELECT
            g.id,
            g.name,
            g.location,
            g.parent_id,
            g.tenant_id,
            p.name as parent_name,
            p.location as parent_location
        FROM `groups` g
        LEFT JOIN `groups` p ON g.parent_id = p.id
        ORDER BY g.tenant_id, g.parent_id, g.name";

$groups = Database::query($sql)->fetchAll();

echo "Total groups found: " . count($groups) . "\n\n";

// Analyze groups
$stats = [
    'total' => count($groups),
    'with_location' => 0,
    'without_location' => 0,
    'can_inherit_from_parent' => 0,
    'can_extract_from_name' => 0,
    'cannot_determine' => 0,
];

$updates = [];
$cannotDetermine = [];

foreach ($groups as $group) {
    if (!empty($group['location'])) {
        $stats['with_location']++;
        continue;
    }

    $stats['without_location']++;

    // Strategy 1: Inherit parent's location if available
    if (!empty($group['parent_location'])) {
        $stats['can_inherit_from_parent']++;
        $updates[] = [
            'id' => $group['id'],
            'name' => $group['name'],
            'new_location' => $group['parent_location'],
            'source' => 'parent_location',
            'parent_name' => $group['parent_name'],
        ];
        continue;
    }

    // Strategy 2: Extract county from parent name
    if (!empty($group['parent_name'])) {
        $extracted = extractLocationFromName($group['parent_name'], $countyPatterns);
        if ($extracted) {
            $stats['can_extract_from_name']++;
            $updates[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'new_location' => $extracted,
                'source' => 'parent_name_extraction',
                'parent_name' => $group['parent_name'],
            ];
            continue;
        }
    }

    // Strategy 3: Extract county from own name
    $extracted = extractLocationFromName($group['name'], $countyPatterns);
    if ($extracted) {
        $stats['can_extract_from_name']++;
        $updates[] = [
            'id' => $group['id'],
            'name' => $group['name'],
            'new_location' => $extracted,
            'source' => 'own_name_extraction',
            'parent_name' => $group['parent_name'] ?? 'N/A',
        ];
        continue;
    }

    // Cannot determine location
    $stats['cannot_determine']++;
    $cannotDetermine[] = [
        'id' => $group['id'],
        'name' => $group['name'],
        'parent_name' => $group['parent_name'] ?? 'N/A',
    ];
}

// Output statistics
echo "=== STATISTICS ===\n";
echo "Groups with location already set: {$stats['with_location']}\n";
echo "Groups without location: {$stats['without_location']}\n";
echo "  - Can inherit from parent: {$stats['can_inherit_from_parent']}\n";
echo "  - Can extract from name: {$stats['can_extract_from_name']}\n";
echo "  - Cannot determine: {$stats['cannot_determine']}\n";
echo "\n";

if ($mode === 'report' || $mode === 'dry-run') {
    // Show planned updates
    if (!empty($updates)) {
        echo "=== PLANNED UPDATES (" . count($updates) . " groups) ===\n";
        foreach ($updates as $update) {
            echo "  [{$update['id']}] {$update['name']}\n";
            echo "      -> \"{$update['new_location']}\" (from: {$update['source']})\n";
            if ($update['parent_name'] !== 'N/A') {
                echo "      Parent: {$update['parent_name']}\n";
            }
            echo "\n";
        }
    }

    // Show groups that cannot be determined
    if (!empty($cannotDetermine)) {
        echo "=== CANNOT DETERMINE (" . count($cannotDetermine) . " groups) ===\n";
        echo "These groups need manual location assignment:\n";
        foreach ($cannotDetermine as $group) {
            echo "  [{$group['id']}] {$group['name']} (Parent: {$group['parent_name']})\n";
        }
        echo "\n";
    }
}

if ($mode === 'execute') {
    if (empty($updates)) {
        echo "No updates to perform.\n";
        exit(0);
    }

    echo "=== EXECUTING UPDATES ===\n";
    $successCount = 0;
    $errorCount = 0;

    foreach ($updates as $update) {
        try {
            Database::query(
                "UPDATE `groups` SET location = ? WHERE id = ?",
                [$update['new_location'], $update['id']]
            );
            $successCount++;
            echo "  [OK] Updated group {$update['id']}: {$update['name']} -> {$update['new_location']}\n";
        } catch (Exception $e) {
            $errorCount++;
            echo "  [ERROR] Failed to update group {$update['id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== EXECUTION COMPLETE ===\n";
    echo "Successfully updated: {$successCount}\n";
    echo "Errors: {$errorCount}\n";
}

if ($mode === 'dry-run') {
    echo "\n=== DRY RUN COMPLETE ===\n";
    echo "No changes were made. Run with --execute to apply changes.\n";
    echo "Run with --report for detailed analysis.\n";
}

echo "\nDone.\n";
