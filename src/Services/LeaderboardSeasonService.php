<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class LeaderboardSeasonService
{
    /**
     * Get the current active season
     */
    public static function getCurrentSeason()
    {
        $tenantId = TenantContext::getId();
        $now = date('Y-m-d H:i:s');

        $season = Database::query(
            "SELECT * FROM leaderboard_seasons
             WHERE tenant_id = ? AND start_date <= ? AND end_date >= ? AND status = 'active'
             ORDER BY start_date DESC LIMIT 1",
            [$tenantId, $now, $now]
        )->fetch();

        return $season;
    }

    /**
     * Get or create the current month's season
     */
    public static function getOrCreateCurrentSeason()
    {
        $season = self::getCurrentSeason();

        if (!$season) {
            // Auto-create a monthly season
            $season = self::createMonthlySeason();
        }

        return $season;
    }

    /**
     * Create a new monthly season
     */
    public static function createMonthlySeason($month = null, $year = null)
    {
        $tenantId = TenantContext::getId();
        $month = $month ?? date('n');
        $year = $year ?? date('Y');

        $startDate = date('Y-m-01 00:00:00', strtotime("$year-$month-01"));
        $endDate = date('Y-m-t 23:59:59', strtotime("$year-$month-01"));

        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $name = $monthNames[$month] . ' ' . $year;

        // Default rewards structure
        $rewards = json_encode([
            1 => ['xp' => 500, 'badge' => 'season_champion', 'title' => 'Season Champion'],
            2 => ['xp' => 300, 'badge' => 'season_runner_up', 'title' => 'Runner Up'],
            3 => ['xp' => 200, 'badge' => 'season_third', 'title' => 'Third Place'],
            'top10' => ['xp' => 100, 'badge' => 'season_top10'],
            'top25' => ['xp' => 50],
            'participant' => ['xp' => 25]
        ]);

        Database::query(
            "INSERT INTO leaderboard_seasons (tenant_id, name, start_date, end_date, rewards, status)
             VALUES (?, ?, ?, ?, ?, 'active')",
            [$tenantId, $name, $startDate, $endDate, $rewards]
        );

        return self::getCurrentSeason();
    }

    /**
     * Get season leaderboard rankings
     */
    public static function getSeasonLeaderboard($seasonId, $limit = 50)
    {
        // Get rankings from season_rankings table
        $rankings = Database::query(
            "SELECT sr.*, u.first_name, u.last_name, u.photo, u.level
             FROM season_rankings sr
             JOIN users u ON sr.user_id = u.id
             WHERE sr.season_id = ?
             ORDER BY sr.season_xp DESC
             LIMIT ?",
            [$seasonId, $limit]
        )->fetchAll();

        // If no rankings yet, calculate from XP earned during season
        if (empty($rankings)) {
            $season = Database::query(
                "SELECT * FROM leaderboard_seasons WHERE id = ?",
                [$seasonId]
            )->fetch();

            if ($season) {
                $rankings = self::calculateSeasonRankings($season);
            }
        }

        return $rankings;
    }

    /**
     * Calculate rankings based on XP earned during season period
     */
    public static function calculateSeasonRankings($season)
    {
        $tenantId = TenantContext::getId();

        // Get XP earned during the season from xp_log if available
        // Fall back to current total XP if no log exists
        $rankings = Database::query(
            "SELECT u.id as user_id, u.first_name, u.last_name, u.photo, u.level, u.xp as season_xp
             FROM users u
             WHERE u.tenant_id = ? AND u.xp > 0
             ORDER BY u.xp DESC
             LIMIT 50",
            [$tenantId]
        )->fetchAll();

        return $rankings;
    }

    /**
     * Get user's season ranking
     */
    public static function getUserSeasonRank($userId, $seasonId = null)
    {
        if (!$seasonId) {
            $season = self::getCurrentSeason();
            $seasonId = $season['id'] ?? null;
        }

        if (!$seasonId) {
            return null;
        }

        // Check season_rankings table first
        $ranking = Database::query(
            "SELECT * FROM season_rankings WHERE season_id = ? AND user_id = ?",
            [$seasonId, $userId]
        )->fetch();

        if ($ranking) {
            // Calculate position
            $position = Database::query(
                "SELECT COUNT(*) + 1 as position FROM season_rankings
                 WHERE season_id = ? AND season_xp > ?",
                [$seasonId, $ranking['season_xp']]
            )->fetch();

            $ranking['position'] = $position['position'];
            return $ranking;
        }

        // Calculate position from users table
        $tenantId = TenantContext::getId();
        $user = Database::query("SELECT xp FROM users WHERE id = ?", [$userId])->fetch();

        if (!$user) {
            return null;
        }

        $position = Database::query(
            "SELECT COUNT(*) + 1 as position FROM users WHERE tenant_id = ? AND xp > ?",
            [$tenantId, $user['xp']]
        )->fetch();

        return [
            'user_id' => $userId,
            'season_id' => $seasonId,
            'season_xp' => $user['xp'],
            'position' => $position['position']
        ];
    }

    /**
     * End a season and distribute rewards
     */
    public static function endSeason($seasonId)
    {
        $season = Database::query(
            "SELECT * FROM leaderboard_seasons WHERE id = ?",
            [$seasonId]
        )->fetch();

        if (!$season || $season['status'] !== 'active') {
            return false;
        }

        $rewards = json_decode($season['rewards'], true);
        $rankings = self::getSeasonLeaderboard($seasonId, 100);

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            foreach ($rankings as $index => $ranking) {
                $position = $index + 1;
                $reward = null;

                if (isset($rewards[$position])) {
                    $reward = $rewards[$position];
                } elseif ($position <= 10 && isset($rewards['top10'])) {
                    $reward = $rewards['top10'];
                } elseif ($position <= 25 && isset($rewards['top25'])) {
                    $reward = $rewards['top25'];
                } elseif (isset($rewards['participant'])) {
                    $reward = $rewards['participant'];
                }

                if ($reward) {
                    // Award XP
                    if (isset($reward['xp'])) {
                        GamificationService::awardXP(
                            $ranking['user_id'],
                            $reward['xp'],
                            'season_reward',
                            "Season reward - Position #{$position}"
                        );
                    }

                    // Award badge
                    if (isset($reward['badge'])) {
                        GamificationService::awardBadge($ranking['user_id'], $reward['badge']);
                    }

                    // Store final ranking
                    Database::query(
                        "INSERT INTO season_rankings (season_id, user_id, season_xp, final_rank, rewards_claimed)
                         VALUES (?, ?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE final_rank = ?, rewards_claimed = 1",
                        [$seasonId, $ranking['user_id'], $ranking['season_xp'], $position, $position]
                    );
                }
            }

            // Mark season as completed
            Database::query(
                "UPDATE leaderboard_seasons SET status = 'completed' WHERE id = ?",
                [$seasonId]
            );

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get all seasons for display
     */
    public static function getAllSeasons($limit = 12)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT * FROM leaderboard_seasons
             WHERE tenant_id = ?
             ORDER BY start_date DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll();
    }

    /**
     * Get season with user context
     */
    public static function getSeasonWithUserData($userId)
    {
        $season = self::getOrCreateCurrentSeason();

        if (!$season) {
            return null;
        }

        $userRank = self::getUserSeasonRank($userId, $season['id']);
        $leaderboard = self::getSeasonLeaderboard($season['id'], 10);
        $rewards = json_decode($season['rewards'], true);

        // Calculate time remaining
        $endDate = strtotime($season['end_date']);
        $now = time();
        $daysRemaining = max(0, ceil(($endDate - $now) / 86400));

        return [
            'season' => $season,
            'user_rank' => $userRank,
            'leaderboard' => $leaderboard,
            'rewards' => $rewards,
            'days_remaining' => $daysRemaining,
            'is_ending_soon' => $daysRemaining <= 7
        ];
    }
}
