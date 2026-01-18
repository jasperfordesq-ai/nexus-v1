<?php
/**
 * Check if gamification tables exist in the database
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Nexus\Core\Database;

// Initialize database connection
$pdo = Database::getInstance();

// Required gamification tables
$requiredTables = [
    'daily_rewards',
    'challenges',
    'user_challenge_progress',
    'badge_collections',
    'badge_collection_items',
    'user_collection_completions',
    'leaderboard_seasons',
    'season_rankings',
    'group_achievements',
    'group_achievement_progress',
    'referral_tracking',
    'xp_shop_items',
    'user_xp_purchases',
    'custom_badges',
    'achievement_campaigns',
    'campaign_executions',
    'achievement_analytics',
    'progress_notifications',
    'campaign_awards',
];

echo "=== Gamification Tables Check ===\n\n";

$existing = [];
$missing = [];

foreach ($requiredTables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $existing[] = $table;
            echo "[OK] $table\n";
        } else {
            $missing[] = $table;
            echo "[MISSING] $table\n";
        }
    } catch (Exception $e) {
        $missing[] = $table;
        echo "[ERROR] $table - " . $e->getMessage() . "\n";
    }
}

echo "\n=== Summary ===\n";
echo "Existing: " . count($existing) . "/" . count($requiredTables) . "\n";
echo "Missing: " . count($missing) . "\n";

if (!empty($missing)) {
    echo "\nMissing tables:\n";
    foreach ($missing as $table) {
        echo "  - $table\n";
    }
    echo "\nRun the migration SQL file to create missing tables:\n";
    echo "Documents/GAMIFICATION_ENHANCEMENTS_MIGRATION.sql\n";
}

// Check user columns
echo "\n=== User Table Columns Check ===\n";
try {
    $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);

    $neededCols = ['xp', 'level', 'referral_code', 'referred_by'];
    foreach ($neededCols as $col) {
        if (in_array($col, $cols)) {
            echo "[OK] users.$col exists\n";
        } else {
            echo "[MISSING] users.$col\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking users table: " . $e->getMessage() . "\n";
}
