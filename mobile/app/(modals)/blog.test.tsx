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
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'title': 'Blog',
        'empty': 'No posts yet.',
        'readingTime': opts ? `${String(opts.minutes ?? 0)} min read` : '0 min read',
        'common:buttons.retry': 'Retry',
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

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

// Mock react-native Image to avoid URI issues in tests
jest.mock('react-native/Libraries/Image/Image', () => 'View');

jest.mock('@/lib/api/blog', () => ({
  getBlogPosts: jest.fn(),
}));

// --- Tests ---

import BlogScreen from './blog';

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

const mockBlogPost = {
  id: 7,
  title: 'Getting Started with Timebanking',
  excerpt: 'Learn how to exchange time credits in your community.',
  cover_image: null,
  category: 'Guide',
  reading_time_minutes: 5,
  author: { id: 3, name: 'Editor Team' },
  published_at: '2026-01-10T09:00:00Z',
};

describe('BlogScreen', () => {
  it('renders the blog list without crashing', () => {
    const { toJSON } = render(<BlogScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders empty state when no posts and not loading', () => {
    const { getByText } = render(<BlogScreen />);
    expect(getByText('No posts yet.')).toBeTruthy();
  });

  it('does not render empty state while loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<BlogScreen />);
    expect(queryByText('No posts yet.')).toBeNull();
  });

  it('renders blog post title when items are provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockBlogPost],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<BlogScreen />);
    expect(getByText('Getting Started with Timebanking')).toBeTruthy();
  });

  it('renders blog post author name', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockBlogPost],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<BlogScreen />);
    expect(getByText('Editor Team')).toBeTruthy();
  });

  it('renders reading time when provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockBlogPost],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<BlogScreen />);
    expect(getByText('5 min read')).toBeTruthy();
  });
});
