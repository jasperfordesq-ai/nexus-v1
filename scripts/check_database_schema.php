<?php
/**
 * Database Schema Verification Script
 * Checks if all required tables and columns exist for Project NEXUS TimeBank
 */

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $value = trim($value, '"\'');
            putenv("$key=$value");
        }
    }
}

// Database connection settings
$dbType = getenv('DB_TYPE') ?: 'mysql';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'project_nexus_';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

echo "============================================================\n";
echo "PROJECT NEXUS - Database Schema Verification\n";
echo "============================================================\n\n";
echo "Database: $dbName @ $dbHost:$dbPort\n";
echo "Type: $dbType\n\n";

// Connect to database
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✓ Connected to database successfully\n\n";
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Define required tables and their essential columns
$requiredSchema = [
    // Core tables
    'tenants' => [
        'id', 'name', 'domain', 'slug', 'logo_url', 'settings', 'configuration',
        'contact_email', 'contact_phone', 'address',
        'social_facebook', 'social_twitter', 'social_instagram', 'social_linkedin', 'social_youtube',
        'created_at'
    ],
    'users' => [
        'id', 'tenant_id', 'email', 'password', 'first_name', 'last_name', 'avatar_url',
        'role', 'is_approved', 'status', 'balance', 'location', 'latitude', 'longitude',
        'last_login_at', 'last_active_at', 'last_activity',
        'xp', 'level', 'login_streak', 'longest_streak', 'gamification_enabled',
        'skills', 'notification_preferences',
        'created_at', 'updated_at'
    ],
    'categories' => ['id', 'tenant_id', 'name', 'slug', 'color', 'icon', 'parent_id'],
    'listings' => [
        'id', 'tenant_id', 'user_id', 'category_id', 'title', 'description', 'type',
        'status', 'image_url', 'latitude', 'longitude', 'created_at'
    ],
    'transactions' => [
        'id', 'tenant_id', 'sender_id', 'receiver_id', 'listing_id',
        'amount', 'description', 'status', 'created_at'
    ],
    'groups' => [
        'id', 'tenant_id', 'name', 'description', 'image_url',
        'location', 'latitude', 'longitude', 'created_at'
    ],
    'events' => ['id', 'tenant_id', 'user_id', 'title', 'description', 'start_date', 'end_date'],
    'messages' => ['id', 'tenant_id', 'sender_id', 'receiver_id', 'content', 'created_at'],

    // AI Integration
    'ai_conversations' => ['id', 'tenant_id', 'user_id', 'title', 'provider', 'model', 'context_type', 'created_at'],
    'ai_messages' => ['id', 'conversation_id', 'role', 'content', 'tokens_used', 'created_at'],
    'ai_usage' => ['id', 'tenant_id', 'user_id', 'provider', 'feature', 'tokens_input', 'tokens_output', 'cost_usd'],
    'ai_content_cache' => ['id', 'tenant_id', 'cache_key', 'content', 'expires_at'],
    'ai_settings' => ['id', 'tenant_id', 'setting_key', 'setting_value', 'is_encrypted'],
    'ai_user_limits' => ['id', 'tenant_id', 'user_id', 'daily_limit', 'monthly_limit', 'daily_used', 'monthly_used'],

    // SEO Module
    'seo_redirects' => ['id', 'tenant_id', 'source_url', 'destination_url', 'hits'],
    'seo_metadata' => ['id', 'tenant_id', 'entity_type', 'entity_id', 'meta_title', 'meta_description', 'noindex'],

    // Organization Wallets
    'vol_organizations' => ['id', 'tenant_id', 'user_id', 'name', 'description'],
    'org_wallets' => ['id', 'tenant_id', 'organization_id', 'balance'],
    'org_members' => ['id', 'tenant_id', 'organization_id', 'user_id', 'role', 'status'],
    'org_transfer_requests' => ['id', 'tenant_id', 'organization_id', 'requester_id', 'recipient_id', 'amount', 'status'],
    'org_transactions' => ['id', 'tenant_id', 'organization_id', 'sender_type', 'sender_id', 'receiver_type', 'receiver_id', 'amount'],

    // Abuse Detection
    'abuse_alerts' => ['id', 'tenant_id', 'alert_type', 'severity', 'user_id', 'status'],

    // Gamification System
    'badges' => ['id', 'tenant_id', 'badge_key', 'name', 'description', 'icon', 'xp_value', 'rarity'],
    'user_badges' => ['id', 'tenant_id', 'user_id', 'badge_id', 'badge_key', 'awarded_at'],
    'daily_rewards' => ['id', 'tenant_id', 'user_id', 'reward_date', 'xp_earned', 'streak_day'],
    'user_streaks' => ['id', 'tenant_id', 'user_id', 'streak_type', 'current_streak', 'longest_streak'],
    'xp_history' => ['id', 'tenant_id', 'user_id', 'xp_amount', 'reason'],
    'xp_notifications' => ['id', 'tenant_id', 'user_id', 'xp_amount', 'is_read'],
    'challenges' => ['id', 'tenant_id', 'title', 'challenge_type', 'target_count', 'xp_reward'],
    'user_challenge_progress' => ['id', 'tenant_id', 'user_id', 'challenge_id', 'current_count'],
    'friend_challenges' => ['id', 'tenant_id', 'challenger_id', 'challenged_id', 'status'],
    'leaderboard_seasons' => ['id', 'tenant_id', 'name', 'start_date', 'end_date'],
    'weekly_rank_snapshots' => ['id', 'tenant_id', 'user_id', 'rank_position', 'xp'],
    'achievement_campaigns' => ['id', 'tenant_id', 'name', 'campaign_type', 'criteria_type'],
    'campaign_awards' => ['id', 'tenant_id', 'campaign_id', 'user_id'],
    'achievement_analytics' => ['id', 'tenant_id', 'date', 'metric_name', 'metric_value'],

    // Smart Matching
    'match_preferences' => ['id', 'user_id', 'tenant_id', 'max_distance_km', 'min_match_score', 'notification_frequency'],
    'match_cache' => ['id', 'user_id', 'listing_id', 'tenant_id', 'match_score', 'match_type', 'status'],
    'match_history' => ['id', 'user_id', 'listing_id', 'tenant_id', 'match_score', 'action'],

    // Social Feed
    'feed_posts' => ['id', 'tenant_id', 'user_id', 'content', 'visibility', 'likes_count'],

    // Help Center
    'help_articles' => ['id', 'tenant_id', 'title', 'content', 'category'],
    'help_article_feedback' => ['id', 'tenant_id', 'article_id', 'user_id', 'helpful'],

    // Newsletter
    'newsletters' => ['id', 'tenant_id', 'subject', 'content', 'status'],
    'newsletter_subscribers' => ['id', 'tenant_id', 'email', 'status'],
    'newsletter_queue' => ['id', 'newsletter_id', 'user_id', 'email', 'status'],
    'newsletter_opens' => ['id', 'newsletter_id', 'email'],
    'newsletter_clicks' => ['id', 'newsletter_id', 'email', 'url'],
];

