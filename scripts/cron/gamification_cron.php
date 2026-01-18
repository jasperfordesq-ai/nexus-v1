<?php

/**
 * Gamification Cron Jobs
 *
 * Run this script via cron for recurring gamification tasks.
 *
 * Recommended crontab entries:
 *
 * # Daily tasks at 6:00 AM
 * 0 6 * * * php /path/to/scripts/cron/gamification_cron.php daily
 *
 * # Weekly digest on Mondays at 8:00 AM
 * 0 8 * * 1 php /path/to/scripts/cron/gamification_cron.php weekly_digest
 *
 * # Hourly campaign processing
 * 0 * * * * php /path/to/scripts/cron/gamification_cron.php campaigns
 *
 * # Leaderboard snapshot daily at midnight
 * 0 0 * * * php /path/to/scripts/cron/gamification_cron.php leaderboard_snapshot
 *
 * # Challenge expiry check every hour
 * 30 * * * * php /path/to/scripts/cron/gamification_cron.php check_challenges
 */

// Bootstrap the application
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\GamificationService;
use Nexus\Services\GamificationEmailService;
use Nexus\Services\AchievementCampaignService;
use Nexus\Services\DailyRewardService;
use Nexus\Services\ChallengeService;

// Get command argument - supports both CLI and internal (include) execution
$command = $GLOBALS['argv'][1] ?? $argv[1] ?? 'help';

// Log function (with function_exists check to prevent redefinition on multiple includes)
if (!function_exists('gamificationLog')) {
    function gamificationLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
        error_log("[Gamification Cron] {$message}");
    }
}

