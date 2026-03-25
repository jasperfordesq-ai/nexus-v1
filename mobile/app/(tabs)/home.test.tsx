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
        'feed.greeting': opts ? `Hello, ${String(opts.name ?? '')}` : 'Hello, Friend',
        'feed.subtitle': "Here's what's happening in your timebank",
        'feed.emptyTitle': 'No activity yet. Say hello to your community!',
        'notifications.title': 'Notifications',
        'common:labels.friend': 'Friend',
        'common:buttons.retry': 'Retry',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({
    user: { id: 1, email: 'alice@example.com', name: 'Alice Smith' },
    displayName: 'Alice Smith',
    logout: jest.fn(),
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

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/feed', () => ({
  getFeed: jest.fn(),
}));

jest.mock('@/lib/api/notifications', () => ({
  getNotificationCounts: jest.fn(),
}));

jest.mock('@/components/FeedItem', () => {
  const MockFeedItem = ({ item }: { item: { id: number; content?: string } }) => {
    const { Text } = require('react-native');
    return <Text>{item.content ?? `feed-item-${item.id}`}</Text>;
  };
  MockFeedItem.displayName = 'MockFeedItem';
  return MockFeedItem;
});

jest.mock('@/components/OfflineBanner', () => () => null);
jest.mock('@/components/TenantBanner', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  FeedItemSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  ProfileSkeleton: () => null,
}));

// --- Tests ---

import HomeScreen from './home';

const defaultPaginatedState = {
  items: [],
  isLoading: false,
  isLoadingMore: false,
  error: null,
  hasMore: false,
  loadMore: jest.fn(),
  refresh: jest.fn(),
};

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUsePaginatedApi.mockReturnValue(defaultPaginatedState);
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockFeedItem = {
  id: 1,
  type: 'post' as const,
  content: 'Hello, timebank!',
  created_at: '2026-01-01T10:00:00Z',
  user: { id: 1, name: 'Alice Smith', avatar_url: null },
  likes_count: 0,
  comments_count: 0,
  has_liked: false,
};

describe('HomeScreen', () => {
  it('renders the greeting with the user first name', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText('Hello, Alice', { exact: false })).toBeTruthy();
  });

  it('renders the feed subtitle', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText("Here's what's happening in your timebank")).toBeTruthy();
  });

  it('renders empty state when feed has no items and is not loading', () => {
    const { getByText } = render(<HomeScreen />);
    expect(getByText('No activity yet. Say hello to your community!')).toBeTruthy();
  });

  it('does not show empty text while loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<HomeScreen />);
    expect(queryByText('No activity yet. Say hello to your community!')).toBeNull();
  });

  it('renders feed items when data is loaded', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockFeedItem],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<HomeScreen />);
    expect(getByText('Hello, timebank!')).toBeTruthy();
  });

  it('shows error message with Retry button when feed fails to load', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: false,
      isLoadingMore: false,
      error: 'Network error',
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<HomeScreen />);
    expect(getByText('Network error')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });

  it('shows unread notification badge when there are unread notifications', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: { total: 3 } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<HomeScreen />);
    expect(getByText('3')).toBeTruthy();
  });

  it('shows 9+ badge when unread notification count exceeds 9', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: { total: 14 } },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<HomeScreen />);
    expect(getByText('9+')).toBeTruthy();
  });
});
