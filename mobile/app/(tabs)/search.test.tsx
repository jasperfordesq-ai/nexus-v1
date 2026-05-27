// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) }),
  useSegments: () => ['(tabs)'],
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        title: 'Search',
        heroEyebrow: 'Global search',
        subtitle: 'Find members, listings, events, groups, and community stories.',
        placeholder: 'Search for people, listings...',
        clearSearch: 'Clear search',
        initialTitle: 'Search your community',
        startTyping: 'Start typing to search.',
        empty: 'No results found.',
        emptyHint: opts ? `No matches for ${String(opts.query ?? '')}` : 'No matches',
        searching: 'Searching...',
        resultsCount: opts ? `${String(opts.count ?? 0)} results` : '0 results',
        errorTitle: 'Search failed',
        error: 'Something went wrong.',
        filterAll: 'All',
        'types.user': 'People',
        'types.listing': 'Listings',
        'types.event': 'Events',
        'types.group': 'Groups',
        'types.blog_post': 'Blog',
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

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@/lib/hooks/useDebounce', () => ({
  useDebounce: (value: string) => value,
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/search', () => ({
  search: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/OfflineBanner', () => () => null);

import SearchScreen from './search';

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

const mockSearchResult = {
  id: 42,
  type: 'user' as const,
  title: 'Jane Doe',
  subtitle: 'Timebanker',
  avatar: null,
  url: null,
  created_at: '2026-01-01T00:00:00Z',
};

describe('SearchScreen', () => {
  it('renders the screen title', () => {
    const { getAllByText } = render(<SearchScreen />);
    expect(getAllByText('Search').length).toBeGreaterThan(0);
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<SearchScreen />);
    expect(getByPlaceholderText('Search for people, listings...')).toBeTruthy();
  });

  it('renders the initial search prompt when query is empty', () => {
    const { getByText } = render(<SearchScreen />);
    expect(getByText('Search your community')).toBeTruthy();
    expect(getByText('Start typing to search.')).toBeTruthy();
  });

  it('renders type filter tabs', () => {
    const { getByText } = render(<SearchScreen />);
    expect(getByText('All')).toBeTruthy();
    expect(getByText('People')).toBeTruthy();
    expect(getByText('Listings')).toBeTruthy();
    expect(getByText('Events')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
    expect(getByText('Blog')).toBeTruthy();
  });

  it('renders results when data is provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [mockSearchResult],
    });

    const { getByText } = render(<SearchScreen />);
    expect(getByText('Jane Doe')).toBeTruthy();
    expect(getByText('Timebanker')).toBeTruthy();
  });

  it('renders the initial empty state when no query has been entered', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [],
    });

    const { getByText } = render(<SearchScreen />);
    expect(getByText('Search your community')).toBeTruthy();
  });
});