// Check if gamification tables exist (with function_exists check)
if (!function_exists('gamificationTableExists')) {
    function gamificationTableExists($table) {
        try {
            Database::query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

// Ensure required tables exist (with function_exists check)
if (!function_exists('gamificationEnsureTables')) {
    function gamificationEnsureTables() {
        $requiredTables = [
            'daily_rewards' => "CREATE TABLE IF NOT EXISTS daily_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                reward_date DATE NOT NULL,
                xp_earned INT DEFAULT 0,
                streak_day INT DEFAULT 1,
                milestone_bonus INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_daily (tenant_id, user_id, reward_date),
                INDEX idx_user (user_id),
                INDEX idx_date (reward_date)
            )",
            'weekly_rank_snapshots' => "CREATE TABLE IF NOT EXISTS weekly_rank_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                rank_position INT NOT NULL,
                xp INT DEFAULT 0,
                snapshot_date DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant_date (tenant_id, snapshot_date),
                INDEX idx_user (user_id)
            )",
            'leaderboard_seasons' => "CREATE TABLE IF NOT EXISTS leaderboard_seasons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                name VARCHAR(100),
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_finalized TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_dates (start_date, end_date)
            )",
            'challenges' => "CREATE TABLE IF NOT EXISTS challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                challenge_type VARCHAR(50) DEFAULT 'weekly',
                action_type VARCHAR(50) NOT NULL DEFAULT 'transaction',
                target_count INT DEFAULT 1,
                xp_reward INT DEFAULT 50,
                badge_reward VARCHAR(100),
                start_date DATE,
                end_date DATE,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_active (is_active),
                INDEX idx_action (action_type)
            )",
            'friend_challenges' => "CREATE TABLE IF NOT EXISTS friend_challenges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                challenger_id INT NOT NULL,
                challenged_id INT NOT NULL,
                challenge_type VARCHAR(50),
                target_value INT DEFAULT 1,
                start_date DATETIME,
                end_date DATETIME,
                status ENUM('pending', 'active', 'completed', 'expired', 'declined') DEFAULT 'pending',
                winner_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_users (challenger_id, challenged_id),
                INDEX idx_status (status)
            )",
            'xp_notifications' => "CREATE TABLE IF NOT EXISTS xp_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                xp_amount INT NOT NULL,
                reason VARCHAR(100),
                description TEXT,
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_created (created_at)
            )",
            'campaign_awards' => "CREATE TABLE IF NOT EXISTS campaign_awards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                user_id INT NOT NULL,
                awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_award (campaign_id, user_id),
                INDEX idx_user (user_id)
            )",
            'achievement_analytics' => "CREATE TABLE IF NOT EXISTS achievement_analytics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                date DATE NOT NULL,
                metric_name VARCHAR(100) NOT NULL,
                metric_value INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_metric (tenant_id, date, metric_name),
                INDEX idx_tenant_date (tenant_id, date)
            )",
            'user_challenge_progress' => "CREATE TABLE IF NOT EXISTS user_challenge_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                challenge_id INT NOT NULL,
                current_count INT DEFAULT 0,
                completed_at DATETIME,
                reward_claimed TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_progress (user_id, challenge_id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id),
                INDEX idx_challenge (challenge_id)
            )",
            'user_streaks' => "CREATE TABLE IF NOT EXISTS user_streaks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                streak_type VARCHAR(50) NOT NULL,
                current_streak INT DEFAULT 0,
                longest_streak INT DEFAULT 0,
                last_activity_date DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_streak (tenant_id, user_id, streak_type),
                INDEX idx_user (user_id)
            )",
            'xp_history' => "CREATE TABLE IF NOT EXISTS xp_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                xp_amount INT NOT NULL,
                reason VARCHAR(100),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_created (created_at),
                INDEX idx_reason (reason)
            )",
            'user_badges' => "CREATE TABLE IF NOT EXISTS user_badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                user_id INT NOT NULL,
                badge_id INT,
                badge_key VARCHAR(100),
                awarded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_badge (user_id, badge_key),
                INDEX idx_tenant (tenant_id),
                INDEX idx_user (user_id)
            )",
            'badges' => "CREATE TABLE IF NOT EXISTS badges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                badge_key VARCHAR(100) NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                icon VARCHAR(50),
                color VARCHAR(20),
                xp_value INT DEFAULT 0,
                rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') DEFAULT 'common',
                category VARCHAR(50),
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_key (tenant_id, badge_key),
                INDEX idx_tenant (tenant_id),
                INDEX idx_category (category)
            )",
            'achievement_campaigns' => "CREATE TABLE IF NOT EXISTS achievement_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                campaign_type VARCHAR(50) DEFAULT 'one_time',
                criteria_type VARCHAR(50),
                criteria_value TEXT,
                reward_type VARCHAR(50) DEFAULT 'badge',
                reward_value TEXT,
                start_date DATE,
                end_date DATE,
                is_recurring TINYINT(1) DEFAULT 0,
                recurrence_schedule VARCHAR(50),
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_active (is_active)
            )",
        ];

        $created = [];
        foreach ($requiredTables as $table => $createSql) {
            if (!gamificationTableExists($table)) {
                try {
                    Database::query($createSql);
                    $created[] = $table;
                    gamificationLog("Created missing table: {$table}");
                } catch (\Throwable $e) {
                    gamificationLog("Failed to create table {$table}: " . $e->getMessage());
                }
            }
        }

        // Also ensure users table has login_streak column
        try {
            Database::query("SELECT login_streak FROM users LIMIT 1");
        } catch (\Throwable $e) {
            try {
                Database::query("ALTER TABLE users ADD COLUMN login_streak INT DEFAULT 0");
                gamificationLog("Added login_streak column to users table");
            } catch (\Throwable $e2) {
                // Column may already exist or other issue
            }
        }

        // Ensure users table has xp column
        try {
            Database::query("SELECT xp FROM users LIMIT 1");
        } catch (\Throwable $e) {
            try {
                Database::query("ALTER TABLE users ADD COLUMN xp INT DEFAULT 0");
                gamificationLog("Added xp column to users table");
            } catch (\Throwable $e2) {
                // Column may already exist or other issue
            }
        }

        return $created;
    }
}

// Run table check before processing commands (except help)
if ($command !== 'help') {
    try {
        gamificationEnsureTables();
    } catch (\Throwable $e) {
        gamificationLog("Warning: Could not ensure tables exist: " . $e->getMessage());
    }
}

// Process all tenants (with function_exists check to prevent redefinition on multiple includes)
if (!function_exists('gamificationForEachTenant')) {
    function gamificationForEachTenant(callable $callback) {
        $tenants = Database::query("SELECT id, slug FROM tenants")->fetchAll();

        foreach ($tenants as $tenant) {
            try {
                TenantContext::setById($tenant['id']);
                gamificationLog("Processing tenant: {$tenant['slug']} (ID: {$tenant['id']})");
                $callback($tenant);
            } catch (\Throwable $e) {
                gamificationLog("Error processing tenant {$tenant['id']}: " . $e->getMessage());
            }
        }
    }
}

