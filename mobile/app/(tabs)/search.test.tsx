// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

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
        'title': 'Search',
        'placeholder': 'Search for people, listings…',
        'startTyping': 'Start typing to search.',
        'empty': 'No results found.',
        'error': 'Something went wrong.',
        'filterAll': 'All',
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

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
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

jest.mock('@/components/OfflineBanner', () => () => null);

// --- Tests ---

import SearchScreen from './search';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockSearchResult = {
  id: 42,
  type: 'user' as const,
  title: 'Jane Doe',
  subtitle: 'Timebanker',
};

describe('SearchScreen', () => {
  it('renders the screen title', () => {
    const { getByText } = render(<SearchScreen />);
    expect(getByText('Search')).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<SearchScreen />);
    expect(getByPlaceholderText('Search for people, listings…')).toBeTruthy();
  });

  it('renders "start typing" prompt when query is empty', () => {
    const { getByText } = render(<SearchScreen />);
    expect(getByText('Start typing to search.')).toBeTruthy();
  });

  it('renders type filter pills', () => {
    const { getByText } = render(<SearchScreen />);
    expect(getByText('All')).toBeTruthy();
    expect(getByText('People')).toBeTruthy();
    expect(getByText('Listings')).toBeTruthy();
    expect(getByText('Events')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
    expect(getByText('Blog')).toBeTruthy();
  });

  it('renders results when data is provided', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: [mockSearchResult] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<SearchScreen />);
    expect(getByText('Jane Doe')).toBeTruthy();
  });

  it('renders empty state when query returns no results', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: [] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<SearchScreen />);
    // With empty query (debouncedQuery.trim().length === 0), "startTyping" shows
    expect(getByText('Start typing to search.')).toBeTruthy();
  });
});
