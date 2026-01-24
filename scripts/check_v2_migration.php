<?php
/**
 * Check V2 Migration Status
 * Run: php scripts/check_v2_migration.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load config
$config = require dirname(__DIR__) . '/src/config.php';

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "=== Checking V2 Migration Tables ===\n\n";

    $tables = [
        'user_active_unlockables',
        'friend_challenges',
        'achievement_celebrations',
        'weekly_rank_snapshots',
        'xp_notifications',
        'gamification_tour_completions',
        'user_email_preferences'
    ];

    $missing = [];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $result = $stmt->fetch();
        $status = $result ? '✓ EXISTS' : '✗ MISSING';
        echo "{$status}: {$table}\n";
        if (!$result) $missing[] = $table;
    }

    echo "\n=== Checking Column Additions ===\n\n";

    // Check achievement_campaigns columns
    $columns = ['target_audience', 'audience_config', 'schedule', 'activated_at', 'last_run_at', 'total_awards'];
    foreach ($columns as $col) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM achievement_campaigns LIKE ?");
            $stmt->execute([$col]);
            $result = $stmt->fetch();
            $status = $result ? '✓ EXISTS' : '✗ MISSING';
            echo "{$status}: achievement_campaigns.{$col}\n";
            if (!$result) $missing[] = "achievement_campaigns.{$col}";
        } catch (Exception $e) {
            echo "✗ TABLE MISSING: achievement_campaigns\n";
            $missing[] = "achievement_campaigns (table)";
            break;
        }
    }

    // Check users columns
    $userCols = ['login_streak', 'last_daily_reward', 'email_preferences'];
    foreach ($userCols as $col) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
        $stmt->execute([$col]);
        $result = $stmt->fetch();
        $status = $result ? '✓ EXISTS' : '✗ MISSING';
        echo "{$status}: users.{$col}\n";
        if (!$result) $missing[] = "users.{$col}";
    }

    echo "\n=== Summary ===\n";
    if (empty($missing)) {
        echo "✓ All V2 migration items are present!\n";
    } else {
        echo "✗ Missing items (" . count($missing) . "):\n";
        foreach ($missing as $item) {
            echo "  - {$item}\n";
        }
        echo "\nPlease run: Documents/GAMIFICATION_ENHANCEMENTS_V2_MIGRATION.sql\n";
    }

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