// Get existing tables
$existingTables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $existingTables[] = $row[0];
}

// Check tables and columns
$missingTables = [];
$missingColumns = [];
$existingCorrect = [];

echo "============================================================\n";
echo "TABLE AND COLUMN CHECK\n";
echo "============================================================\n\n";

foreach ($requiredSchema as $table => $columns) {
    if (!in_array($table, $existingTables)) {
        $missingTables[] = $table;
        echo "✗ TABLE MISSING: $table\n";
        continue;
    }

    // Get existing columns for this table
    $existingColumns = [];
    $stmt = $pdo->query("DESCRIBE `$table`");
    while ($row = $stmt->fetch()) {
        $existingColumns[] = $row['Field'];
    }

    $tableMissingCols = [];
    foreach ($columns as $col) {
        if (!in_array($col, $existingColumns)) {
            $tableMissingCols[] = $col;
        }
    }

    if (!empty($tableMissingCols)) {
        $missingColumns[$table] = $tableMissingCols;
        echo "⚠ TABLE EXISTS: $table (missing columns: " . implode(', ', $tableMissingCols) . ")\n";
    } else {
        $existingCorrect[] = $table;
        echo "✓ TABLE OK: $table (" . count($columns) . " required columns present)\n";
    }
}

// Summary
echo "\n============================================================\n";
echo "SUMMARY\n";
echo "============================================================\n\n";

