// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

// ─── Types ───────────────────────────────────────────────────────────────────

export interface Badge {
  id: number;
  name: string;
  description: string;
  icon: string;
  earned_at: string | null;
  is_earned: boolean;
}

export interface GamificationProfile {
  xp: number;
  level: number;
  next_level_xp: number;
  level_progress?: {
    current_xp?: number;
    xp_for_current_level?: number;
    xp_for_next_level?: number;
    progress_percentage?: number;
  };
  badges: Badge[];
  badges_count?: number;
  showcased_badges?: Badge[];
  rank: number | null;
  streak_days: number;
}

export interface LeaderboardEntry {
  rank: number;
  user: {
    id: number;
    name: string;
    avatar: string | null;
  };
  xp: number;
  level: number;
  badges_count: number;
}

export interface LeaderboardResponse {
  data: LeaderboardEntry[];
  meta: {
    total: number;
    user_rank: number | null;
    your_position?: number | null;
  };
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * GET /api/v2/gamification/profile
 * Returns the current user's XP, level, rank, streak, and earned badges.
 */
export function getGamificationProfile(userId?: number): Promise<{ data: GamificationProfile }> {
  if (userId) {
    return api.get<{ data: GamificationProfile }>(`${API_V2}/gamification/profile`, { user_id: String(userId) });
  }
  return api.get<{ data: GamificationProfile }>(`${API_V2}/gamification/profile`);
}

/**
 * GET /api/v2/gamification/badges
 * Returns all badges (earned and locked) for the current user.
 */
export function getBadges(userId?: number): Promise<{ data: Badge[] }> {
  if (userId) {
    return api.get<{ data: Badge[] }>(`${API_V2}/gamification/badges`, { user_id: String(userId) });
  }
  return api.get<{ data: Badge[] }>(`${API_V2}/gamification/badges`);
}

/**
 * GET /api/v2/gamification/leaderboard
 * Returns ranked leaderboard entries for the given time period.
 *
 * @param period  'weekly' | 'monthly' | 'all_time' (default: 'monthly')
 */
export function getLeaderboard(
  period: 'weekly' | 'monthly' | 'all_time' = 'monthly',
): Promise<LeaderboardResponse> {
  const apiPeriod = period === 'weekly' ? 'week' : period === 'monthly' ? 'month' : 'all';
  return api.get<LeaderboardResponse>(`${API_V2}/gamification/leaderboard`, { period: apiPeriod });
}
