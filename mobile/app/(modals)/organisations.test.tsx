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
        'title': 'Organisations',
        'searchPlaceholder': 'Search organisations…',
        'empty': 'No organisations found.',
        'verified': 'Verified',
        'members': opts ? `${String(opts.count ?? 0)} members` : '0 members',
        'listings': opts ? `${String(opts.count ?? 0)} listings` : '0 listings',
        'common:actions.retry': 'Retry',
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
  }),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/organisations', () => ({
  getOrganisations: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import OrganisationsScreen from './organisations';

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

const mockOrganisation = {
  id: 1,
  name: 'Green Dublin',
  logo: null,
  location: 'Dublin, Ireland',
  verified: true,
  members_count: 45,
  listings_count: 12,
  description: 'A community environmental group.',
};

const mockUnverifiedOrg = {
  id: 2,
  name: 'Cork Makers',
  logo: null,
  location: 'Cork, Ireland',
  verified: false,
  members_count: 20,
  listings_count: 5,
  description: null,
};

describe('OrganisationsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<OrganisationsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<OrganisationsScreen />);
    expect(getByPlaceholderText('Search organisations…')).toBeTruthy();
  });

  it('renders the empty state when there are no organisations', () => {
    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('No organisations found.')).toBeTruthy();
  });

  it('does not render the empty state when loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<OrganisationsScreen />);
    expect(queryByText('No organisations found.')).toBeNull();
  });

  it('renders organisation cards when items are provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockOrganisation],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('Green Dublin')).toBeTruthy();
    expect(getByText('Dublin, Ireland')).toBeTruthy();
  });

  it('renders the Verified badge on verified organisations', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockOrganisation],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('Verified')).toBeTruthy();
  });

  it('does not render Verified badge for unverified organisations', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockUnverifiedOrg],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<OrganisationsScreen />);
    expect(queryByText('Verified')).toBeNull();
  });

  it('renders member and listing counts on organisation cards', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockOrganisation],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('45 members')).toBeTruthy();
    expect(getByText('12 listings')).toBeTruthy();
  });
});
