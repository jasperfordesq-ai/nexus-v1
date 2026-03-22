// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '2' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Partner Details',
        'detail.about': 'About',
        'detail.notFound': 'Partner not found.',
        'detail.goBack': 'Go Back',
        'visitWebsite': 'Visit Website',
        'connectedSince': opts ? `Connected since ${String(opts.date ?? '')}` : 'Connected since',
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

jest.mock('@/lib/api/federation', () => ({
  getFederationPartner: jest.fn(),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import FederationPartnerScreen from './federation-partner';

const mockPartner = {
  id: 2,
  name: 'Cork Timebank',
  description: 'Serving the Cork community with time-based exchanges.',
  logo: null,
  location: 'Cork, Ireland',
  website: 'https://corktimebank.ie',
  member_count: 128,
  connected_since: '2025-06-01T00:00:00Z',
};

beforeEach(() => {
  mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });
});

describe('FederationPartnerScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockPartner },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { toJSON } = render(<FederationPartnerScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the partner name', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockPartner },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<FederationPartnerScreen />);
    expect(getByText('Cork Timebank')).toBeTruthy();
  });

  it('renders the partner description', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockPartner },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<FederationPartnerScreen />);
    expect(getByText('Serving the Cork community with time-based exchanges.')).toBeTruthy();
  });

  it('renders the Visit Website button', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockPartner },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<FederationPartnerScreen />);
    expect(getByText('Visit Website')).toBeTruthy();
  });

  it('renders loading state without crashing', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    expect(() => render(<FederationPartnerScreen />)).not.toThrow();
  });

  it('renders not found state when data is null after loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<FederationPartnerScreen />);
    expect(getByText('Partner not found.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });
});
