// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        title: 'Organisations',
        subtitle: 'Discover volunteer organisations in your community.',
        heroEyebrow: 'Trusted local partners',
        searchPlaceholder: 'Search organisations...',
        emptyTitle: 'No organisations found',
        empty: 'No organisations found.',
        noDescription: 'Community partner profile.',
        verified: 'Verified',
        members: opts ? `${String(opts.count ?? 0)} members` : '0 members',
        listings: opts ? `${String(opts.count ?? 0)} listings` : '0 listings',
        opportunities: opts ? `${String(opts.count ?? 0)} opportunities` : '0 opportunities',
        volunteers: opts ? `${String(opts.count ?? 0)} volunteers` : '0 volunteers',
        hoursLogged: opts ? `${String(opts.hours ?? 0)}h logged` : '0h logged',
        viewOrganisation: 'View organisation',
        website: 'Visit website',
        'stats.organisations': 'Partners',
        'stats.verified': 'Verified',
        'stats.opportunities': 'Opportunities',
        'stats.volunteers': 'Volunteers',
        'common:back': 'Back',
        'common:endOfList': "You've reached the end",
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

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Text, View } = require('react-native');

  const Button = ({ children, onPress }: { children: React.ReactNode; onPress?: () => void }) => (
    <Text onPress={onPress}>{children}</Text>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

  const Card = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;

  const Chip = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Chip.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

  return {
    Button,
    Card,
    Chip,
    Spinner: () => null,
    Surface: ({ children }: { children?: React.ReactNode }) => <View>{children}</View>,
  };
});

jest.mock('@/lib/api/organisations', () => ({
  getOrganisations: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/AppTopBar', () => 'View');

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
  logo_url: null,
  location: 'Dublin, Ireland',
  verified: true,
  members_count: 45,
  listings_count: 12,
  opportunity_count: 12,
  volunteer_count: 45,
  total_hours: 18,
  average_rating: 4.7,
  website: 'https://example.test',
  description: 'A community environmental group.',
  created_at: '2026-01-01T00:00:00Z',
};

const mockUnverifiedOrg = {
  id: 2,
  name: 'Cork Makers',
  logo: null,
  logo_url: null,
  location: 'Cork, Ireland',
  verified: false,
  members_count: 20,
  listings_count: 5,
  opportunity_count: 5,
  volunteer_count: 20,
  total_hours: 0,
  average_rating: null,
  website: null,
  description: null,
  created_at: '2026-01-01T00:00:00Z',
};

describe('OrganisationsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<OrganisationsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<OrganisationsScreen />);
    expect(getByPlaceholderText('Search organisations...')).toBeTruthy();
  });

  it('renders the empty state when there are no organisations', () => {
    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('No organisations found')).toBeTruthy();
  });

  it('does not render the empty state when loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      isLoading: true,
    });

    const { queryByText } = render(<OrganisationsScreen />);
    expect(queryByText('No organisations found')).toBeNull();
  });

  it('renders organisation cards when items are provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [mockOrganisation],
    });

    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('Green Dublin')).toBeTruthy();
    expect(getByText('Dublin, Ireland')).toBeTruthy();
  });

  it('renders the Verified badge on verified organisations', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [mockOrganisation],
    });

    const { getAllByText } = render(<OrganisationsScreen />);
    expect(getAllByText('Verified').length).toBeGreaterThanOrEqual(1);
  });

  it('does not render Verified badge for unverified organisations', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [mockUnverifiedOrg],
    });

    const { getAllByText } = render(<OrganisationsScreen />);
    expect(getAllByText('Verified').length).toBe(1);
  });

  it('renders opportunity and volunteer counts on organisation cards', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      ...defaultPaginatedState,
      items: [mockOrganisation],
    });

    const { getByText } = render(<OrganisationsScreen />);
    expect(getByText('45 volunteers')).toBeTruthy();
    expect(getByText('12 opportunities')).toBeTruthy();
  });
});
