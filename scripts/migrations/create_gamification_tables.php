<?php
/**
 * Migration: Create Gamification Tables
 *
 * Creates all tables required for the gamification system including:
 * - Badges and user badges
 * - Daily rewards and streaks
 * - Challenges and progress tracking
 * - Leaderboards and seasons
 * - XP history and notifications
 * - Achievement campaigns
 *
 * Run: php scripts/migrations/create_gamification_tables.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use Nexus\Core\Database;

echo "=================================================\n";
echo "  Gamification Tables Migration\n";
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
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if a column exists in a table
 * Note: Table name uses backticks (safe from hardcoded arrays), column uses prepared param
 */
function columnExists($db, $table, $column) {
    try {
        // Table name is from hardcoded arrays only - validated at callsite
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// ============================================================
// BADGES SYSTEM
// ============================================================

echo "[1/15] Creating badges table...\n";
if (!tableExists($db, 'badges')) {
    try {
        $db->exec("
            CREATE TABLE badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                badge_key VARCHAR(100) NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                icon VARCHAR(100) DEFAULT 'fa-award',
                color VARCHAR(20) DEFAULT '#6366f1',
                image_url VARCHAR(500),
                xp_value INT DEFAULT 0,
                rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
                category VARCHAR(50) DEFAULT 'general',
                sort_order INT DEFAULT 0,
                is_hidden TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_badge_key (tenant_id, badge_key),
                INDEX idx_tenant (tenant_id),
                INDEX idx_category (category),
                INDEX idx_rarity (rarity),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'badges';
        echo "   ✓ Created badges table\n";
    } catch (Exception $e) {
        $failed['badges'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'badges';
    echo "   → Table already exists\n";
}

echo "[2/15] Creating user_badges table...\n";
if (!tableExists($db, 'user_badges')) {
    try {
        $db->exec("
            CREATE TABLE user_badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                badge_id INT,
                badge_key VARCHAR(100),
                progress INT DEFAULT 100,
                awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                seen_at DATETIME,
                UNIQUE KEY unique_user_badge (user_id, badge_key),
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id),
                INDEX idx_badge (badge_id),
                INDEX idx_awarded (awarded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'user_badges';
        echo "   ✓ Created user_badges table\n";
    } catch (Exception $e) {
        $failed['user_badges'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'user_badges';
    echo "   → Table already exists\n";
}

// ============================================================
// DAILY REWARDS & STREAKS
// ============================================================

echo "[3/15] Creating daily_rewards table...\n";
if (!tableExists($db, 'daily_rewards')) {
    try {
        $db->exec("
            CREATE TABLE daily_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                reward_date DATE NOT NULL,
                xp_earned INT DEFAULT 0,
                streak_day INT DEFAULT 1,
                milestone_bonus INT DEFAULT 0,
                bonus_type VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_daily_reward (tenant_id, user_id, reward_date),
                INDEX idx_user (user_id),
                INDEX idx_date (reward_date),
                INDEX idx_tenant_date (tenant_id, reward_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'daily_rewards';
        echo "   ✓ Created daily_rewards table\n";
    } catch (Exception $e) {
        $failed['daily_rewards'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'daily_rewards';
    echo "   → Table already exists\n";
}

echo "[4/15] Creating user_streaks table...\n";
if (!tableExists($db, 'user_streaks')) {
    try {
        $db->exec("
            CREATE TABLE user_streaks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                streak_type VARCHAR(50) NOT NULL DEFAULT 'login',
                current_streak INT DEFAULT 0,
                longest_streak INT DEFAULT 0,
                last_activity_date DATE,
                streak_started_at DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_streak (tenant_id, user_id, streak_type),
                INDEX idx_user (user_id),
                INDEX idx_type (streak_type),
                INDEX idx_current (current_streak)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'user_streaks';
        echo "   ✓ Created user_streaks table\n";
    } catch (Exception $e) {
        $failed['user_streaks'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'user_streaks';
    echo "   → Table already exists\n";
}

// ============================================================
// XP SYSTEM
// ============================================================

echo "[5/15] Creating xp_history table...\n";
if (!tableExists($db, 'xp_history')) {
    try {
        $db->exec("
            CREATE TABLE xp_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                xp_amount INT NOT NULL,
                reason VARCHAR(100) NOT NULL,
                description TEXT,
                reference_type VARCHAR(50),
                reference_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_reason (reason),
                INDEX idx_created (created_at),
                INDEX idx_reference (reference_type, reference_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'xp_history';
        echo "   ✓ Created xp_history table\n";
    } catch (Exception $e) {
        $failed['xp_history'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'xp_history';
    echo "   → Table already exists\n";
}

echo "[6/15] Creating xp_notifications table...\n";
if (!tableExists($db, 'xp_notifications')) {
    try {
        $db->exec("
            CREATE TABLE xp_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                xp_amount INT NOT NULL,
                reason VARCHAR(100),
                description TEXT,
                is_read TINYINT(1) DEFAULT 0,
                shown_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_unread (user_id, is_read),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'xp_notifications';
        echo "   ✓ Created xp_notifications table\n";
    } catch (Exception $e) {
        $failed['xp_notifications'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'xp_notifications';
    echo "   → Table already exists\n";
}

// ============================================================
// CHALLENGES
// ============================================================

echo "[7/15] Creating challenges table...\n";
if (!tableExists($db, 'challenges')) {
    try {
        $db->exec("
            CREATE TABLE challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                challenge_type ENUM('daily', 'weekly', 'monthly', 'special', 'community') DEFAULT 'weekly',
                action_type VARCHAR(50) NOT NULL DEFAULT 'transaction',
                target_count INT DEFAULT 1,
                xp_reward INT DEFAULT 50,
                badge_reward VARCHAR(100),
                bonus_rewards TEXT,
                difficulty ENUM('easy', 'medium', 'hard', 'extreme') DEFAULT 'medium',
                start_date DATE,
                end_date DATE,
                is_featured TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_type (challenge_type),
                INDEX idx_action (action_type),
                INDEX idx_active (is_active),
                INDEX idx_dates (start_date, end_date),
                INDEX idx_featured (is_featured)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'challenges';
        echo "   ✓ Created challenges table\n";
    } catch (Exception $e) {
        $failed['challenges'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'challenges';
    echo "   → Table already exists\n";
}

echo "[8/15] Creating user_challenge_progress table...\n";
if (!tableExists($db, 'user_challenge_progress')) {
    try {
        $db->exec("
            CREATE TABLE user_challenge_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                challenge_id INT NOT NULL,
                current_count INT DEFAULT 0,
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME,
                reward_claimed TINYINT(1) DEFAULT 0,
                reward_claimed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_challenge (user_id, challenge_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id),
                INDEX idx_challenge (challenge_id),
                INDEX idx_completed (completed_at),
                INDEX idx_claimed (reward_claimed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'user_challenge_progress';
        echo "   ✓ Created user_challenge_progress table\n";
    } catch (Exception $e) {
        $failed['user_challenge_progress'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'user_challenge_progress';
    echo "   → Table already exists\n";
}

echo "[9/15] Creating friend_challenges table...\n";
if (!tableExists($db, 'friend_challenges')) {
    try {
        $db->exec("
            CREATE TABLE friend_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                challenger_id INT NOT NULL,
                challenged_id INT NOT NULL,
                challenge_type VARCHAR(50) DEFAULT 'xp_race',
                title VARCHAR(200),
                description TEXT,
                target_value INT DEFAULT 100,
                challenger_progress INT DEFAULT 0,
                challenged_progress INT DEFAULT 0,
                wager_amount INT DEFAULT 0,
                start_date DATETIME,
                end_date DATETIME,
                status ENUM('pending', 'active', 'completed', 'expired', 'declined', 'cancelled') DEFAULT 'pending',
                winner_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_challenger (challenger_id),
                INDEX idx_challenged (challenged_id),
                INDEX idx_status (status),
                INDEX idx_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'friend_challenges';
        echo "   ✓ Created friend_challenges table\n";
    } catch (Exception $e) {
        $failed['friend_challenges'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'friend_challenges';
    echo "   → Table already exists\n";
}

// ============================================================
// LEADERBOARDS & SEASONS
// ============================================================

echo "[10/15] Creating leaderboard_seasons table...\n";
if (!tableExists($db, 'leaderboard_seasons')) {
    try {
        $db->exec("
            CREATE TABLE leaderboard_seasons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                reward_pool TEXT,
                is_active TINYINT(1) DEFAULT 1,
                is_finalized TINYINT(1) DEFAULT 0,
                finalized_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_active (is_active),
                INDEX idx_dates (start_date, end_date),
                INDEX idx_finalized (is_finalized)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'leaderboard_seasons';
        echo "   ✓ Created leaderboard_seasons table\n";
    } catch (Exception $e) {
        $failed['leaderboard_seasons'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'leaderboard_seasons';
    echo "   → Table already exists\n";
}

echo "[11/15] Creating weekly_rank_snapshots table...\n";
if (!tableExists($db, 'weekly_rank_snapshots')) {
    try {
        $db->exec("
            CREATE TABLE weekly_rank_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                season_id INT,
                rank_position INT NOT NULL,
                xp INT DEFAULT 0,
                xp_gained_this_period INT DEFAULT 0,
                snapshot_date DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant_date (tenant_id, snapshot_date),
                INDEX idx_user (user_id),
                INDEX idx_season (season_id),
                INDEX idx_rank (rank_position),
                UNIQUE KEY unique_snapshot (tenant_id, user_id, snapshot_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'weekly_rank_snapshots';
        echo "   ✓ Created weekly_rank_snapshots table\n";
    } catch (Exception $e) {
        $failed['weekly_rank_snapshots'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'weekly_rank_snapshots';
    echo "   → Table already exists\n";
}

// ============================================================
// ACHIEVEMENT CAMPAIGNS
// ============================================================

echo "[12/15] Creating achievement_campaigns table...\n";
if (!tableExists($db, 'achievement_campaigns')) {
    try {
        $db->exec("
            CREATE TABLE achievement_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                campaign_type ENUM('one_time', 'recurring', 'milestone', 'event') DEFAULT 'one_time',
                criteria_type VARCHAR(50) NOT NULL,
                criteria_config TEXT,
                reward_type ENUM('xp', 'badge', 'both', 'custom') DEFAULT 'badge',
                reward_config TEXT,
                start_date DATE,
                end_date DATE,
                is_recurring TINYINT(1) DEFAULT 0,
                recurrence_schedule VARCHAR(50),
                last_processed_at DATETIME,
                is_active TINYINT(1) DEFAULT 1,
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_type (campaign_type),
                INDEX idx_active (is_active),
                INDEX idx_dates (start_date, end_date),
                INDEX idx_recurring (is_recurring)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'achievement_campaigns';
        echo "   ✓ Created achievement_campaigns table\n";
    } catch (Exception $e) {
        $failed['achievement_campaigns'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'achievement_campaigns';
    echo "   → Table already exists\n";
}

echo "[13/15] Creating campaign_awards table...\n";
if (!tableExists($db, 'campaign_awards')) {
    try {
        $db->exec("
            CREATE TABLE campaign_awards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                campaign_id INT NOT NULL,
                user_id INT NOT NULL,
                award_type VARCHAR(50) DEFAULT 'badge',
                award_value TEXT,
                awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_campaign_award (campaign_id, user_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_campaign (campaign_id),
                INDEX idx_user (user_id),
                INDEX idx_awarded (awarded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'campaign_awards';
        echo "   ✓ Created campaign_awards table\n";
    } catch (Exception $e) {
        $failed['campaign_awards'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'campaign_awards';
    echo "   → Table already exists\n";
}

// ============================================================
// ANALYTICS
// ============================================================

echo "[14/15] Creating achievement_analytics table...\n";
if (!tableExists($db, 'achievement_analytics')) {
    try {
        $db->exec("
            CREATE TABLE achievement_analytics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                date DATE NOT NULL,
                metric_name VARCHAR(100) NOT NULL,
                metric_value INT DEFAULT 0,
                metadata TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_metric (tenant_id, date, metric_name),
                INDEX idx_tenant_date (tenant_id, date),
                INDEX idx_metric (metric_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $created[] = 'achievement_analytics';
        echo "   ✓ Created achievement_analytics table\n";
    } catch (Exception $e) {
        $failed['achievement_analytics'] = $e->getMessage();
        echo "   ✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    $existed[] = 'achievement_analytics';
    echo "   → Table already exists\n";
}

// ============================================================
// USER TABLE MODIFICATIONS
// ============================================================

echo "[15/15] Checking users table columns...\n";

$userColumns = [
    'xp' => 'INT DEFAULT 0',
    'level' => 'INT DEFAULT 1',
    'login_streak' => 'INT DEFAULT 0',
    'longest_streak' => 'INT DEFAULT 0',
    'last_xp_awarded_at' => 'DATETIME',
    'gamification_enabled' => 'TINYINT(1) DEFAULT 1',
];

foreach ($userColumns as $column => $definition) {
    if (!columnExists($db, 'users', $column)) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
            echo "   ✓ Added '{$column}' column to users table\n";
            $created[] = "users.{$column}";
        } catch (Exception $e) {
            echo "   ✗ Failed to add '{$column}': " . $e->getMessage() . "\n";
            $failed["users.{$column}"] = $e->getMessage();
        }
    } else {
        echo "   → Column '{$column}' already exists\n";
        $existed[] = "users.{$column}";
    }
}

// ============================================================
// SEED DEFAULT BADGES (if badges table was just created)
// ============================================================

if (in_array('badges', $created)) {
    echo "\nSeeding default badges...\n";

    try {
        // Get all active tenants
        $tenants = $db->query("SELECT id FROM tenants")->fetchAll(PDO::FETCH_COLUMN);

        $defaultBadges = [
            // Streak badges
            ['streak_7', 'Week Warrior', 'Logged in 7 days in a row', 'fa-fire', '#ef4444', 'common', 'streak', 25],
            ['streak_14', 'Fortnight Fighter', 'Logged in 14 days in a row', 'fa-fire-flame-curved', '#f97316', 'uncommon', 'streak', 50],
            ['streak_30', 'Monthly Master', 'Logged in 30 days in a row', 'fa-fire-flame-simple', '#eab308', 'rare', 'streak', 100],
            ['streak_60', 'Dedication Champion', 'Logged in 60 days in a row', 'fa-meteor', '#a855f7', 'epic', 'streak', 200],
            ['streak_90', 'Legendary Loyalist', 'Logged in 90 days in a row', 'fa-crown', '#f59e0b', 'legendary', 'streak', 500],

            // Activity badges
            ['first_transaction', 'First Exchange', 'Completed your first transaction', 'fa-handshake', '#10b981', 'common', 'activity', 10],
            ['helper_10', 'Helping Hand', 'Completed 10 transactions', 'fa-hands-helping', '#3b82f6', 'uncommon', 'activity', 50],
            ['helper_50', 'Community Pillar', 'Completed 50 transactions', 'fa-building-columns', '#6366f1', 'rare', 'activity', 150],
            ['helper_100', 'Timebank Hero', 'Completed 100 transactions', 'fa-trophy', '#f59e0b', 'epic', 'activity', 300],

            // Social badges
            ['first_connection', 'Making Friends', 'Made your first connection', 'fa-user-plus', '#ec4899', 'common', 'social', 10],
            ['social_butterfly', 'Social Butterfly', 'Connected with 25 members', 'fa-users', '#8b5cf6', 'uncommon', 'social', 75],
            ['network_builder', 'Network Builder', 'Connected with 100 members', 'fa-diagram-project', '#06b6d4', 'rare', 'social', 200],

            // Content badges
            ['first_listing', 'Offering Help', 'Created your first listing', 'fa-store', '#22c55e', 'common', 'content', 15],
            ['listing_master', 'Listing Master', 'Created 10 listings', 'fa-list-check', '#14b8a6', 'uncommon', 'content', 75],

            // Special badges
            ['early_adopter', 'Early Adopter', 'Joined during the early days', 'fa-rocket', '#6366f1', 'rare', 'special', 100],
            ['volunteer_star', 'Volunteer Star', 'Logged 50 volunteer hours', 'fa-star', '#f59e0b', 'epic', 'volunteer', 250],
        ];

        $insertStmt = $db->prepare("
            INSERT IGNORE INTO badges (tenant_id, badge_key, name, description, icon, color, rarity, category, xp_value)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $badgeCount = 0;
        foreach ($tenants as $tenantId) {
            foreach ($defaultBadges as $badge) {
                $insertStmt->execute(array_merge([$tenantId], $badge));
                $badgeCount++;
            }
        }

        echo "   ✓ Seeded {$badgeCount} default badges across " . count($tenants) . " tenant(s)\n";
    } catch (Exception $e) {
        echo "   ✗ Failed to seed badges: " . $e->getMessage() . "\n";
    }
}

// ============================================================
// SUMMARY
// ============================================================

echo "\n=================================================\n";
echo "  Migration Complete!\n";
echo "=================================================\n\n";

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
