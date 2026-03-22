// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'gamification:title': 'Gamification',
        'gamification:level': opts ? `Level ${String(opts.level ?? 1)}` : 'Level 1',
        'gamification:rank': opts ? `Rank #${String(opts.rank ?? 1)}` : 'Rank #1',
        'gamification:unranked': 'Unranked',
        'gamification:streak': opts ? `${String(opts.days ?? 0)} day streak` : '0 day streak',
        'gamification:xp': opts ? `${String(opts.xp ?? 0)} XP` : '0 XP',
        'gamification:nextLevel': opts ? `${String(opts.xp ?? 0)} XP to next level` : '0 XP to next level',
        'gamification:badges.title': 'Badges',
        'gamification:badges.empty': 'No badges yet.',
        'gamification:badges.earned': 'Earned',
        'gamification:badges.locked': 'Locked',
        'gamification:leaderboard.title': 'Leaderboard',
        'gamification:leaderboard.empty': 'No leaderboard data.',
        'gamification:leaderboard.weekly': 'Weekly',
        'gamification:leaderboard.monthly': 'Monthly',
        'gamification:leaderboard.allTime': 'All Time',
        'gamification:leaderboard.you': 'You',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({ hasFeature: () => true }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    borderSubtle: '#eeeeee',
    error: '#e53e3e',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/gamification', () => ({
  getGamificationProfile: jest.fn(),
  getBadges: jest.fn(),
  getLeaderboard: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import GamificationScreen from './gamification';

const defaultLoadingState = { data: null, isLoading: true, error: null, refresh: jest.fn() };

beforeEach(() => {
  // Default: all loading so LoadingSpinner is rendered
  mockUseApi.mockReturnValue(defaultLoadingState);
});

const mockProfile = {
  level: 3,
  xp: 750,
  next_level_xp: 1000,
  rank: 5,
  streak_days: 7,
};

const mockBadge = {
  id: 1,
  name: 'First Exchange',
  icon: 'ribbon-outline',
  is_earned: true,
  earned_at: '2026-01-15T10:00:00Z',
};

describe('GamificationScreen', () => {
  it('renders loading state (no content visible) when APIs are loading', () => {
    // Default useApi mock returns isLoading:true for all three calls
    const { queryByText } = render(<GamificationScreen />);
    // Tab buttons should not be visible — profile+badges loading blocks render
    expect(queryByText('Badges')).toBeNull();
    expect(queryByText('Leaderboard')).toBeNull();
  });

  it('renders tab buttons when profile and badges data are loaded', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: mockProfile }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [], meta: { user_rank: null } }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GamificationScreen />);
    expect(getByText('Badges')).toBeTruthy();
    expect(getByText('Leaderboard')).toBeTruthy();
  });

  it('renders profile XP when loaded', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: mockProfile }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [], meta: { user_rank: null } }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GamificationScreen />);
    expect(getByText('750 XP')).toBeTruthy();
  });

  it('renders badge cards when badges are loaded', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: mockProfile }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [mockBadge] }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [], meta: { user_rank: null } }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GamificationScreen />);
    expect(getByText('First Exchange')).toBeTruthy();
  });

  it('switches to leaderboard tab when tapped', () => {
    let callCount = 0;
    mockUseApi.mockImplementation(() => {
      callCount += 1;
      const pos = ((callCount - 1) % 3) + 1;
      if (pos === 1) return { data: { data: mockProfile }, isLoading: false, error: null, refresh: jest.fn() };
      if (pos === 2) return { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
      return { data: { data: [], meta: { user_rank: null } }, isLoading: false, error: null, refresh: jest.fn() };
    });

    const { getByText } = render(<GamificationScreen />);
    fireEvent.press(getByText('Leaderboard'));
    // After pressing Leaderboard tab, period selector appears
    expect(getByText('Weekly')).toBeTruthy();
    expect(getByText('Monthly')).toBeTruthy();
    expect(getByText('All Time')).toBeTruthy();
  });

  it('renders empty badges message when badge list is empty', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: mockProfile }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [], meta: { user_rank: null } }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<GamificationScreen />);
    expect(getByText('No badges yet.')).toBeTruthy();
  });
});
