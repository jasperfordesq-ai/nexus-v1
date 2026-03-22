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
    t: (key: string) => {
      const map: Record<string, string> = {
        'title': 'Listings',
        'searchPlaceholder': 'Search listings\u2026',
        'newListing': 'Create new listing',
        'empty': 'No listings found.',
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

jest.mock('@/lib/hooks/useDebounce', () => ({
  useDebounce: (value: string) => value,
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

jest.mock('@/lib/api/exchanges', () => ({
  getExchanges: jest.fn(),
}));

jest.mock('@/components/ExchangeCard', () => ({ exchange }: { exchange: { id: number; title: string } }) => {
  const { Text } = require('react-native');
  return <Text>{exchange.title}</Text>;
});

jest.mock('@/components/OfflineBanner', () => () => null);
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  ExchangeCardSkeleton: () => null,
  ProfileSkeleton: () => null,
}));

// --- Tests ---

import ExchangesScreen from './exchanges';

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

const mockExchange = {
  id: 5,
  title: 'Gardening Help Offered',
  type: 'offer' as const,
  description: 'I can help with your garden.',
  time_credits: 2,
  user: { id: 1, name: 'Alice Smith', avatar_url: null },
  created_at: '2026-01-10T09:00:00Z',
};

describe('ExchangesScreen', () => {
  it('renders the screen title', () => {
    const { getByText } = render(<ExchangesScreen />);
    expect(getByText('Listings')).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<ExchangesScreen />);
    expect(getByPlaceholderText('Search listings\u2026')).toBeTruthy();
  });

  it('renders empty state when there are no exchanges and not loading', () => {
    const { getByText } = render(<ExchangesScreen />);
    expect(getByText('No listings found.')).toBeTruthy();
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

    const { queryByText } = render(<ExchangesScreen />);
    expect(queryByText('No listings found.')).toBeNull();
  });

  it('renders exchange cards when data is loaded', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockExchange],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<ExchangesScreen />);
    expect(getByText('Gardening Help Offered')).toBeTruthy();
  });

  it('shows error text with Retry button when exchanges fail to load', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: false,
      isLoadingMore: false,
      error: 'Failed to load listings.',
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<ExchangesScreen />);
    expect(getByText('Failed to load listings.')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });

  it('updates the search input value when typed into', () => {
    const { getByPlaceholderText } = render(<ExchangesScreen />);
    const input = getByPlaceholderText('Search listings\u2026');
    fireEvent.changeText(input, 'gardening');
    expect(input.props.value).toBe('gardening');
  });
});
