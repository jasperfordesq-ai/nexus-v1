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
        'title': 'Federation',
        'partners': 'Partner Communities',
        'empty': 'No partner communities yet.',
        'connectedSince': opts ? `Connected since ${String(opts.date ?? '')}` : 'Connected since',
        'stats.partners': opts ? `${String(opts.count ?? 0)} partners` : '0 partners',
        'stats.members': opts ? `${String(opts.count ?? 0)} members` : '0 members',
        'stats.exchanges': opts ? `${String(opts.count ?? 0)} exchanges` : '0 exchanges',
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

// Default: stats loaded, no partners
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: () => ({
    data: {
      data: {
        partner_count: 3,
        federated_members: 250,
        cross_community_exchanges: 47,
      },
    },
    isLoading: false,
    error: null,
    refresh: jest.fn(),
  }),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/federation', () => ({
  getFederationPartners: jest.fn(),
  getFederationStats: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import FederationScreen from './federation';
import * as useApiModule from '@/lib/hooks/useApi';

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

const mockPartner = {
  id: 1,
  name: 'Cork Timebank',
  logo: null,
  location: 'Cork, Ireland',
  member_count: 120,
  connected_since: '2025-06-01T00:00:00Z',
};

describe('FederationScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<FederationScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the Partner Communities section heading', () => {
    const { getByText } = render(<FederationScreen />);
    expect(getByText('Partner Communities')).toBeTruthy();
  });

  it('renders the federation stats card', () => {
    const { getByText } = render(<FederationScreen />);
    expect(getByText('3 partners')).toBeTruthy();
    expect(getByText('250 members')).toBeTruthy();
    expect(getByText('47 exchanges')).toBeTruthy();
  });

  it('renders the empty state when there are no partners', () => {
    const { getByText } = render(<FederationScreen />);
    expect(getByText('No partner communities yet.')).toBeTruthy();
  });

  it('renders partner cards when partners are available', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockPartner],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<FederationScreen />);
    expect(getByText('Cork Timebank')).toBeTruthy();
    expect(getByText('Cork, Ireland')).toBeTruthy();
  });

  it('renders a loading spinner when partners are loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    // While loading, the screen renders a LoadingSpinner (mocked to null) — no crash
    const { toJSON } = render(<FederationScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders multiple partners correctly', () => {
    const secondPartner = {
      id: 2,
      name: 'Galway Community Exchange',
      logo: null,
      location: 'Galway, Ireland',
      member_count: 85,
      connected_since: '2025-09-01T00:00:00Z',
    };

    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockPartner, secondPartner],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<FederationScreen />);
    expect(getByText('Cork Timebank')).toBeTruthy();
    expect(getByText('Galway Community Exchange')).toBeTruthy();
  });
});
