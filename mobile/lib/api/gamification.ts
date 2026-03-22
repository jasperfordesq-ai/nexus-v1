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
  badges: Badge[];
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
  };
}

// ─── API Functions ────────────────────────────────────────────────────────────

/**
 * GET /api/v2/gamification/profile
 * Returns the current user's XP, level, rank, streak, and earned badges.
 */
export function getGamificationProfile(): Promise<{ data: GamificationProfile }> {
  return api.get<{ data: GamificationProfile }>(`${API_V2}/gamification/profile`);
}

/**
 * GET /api/v2/gamification/badges
 * Returns all badges (earned and locked) for the current user.
 */
export function getBadges(): Promise<{ data: Badge[] }> {
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
  return api.get<LeaderboardResponse>(`${API_V2}/gamification/leaderboard`, { period });
}
