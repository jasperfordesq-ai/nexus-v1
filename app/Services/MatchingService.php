<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MatchingService — handles match suggestions, preferences, and interaction tracking.
 */
class MatchingService
{
    /** Default preference values */
    private const DEFAULT_PREFERENCES = [
        'max_distance_km'         => 25,
        'min_match_score'         => 50,
        'notification_frequency'  => 'fortnightly',
        'notify_hot_matches'      => true,
        'notify_mutual_matches'   => true,
        'categories'              => [],
    ];

    public function __construct()
    {
    }

    /**
     * Get match suggestions for a user.
     */
    public static function getSuggestionsForUser($userId, $limit = 5, array $options = [])
    {
        try {
            $tenantId = \App\Core\TenantContext::getId();

            return DB::select(
                "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.location, u.skills
                 FROM users u
                 WHERE u.tenant_id = ? AND u.id != ? AND u.status = 'active' AND u.is_approved = 1
                 ORDER BY RAND()
                 LIMIT ?",
                [$tenantId, $userId, $limit]
            );
        } catch (\Throwable $e) {
            Log::debug('[MatchingService] getSuggestionsForUser failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get hot matches for a user.
     */
    public static function getHotMatches($userId, $limit = 5)
    {
        return self::getSuggestionsForUser($userId, $limit) ?? [];
    }

    /**
     * Get mutual matches for a user.
     */
    public static function getMutualMatches($userId, $limit = 10)
    {
        return [];
    }

    /**
     * Get matches grouped by type.
     *
     * @return array{hot: array, good: array, mutual: array, all: array}
     */
    public static function getMatchesByType($userId)
    {
        $hot = self::getHotMatches($userId, 5);
        $mutual = self::getMutualMatches($userId, 5);

        return [
            'hot'    => is_array($hot) ? $hot : [],
            'good'   => [],
            'mutual' => is_array($mutual) ? $mutual : [],
            'all'    => is_array($hot) ? $hot : [],
        ];
    }

    /**
     * Save matching preferences for a user.
     */
    public static function savePreferences($userId, array $preferences)
    {
        try {
            $data = [
                'user_id'    => $userId,
                'updated_at' => now(),
            ];

            if (isset($preferences['max_distance_km'])) {
                $data['max_distance_km'] = (int) $preferences['max_distance_km'];
            }
            if (isset($preferences['min_match_score'])) {
                $data['min_match_score'] = (int) $preferences['min_match_score'];
            }
            if (isset($preferences['notification_frequency'])) {
                $data['notification_frequency'] = $preferences['notification_frequency'];
            }
            if (isset($preferences['notify_hot_matches'])) {
                $data['notify_hot_matches'] = $preferences['notify_hot_matches'] ? 1 : 0;
            }
            if (isset($preferences['notify_mutual_matches'])) {
                $data['notify_mutual_matches'] = $preferences['notify_mutual_matches'] ? 1 : 0;
            }

            $categories = $preferences['categories'] ?? null;

            DB::table('match_preferences')->updateOrInsert(
                ['user_id' => $userId],
                $data
            );

            // Sync category preferences
            if (is_array($categories)) {
                DB::table('match_preference_categories')
                    ->where('user_id', $userId)
                    ->delete();

                foreach ($categories as $catId) {
                    DB::table('match_preference_categories')->insert([
                        'user_id'     => $userId,
                        'category_id' => (int) $catId,
                    ]);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to save match preferences', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get matching preferences for a user (returns defaults if none saved).
     */
    public static function getPreferences($userId)
    {
        try {
            $row = DB::table('match_preferences')
                ->where('user_id', $userId)
                ->first();

            if (!$row) {
                return self::DEFAULT_PREFERENCES;
            }

            $categories = DB::table('match_preference_categories')
                ->where('user_id', $userId)
                ->pluck('category_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return [
                'max_distance_km'        => (int) ($row->max_distance_km ?? self::DEFAULT_PREFERENCES['max_distance_km']),
                'min_match_score'        => (int) ($row->min_match_score ?? self::DEFAULT_PREFERENCES['min_match_score']),
                'notification_frequency' => $row->notification_frequency ?? self::DEFAULT_PREFERENCES['notification_frequency'],
                'notify_hot_matches'     => (bool) ($row->notify_hot_matches ?? self::DEFAULT_PREFERENCES['notify_hot_matches']),
                'notify_mutual_matches'  => (bool) ($row->notify_mutual_matches ?? self::DEFAULT_PREFERENCES['notify_mutual_matches']),
                'categories'             => $categories,
            ];
        } catch (\Throwable $e) {
            return self::DEFAULT_PREFERENCES;
        }
    }

    /**
     * Record a user interaction with a match/listing.
     *
     * @param int         $userId    User who interacted
     * @param int         $listingId Listing/match they interacted with
     * @param string      $action    Action type: viewed, contacted, saved, dismissed
     * @param float|null  $score     Match score at time of interaction
     * @param float|null  $distance  Distance in km at time of interaction
     */
    public static function recordInteraction($userId, $listingId, string $action, $score = null, $distance = null): bool
    {
        try {
            DB::table('match_history')->insert([
                'user_id'    => $userId,
                'listing_id' => $listingId,
                'action'     => $action,
                'score'      => $score,
                'distance'   => $distance,
                'created_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('Failed to record match interaction', [
                'user_id' => $userId,
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get matching statistics for a user.
     *
     * @return array{total_matches: int, hot_matches: int, mutual_matches: int, avg_score: float|int, avg_distance: float|int}
     */
    public static function getStats($userId): array
    {
        try {
            $tenantId = \App\Core\TenantContext::getId();

            $total = (int) DB::table('match_cache')
                ->where('user_id', $userId)
                ->count();

            $hot = (int) DB::table('match_cache')
                ->where('user_id', $userId)
                ->where('score', '>=', 80)
                ->count();

            $mutual = (int) DB::table('match_cache')
                ->where('user_id', $userId)
                ->where('is_mutual', true)
                ->count();

            $avgScore = DB::table('match_cache')
                ->where('user_id', $userId)
                ->avg('score');

            $avgDistance = DB::table('match_cache')
                ->where('user_id', $userId)
                ->avg('distance');

            return [
                'total_matches'  => $total,
                'hot_matches'    => $hot,
                'mutual_matches' => $mutual,
                'avg_score'      => $avgScore !== null ? round((float) $avgScore, 1) : 0,
                'avg_distance'   => $avgDistance !== null ? round((float) $avgDistance, 1) : 0,
            ];
        } catch (\Throwable $e) {
            return [
                'total_matches'  => 0,
                'hot_matches'    => 0,
                'mutual_matches' => 0,
                'avg_score'      => 0,
                'avg_distance'   => 0,
            ];
        }
    }

    /**
     * Send notifications for new matches.
     *
     * @return int Number of notifications sent
     */
    public static function notifyNewMatches($userId): int
    {
        // Stub: notifications are handled elsewhere
        return 0;
    }
}
