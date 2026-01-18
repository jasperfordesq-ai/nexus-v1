-- ============================================================
-- Gamification System Tables
-- ============================================================
-- Run this script to create all gamification tables.
-- Safe to run multiple times (uses IF NOT EXISTS).
--
-- Usage:
--   mysql -u username -p database_name < gamification_tables.sql
-- ============================================================

-- ============================================================
-- BADGES SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS badges (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_badges (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DAILY REWARDS & STREAKS
-- ============================================================

CREATE TABLE IF NOT EXISTS daily_rewards (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_streaks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- XP SYSTEM
-- ============================================================

CREATE TABLE IF NOT EXISTS xp_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS xp_notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CHALLENGES
-- ============================================================

CREATE TABLE IF NOT EXISTS challenges (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_challenge_progress (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS friend_challenges (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LEADERBOARDS & SEASONS
-- ============================================================

CREATE TABLE IF NOT EXISTS leaderboard_seasons (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weekly_rank_snapshots (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ACHIEVEMENT CAMPAIGNS
-- ============================================================

CREATE TABLE IF NOT EXISTS achievement_campaigns (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_awards (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ANALYTICS
-- ============================================================

CREATE TABLE IF NOT EXISTS achievement_analytics (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- USER TABLE MODIFICATIONS
-- ============================================================
-- Run these ALTER statements if the columns don't exist.
-- You may need to run them individually if some already exist.

-- ALTER TABLE users ADD COLUMN IF NOT EXISTS xp INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS level INT DEFAULT 1;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS login_streak INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS longest_streak INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS last_xp_awarded_at DATETIME;
-- ALTER TABLE users ADD COLUMN IF NOT EXISTS gamification_enabled TINYINT(1) DEFAULT 1;

-- For MySQL < 8.0, use these instead (will error if column exists):
-- ALTER TABLE users ADD COLUMN xp INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN level INT DEFAULT 1;
-- ALTER TABLE users ADD COLUMN login_streak INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN longest_streak INT DEFAULT 0;
-- ALTER TABLE users ADD COLUMN last_xp_awarded_at DATETIME;
-- ALTER TABLE users ADD COLUMN gamification_enabled TINYINT(1) DEFAULT 1;

-- ============================================================
-- Done!
-- ============================================================
SELECT 'Gamification tables created successfully!' AS status;