$totalTables = count($requiredSchema);
$okTables = count($existingCorrect);
$missingTableCount = count($missingTables);
$tablesWithMissingCols = count($missingColumns);

echo "Total Required Tables: $totalTables\n";
echo "Tables OK: $okTables\n";
echo "Tables Missing: $missingTableCount\n";
echo "Tables with Missing Columns: $tablesWithMissingCols\n\n";

if (!empty($missingTables)) {
    echo "============================================================\n";
    echo "MISSING TABLES (need to create)\n";
    echo "============================================================\n";
    foreach ($missingTables as $table) {
        echo "  - $table\n";
    }
    echo "\n";
}

if (!empty($missingColumns)) {
    echo "============================================================\n";
    echo "MISSING COLUMNS (need to add)\n";
    echo "============================================================\n";
    foreach ($missingColumns as $table => $cols) {
        echo "Table: $table\n";
        foreach ($cols as $col) {
            echo "  - $col\n";
        }
        echo "\n";
    }
}

// Recommendations
echo "============================================================\n";
echo "RECOMMENDATIONS\n";
echo "============================================================\n\n";

if (empty($missingTables) && empty($missingColumns)) {
    echo "✓ Database schema is complete! All required tables and columns exist.\n";
} else {
    echo "To fix the missing schema, run the following migration files:\n\n";

    if (!empty($missingTables)) {
        // Determine which migration files to run
        $aiTables = ['ai_conversations', 'ai_messages', 'ai_usage', 'ai_content_cache', 'ai_settings', 'ai_user_limits'];
        $gamificationTables = ['badges', 'user_badges', 'daily_rewards', 'user_streaks', 'xp_history', 'xp_notifications',
                               'challenges', 'user_challenge_progress', 'friend_challenges', 'leaderboard_seasons',
                               'weekly_rank_snapshots', 'achievement_campaigns', 'campaign_awards', 'achievement_analytics'];
        $orgTables = ['org_wallets', 'org_members', 'org_transfer_requests', 'org_transactions', 'abuse_alerts'];
        $matchingTables = ['match_preferences', 'match_cache', 'match_history'];
        $seoTables = ['seo_redirects', 'seo_metadata'];

        $needAi = !empty(array_intersect($missingTables, $aiTables));
        $needGamification = !empty(array_intersect($missingTables, $gamificationTables));
        $needOrg = !empty(array_intersect($missingTables, $orgTables));
        $needMatching = !empty(array_intersect($missingTables, $matchingTables));
        $needSeo = !empty(array_intersect($missingTables, $seoTables));
        $needFeed = in_array('feed_posts', $missingTables);

        if ($needAi) echo "  1. Run: scripts/migrations/ALL_MIGRATIONS.sql (AI tables)\n";
        if ($needGamification) echo "  2. Run: scripts/migrations/gamification_tables.sql\n";
        if ($needOrg) echo "  3. Run: scripts/migrations/LIVE_SERVER_MIGRATION_2026_01_07.sql\n";
        if ($needMatching) echo "  4. Run: scripts/migrations/SMART_MATCHING_ENGINE.sql\n";
        if ($needSeo) echo "  5. Run: scripts/migrations/SEO_MODULE_MIGRATION.sql\n";
        if ($needFeed) echo "  6. Run: scripts/migrations/create_feed_posts_table.sql\n";

        echo "\n  Or run ALL_MIGRATIONS.sql for a comprehensive update.\n";
    }

    if (!empty($missingColumns)) {
        echo "\n  For missing columns, check ALL_MIGRATIONS.sql which includes ALTER TABLE statements.\n";
    }
}

echo "\n============================================================\n";
echo "Script completed.\n";
echo "============================================================\n";
