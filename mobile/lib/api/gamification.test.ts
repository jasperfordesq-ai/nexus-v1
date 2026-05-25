// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), delete: jest.fn(), patch: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
  registerUnauthorizedCallback: jest.fn(),
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  STORAGE_KEYS: { AUTH_TOKEN: 'auth_token', REFRESH_TOKEN: 'refresh_token', TENANT_SLUG: 'tenant_slug', USER_DATA: 'user_data' },
  TIMEOUTS: { API_REQUEST: 15_000 },
  DEFAULT_TENANT: 'test-tenant',
}));

import { api } from '@/lib/api/client';
import { getGamificationProfile, getBadges, getLeaderboard } from './gamification';
import type { GamificationProfile, Badge, LeaderboardResponse } from './gamification';

const mockBadge: Badge = {
  id: 1,
  name: 'First Exchange',
  description: 'Completed your first exchange',
  icon: 'star',
  earned_at: '2026-01-10T12:00:00Z',
  is_earned: true,
};

const mockProfile: GamificationProfile = {
  xp: 250,
  level: 3,
  next_level_xp: 500,
  badges: [mockBadge],
  rank: 12,
  streak_days: 5,
};

const mockLeaderboardResponse: LeaderboardResponse = {
  data: [
    {
      rank: 1,
      user: { id: 5, name: 'Top User', avatar: null },
      xp: 1500,
      level: 10,
      badges_count: 8,
    },
  ],
  meta: { total: 50, user_rank: 12 },
};

describe('getGamificationProfile', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with no params', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockProfile });
    const result = await getGamificationProfile();
    expect(api.get).toHaveBeenCalledWith('/api/v2/gamification/profile');
    expect(result.data.xp).toBe(250);
    expect(result.data.level).toBe(3);
    expect(result.data.streak_days).toBe(5);
  });

  it('returns the full profile including badges', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockProfile });
    const result = await getGamificationProfile();
    expect(result.data.badges).toHaveLength(1);
    expect(result.data.badges[0].name).toBe('First Exchange');
  });
});

describe('getBadges', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct badges endpoint with no params', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [mockBadge] });
    const result = await getBadges();
    expect(api.get).toHaveBeenCalledWith('/api/v2/gamification/badges');
    expect(result.data).toHaveLength(1);
    expect(result.data[0].is_earned).toBe(true);
  });
});

describe('getLeaderboard', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with default period (monthly)', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockLeaderboardResponse);
    const result = await getLeaderboard();
    expect(api.get).toHaveBeenCalledWith('/api/v2/gamification/leaderboard', { period: 'monthly' });
    expect(result.data).toHaveLength(1);
    expect(result.meta.user_rank).toBe(12);
  });

  it('passes weekly period when specified', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockLeaderboardResponse);
    await getLeaderboard('weekly');
    expect(api.get).toHaveBeenCalledWith('/api/v2/gamification/leaderboard', { period: 'weekly' });
  });

  it('passes all_time period when specified', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockLeaderboardResponse);
    await getLeaderboard('all_time');
    expect(api.get).toHaveBeenCalledWith('/api/v2/gamification/leaderboard', { period: 'all_time' });
  });
});
