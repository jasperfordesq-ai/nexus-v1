// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  useSegments: () => ['(tabs)'],
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'upcoming': 'Upcoming',
        'past': 'Past',
        'loadError': 'Could not load events.',
        'noEvents': opts ? `No ${String(opts.when ?? '')} events.` : 'No events.',
        'going': 'Going',
        'online': 'Online',
        'goingCount': opts ? `${String(opts.count ?? 0)} going` : '0 going',
        'common:buttons.retry': 'Retry',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
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
    errorBg: '#fff5f5',
  }),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Warning: 'warning' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/events', () => ({
  getEvents: jest.fn(),
}));

jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  EventCardSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  ProfileSkeleton: () => null,
}));

// --- Tests ---

import EventsScreen from './events';

const defaultPaginatedState = {
  items: [],
  isLoading: false,
  isLoadingMore: false,
  error: null,
  hasMore: false,
  loadMore: jest.fn(),
  refresh: jest.fn(),
};

beforeEach(() => {
  mockUsePaginatedApi.mockReturnValue(defaultPaginatedState);
});

const mockEvent = {
  id: 10,
  title: 'Community Bake Sale',
  start_date: '2026-06-15T14:00:00Z',
  end_date: '2026-06-15T17:00:00Z',
  location: 'Town Hall',
  is_online: false,
  rsvp_counts: { going: 8, interested: 3 },
  user_rsvp: null,
  category: null,
};

describe('EventsScreen', () => {
  it('renders Upcoming and Past tab buttons', () => {
    const { getByText } = render(<EventsScreen />);
    expect(getByText('Upcoming')).toBeTruthy();
    expect(getByText('Past')).toBeTruthy();
  });

  it('renders empty state when there are no events and not loading', () => {
    const { getByText } = render(<EventsScreen />);
    expect(getByText(/No .* events\./)).toBeTruthy();
  });

  it('does not show empty state while loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<EventsScreen />);
    expect(queryByText(/No .* events\./)).toBeNull();
  });

  it('renders event cards when events are loaded', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockEvent],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<EventsScreen />);
    expect(getByText('Community Bake Sale')).toBeTruthy();
  });

  it('shows RSVP going badge on an event the user has RSVPed to', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [{ ...mockEvent, user_rsvp: 'going' }],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<EventsScreen />);
    expect(getByText('Going')).toBeTruthy();
  });

  it('shows error message with Retry button when events fail to load', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: false,
      isLoadingMore: false,
      error: 'Could not load events.',
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<EventsScreen />);
    expect(getByText('Could not load events.')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });

  it('switches to Past tab when tapped', () => {
    const { getByText } = render(<EventsScreen />);
    const pastTab = getByText('Past');
    fireEvent.press(pastTab);
    // Tab is still rendered and accessible after switching
    expect(getByText('Past')).toBeTruthy();
  });

  it('renders online label for online events', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [{ ...mockEvent, is_online: true, location: null }],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<EventsScreen />);
    expect(getByText('Online')).toBeTruthy();
  });
});
