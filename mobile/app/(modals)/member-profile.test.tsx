// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '7' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'profile.loadError': 'Failed to load member profile.',
        'profile.verified': 'Verified',
        'profile.hoursGiven': 'Hours Given',
        'profile.hoursReceived': 'Hours Received',
        'profile.skills': 'Skills',
        'profile.noSkills': 'No skills listed.',
        'profile.sendMessage': 'Send Message',
        'profile.memberSince': opts ? `Member since ${String(opts.date ?? '')}` : 'Member since',
        'common:errors.notFound': 'Member not found.',
        'common:buttons.back': 'Go Back',
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
    success: '#22c55e',
    warning: '#f59e0b',
    info: '#3b82f6',
    errorBg: '#fef2f2',
    successBg: '#f0fdf4',
    infoBg: '#eff6ff',
    warningBg: '#fffbeb',
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

jest.mock('@/lib/api/members', () => ({
  getMember: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import MemberProfileScreen from './member-profile';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockMember = {
  id: 7,
  name: 'Alice Tanner',
  bio: 'Passionate community gardener and timebank advocate.',
  avatar_url: null,
  location: 'Cork, Ireland',
  time_balance: 12,
  skills: ['Gardening', 'Cooking'],
  joined_at: '2024-06-15T00:00:00Z',
  last_active_at: '2026-03-20T10:00:00Z',
  total_hours_given: 15,
  total_hours_received: 8,
  rating: 4.7,
  is_verified: false,
};

describe('MemberProfileScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockMember }, isLoading: false, error: null, refresh: jest.fn() });

    const { toJSON } = render(<MemberProfileScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders a loading spinner when the API is loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    const { toJSON } = render(<MemberProfileScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the member name when loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockMember }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<MemberProfileScreen />);
    expect(getByText('Alice Tanner')).toBeTruthy();
  });

  it('renders the not-found state when data is null after loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<MemberProfileScreen />);
    expect(getByText('Failed to load member profile.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });

  it('renders the Send Message button when member is loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockMember }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<MemberProfileScreen />);
    expect(getByText('Send Message')).toBeTruthy();
  });
});
