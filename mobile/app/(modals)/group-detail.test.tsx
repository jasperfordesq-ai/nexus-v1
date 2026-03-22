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
  useLocalSearchParams: () => ({ id: '1' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Group Detail',
        'detail.invalidId': 'Invalid group ID.',
        'detail.goBack': 'Go back',
        'detail.notFound': 'Group not found.',
        'detail.about': 'About',
        'detail.members': 'Members',
        'featured': 'Featured',
        'private': 'Private',
        'public': 'Public',
        'join': 'Join',
        'leave': 'Leave',
        'joined': 'Joined',
        'leaveConfirmTitle': 'Leave group?',
        'leaveConfirmMessage': 'Are you sure you want to leave?',
        'joinError': 'Failed to join.',
        'leaveError': 'Failed to leave.',
        'members': opts ? `${String(opts.count ?? 0)} members` : '0 members',
        'posts': opts ? `${String(opts.count ?? 0)} posts` : '0 posts',
        'common:buttons.cancel': 'Cancel',
        'common:errors.alertTitle': 'Error',
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

jest.mock('@/lib/api/groups', () => ({
  getGroup: jest.fn(),
  joinGroup: jest.fn().mockResolvedValue({}),
  leaveGroup: jest.fn().mockResolvedValue({}),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import GroupDetailScreen from './group-detail';

const defaultApiState = { data: null, isLoading: true, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockGroupDetail = {
  id: 1,
  name: 'Garden Club',
  description: 'A club for gardening enthusiasts.',
  long_description: null,
  visibility: 'public' as const,
  member_count: 12,
  posts_count: 5,
  is_featured: false,
  is_member: false,
  tags: [],
  admin: {
    id: 10,
    name: 'Alice Admin',
    avatar_url: null,
  },
};

describe('GroupDetailScreen', () => {
  it('renders loading spinner when data is loading', () => {
    // Default mock: isLoading=true, data=null — LoadingSpinner is mocked to null
    // The screen renders a SafeAreaView with LoadingSpinner (null); we verify no content shown
    const { queryByText } = render(<GroupDetailScreen />);
    expect(queryByText('Garden Club')).toBeNull();
    expect(queryByText('Group not found.')).toBeNull();
  });

  it('renders not-found message when data is null and not loading', () => {
    mockUseApi.mockReturnValue({
      data: null,
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Group not found.')).toBeTruthy();
  });

  it('renders group name when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockGroupDetail },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Garden Club')).toBeTruthy();
  });

  it('renders join button when user is not a member', () => {
    mockUseApi.mockReturnValue({
      data: { data: { ...mockGroupDetail, is_member: false } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Join')).toBeTruthy();
  });

  it('renders leave button when user is already a member', () => {
    mockUseApi.mockReturnValue({
      data: { data: { ...mockGroupDetail, is_member: true } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('Leave')).toBeTruthy();
  });

  it('renders group description when loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockGroupDetail },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<GroupDetailScreen />);
    expect(getByText('A club for gardening enthusiasts.')).toBeTruthy();
  });
});
