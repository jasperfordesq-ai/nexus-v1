// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '3' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Organisation',
        'detail.about': 'About',
        'detail.invalidId': 'Invalid organisation ID.',
        'detail.notFound': 'Organisation not found.',
        'detail.goBack': 'Go Back',
        'verified': 'Verified',
        'website': 'Visit Website',
        'members': opts ? `${String(opts.count ?? 0)} members` : '0 members',
        'listings': opts ? `${String(opts.count ?? 0)} listings` : '0 listings',
        'common:errors.alertTitle': 'Error',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
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

jest.mock('@/lib/api/organisations', () => ({
  getOrganisation: jest.fn(),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import OrganisationDetailScreen from './organisation-detail';

const mockOrg = {
  id: 3,
  name: 'Dublin Community Hub',
  description: 'A vibrant hub for community services in Dublin.',
  logo: null,
  location: 'Dublin, Ireland',
  website: 'https://dublincommunityhub.ie',
  verified: true,
  members_count: 42,
  listings_count: 15,
};

beforeEach(() => {
  mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });
});

describe('OrganisationDetailScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockOrg },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { toJSON } = render(<OrganisationDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the organisation name', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockOrg },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationDetailScreen />);
    expect(getByText('Dublin Community Hub')).toBeTruthy();
  });

  it('renders the Verified badge for verified organisations', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockOrg },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationDetailScreen />);
    expect(getByText('Verified')).toBeTruthy();
  });

  it('renders the description text', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockOrg },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<OrganisationDetailScreen />);
    expect(getByText('A vibrant hub for community services in Dublin.')).toBeTruthy();
  });

  it('renders loading state without crashing', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    expect(() => render(<OrganisationDetailScreen />)).not.toThrow();
  });

  it('renders not found state when data is null after loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<OrganisationDetailScreen />);
    expect(getByText('Organisation not found.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });
});