switch ($command) {
    case 'daily':
        gamificationLog("Starting daily gamification tasks...");

        gamificationForEachTenant(function($tenant) {
            // Process streak resets for users who didn't log in
            // Use COALESCE to handle both last_login_at and created_at for compatibility
            $result = Database::query(
                "UPDATE users
                 SET login_streak = 0
                 WHERE tenant_id = ?
                 AND COALESCE(last_login_at, created_at) < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                 AND login_streak > 0",
                [$tenant['id']]
            );
            gamificationLog("Reset {$result->rowCount()} user streaks for tenant {$tenant['id']}");

            // Award daily bonuses to users who logged in today
            $activeUsers = Database::query(
                "SELECT id FROM users
                 WHERE tenant_id = ? AND DATE(COALESCE(last_login_at, created_at)) = CURDATE()
                 AND id NOT IN (
                     SELECT user_id FROM daily_rewards
                     WHERE tenant_id = ? AND reward_date = CURDATE()
                 )",
                [$tenant['id'], $tenant['id']]
            )->fetchAll();

            $bonusCount = 0;
            foreach ($activeUsers as $user) {
                try {
                    DailyRewardService::checkAndAwardDailyReward($user['id']);
                    $bonusCount++;
                } catch (\Throwable $e) {
                    // User may have already claimed or other error
                    gamificationLog("Warning awarding daily bonus to user {$user['id']}: " . $e->getMessage());
                }
            }
            gamificationLog("Awarded daily bonuses to {$bonusCount} users");

            // Check and award milestone badges - ONLY for users active in last 24 hours
            // This prevents the cron from processing thousands of inactive users
            $users = Database::query(
                "SELECT id FROM users
                 WHERE tenant_id = ?
                 AND is_approved = 1
                 AND COALESCE(last_login_at, created_at) > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 LIMIT 200",
                [$tenant['id']]
            )->fetchAll();

            $badgesAwarded = 0;
            $processed = 0;
            foreach ($users as $user) {
                try {
                    $beforeCount = Database::query(
                        "SELECT COUNT(*) as c FROM user_badges WHERE user_id = ?",
                        [$user['id']]
                    )->fetch()['c'];

                    GamificationService::runAllBadgeChecks($user['id']);

                    $afterCount = Database::query(
                        "SELECT COUNT(*) as c FROM user_badges WHERE user_id = ?",
                        [$user['id']]
                    )->fetch()['c'];

                    $badgesAwarded += ($afterCount - $beforeCount);
                    $processed++;
                } catch (\Throwable $e) {
                    // Continue
                }
            }
            gamificationLog("Processed {$processed} active users, awarded {$badgesAwarded} badges");
        });

        gamificationLog("Daily tasks completed.");
        break;

    case 'weekly_digest':
        gamificationLog("Sending weekly progress digests...");

        gamificationForEachTenant(function($tenant) {
            $result = GamificationEmailService::sendWeeklyDigests();
            gamificationLog("Tenant {$tenant['id']}: Sent {$result['sent']}, Skipped {$result['skipped']}, Failed {$result['failed']}");
        });

        gamificationLog("Weekly digests completed.");
        break;

    case 'campaigns':
        gamificationLog("Processing recurring campaigns...");

        gamificationForEachTenant(function($tenant) {
            $results = AchievementCampaignService::processRecurringCampaigns();

            foreach ($results as $campaignId => $result) {
                if ($result['success']) {
                    gamificationLog("Campaign {$campaignId}: Awarded to {$result['awarded']} users");
                } else {
                    gamificationLog("Campaign {$campaignId}: Failed - {$result['error']}");
                }
            }
        });

        gamificationLog("Campaign processing completed.");
        break;

    case 'leaderboard_snapshot':
        gamificationLog("Creating leaderboard snapshots...");

        gamificationForEachTenant(function($tenant) {
            // Create weekly rank snapshot (use INSERT IGNORE to handle re-runs on same day)
            Database::query(
                "INSERT IGNORE INTO weekly_rank_snapshots (tenant_id, user_id, rank_position, xp, snapshot_date)
                 SELECT ?, id, @rank := @rank + 1, xp, CURDATE()
                 FROM users, (SELECT @rank := 0) r
                 WHERE tenant_id = ? AND is_approved = 1
                 ORDER BY xp DESC",
                [$tenant['id'], $tenant['id']]
            );

            // Finalize ended seasons
            $endedSeasons = Database::query(
                "SELECT id FROM leaderboard_seasons
                 WHERE tenant_id = ? AND end_date < CURDATE() AND is_finalized = 0",
                [$tenant['id']]
            )->fetchAll();

            foreach ($endedSeasons as $season) {
                // Award top performers
                $topUsers = Database::query(
                    "SELECT user_id, rank_position FROM weekly_rank_snapshots
                     WHERE tenant_id = ? AND snapshot_date = (SELECT end_date FROM leaderboard_seasons WHERE id = ?)
                     ORDER BY rank_position ASC LIMIT 10",
                    [$tenant['id'], $season['id']]
                )->fetchAll();

                $rewards = [1 => 500, 2 => 300, 3 => 200, 4 => 100, 5 => 100];
                foreach ($topUsers as $user) {
                    $xp = $rewards[$user['rank_position']] ?? 50;
                    GamificationService::awardXP(
                        $user['user_id'],
                        $xp,
                        'season_reward',
                        "Season #{$season['id']} rank #{$user['rank_position']} reward"
                    );
                }

                Database::query(
                    "UPDATE leaderboard_seasons SET is_finalized = 1 WHERE id = ?",
                    [$season['id']]
                );

                gamificationLog("Finalized season {$season['id']} and awarded rewards");
            }

            gamificationLog("Leaderboard snapshot created for tenant {$tenant['id']}");
        });

        gamificationLog("Leaderboard snapshots completed.");
        break;

    case 'check_challenges':
        gamificationLog("Checking challenge expirations...");

        gamificationForEachTenant(function($tenant) {
            // Expire challenges past end date
            $expired = Database::query(
                "UPDATE challenges SET is_active = 0
                 WHERE tenant_id = ? AND end_date < CURDATE() AND is_active = 1",
                [$tenant['id']]
            );
            gamificationLog("Expired {$expired->rowCount()} challenges");

            // Expire friend challenges
            $expiredFriend = Database::query(
                "UPDATE friend_challenges SET status = 'expired'
                 WHERE tenant_id = ? AND end_date < CURDATE() AND status IN ('pending', 'active')",
                [$tenant['id']]
            );
            gamificationLog("Expired {$expiredFriend->rowCount()} friend challenges");

            // Auto-complete timed challenges (if method exists)
            try {
                if (method_exists(ChallengeService::class, 'autoCompleteTimedChallenges')) {
                    ChallengeService::autoCompleteTimedChallenges();
                }
            } catch (\Throwable $e) {
                gamificationLog("Warning during auto-complete: " . $e->getMessage());
            }
        });

        gamificationLog("Challenge check completed.");
        break;

    case 'streak_milestones':
        gamificationLog("Checking streak milestones...");

        gamificationForEachTenant(function($tenant) {
            // Check for streak milestone badges
            $milestones = [7, 14, 30, 60, 90, 180, 365];

            foreach ($milestones as $days) {
                $users = Database::query(
                    "SELECT id FROM users
                     WHERE tenant_id = ? AND login_streak = ?",
                    [$tenant['id'], $days]
                )->fetchAll();

                foreach ($users as $user) {
                    $badgeKey = "streak_{$days}";
                    GamificationService::awardBadge($user['id'], $badgeKey);

                    // Send milestone email
                    GamificationEmailService::sendMilestoneEmail($user['id'], 'streak_milestone', [
                        'streak_days' => $days
                    ]);
                }

                if (count($users) > 0) {
                    gamificationLog("Awarded {$days}-day streak badge to " . count($users) . " users");
                }
            }
        });

        gamificationLog("Streak milestone check completed.");
        break;

    case 'cleanup':
        gamificationLog("Running cleanup tasks...");

        gamificationForEachTenant(function($tenant) {
            // Clean old XP floater notifications (older than 7 days)
            Database::query(
                "DELETE FROM xp_notifications
                 WHERE tenant_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenant['id']]
            );

            // Clean old campaign awards tracking (older than 1 year)
            Database::query(
                "DELETE FROM campaign_awards
                 WHERE awarded_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
            );

            // Archive old analytics data
            Database::query(
                "DELETE FROM achievement_analytics
                 WHERE tenant_id = ? AND date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)",
                [$tenant['id']]
            );

            gamificationLog("Cleanup completed for tenant {$tenant['id']}");
        });

        gamificationLog("Cleanup tasks completed.");
        break;

    case 'help':
    default:
        echo <<<HELP
Gamification Cron Jobs

Usage: php gamification_cron.php <command>

Available commands:
  daily              - Run daily tasks (streak resets, badge checks)
  weekly_digest      - Send weekly progress email digests
  campaigns          - Process recurring achievement campaigns
  leaderboard_snapshot - Create leaderboard snapshots and finalize seasons
  check_challenges   - Check for expired challenges
  streak_milestones  - Award streak milestone badges
  cleanup            - Clean up old data

Recommended cron schedule:
  0 6 * * *   daily
  0 8 * * 1   weekly_digest
  0 * * * *   campaigns
  0 0 * * *   leaderboard_snapshot
  30 * * * *  check_challenges
  0 1 * * *   streak_milestones
  0 3 * * 0   cleanup

HELP;
        break;
}

// Use return instead of exit when included internally to allow the calling script to continue
if (defined('CRON_INTERNAL_RUN')) {
    return;
}
exit(0);
