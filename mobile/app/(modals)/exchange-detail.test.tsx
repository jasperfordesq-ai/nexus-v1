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
  useLocalSearchParams: () => ({ id: '5' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Exchange Details',
        'detail.invalidId': 'Invalid exchange ID.',
        'detail.notFound': 'Exchange not found.',
        'detail.goBack': 'Go Back',
        'detail.postedBy': 'Posted by',
        'detail.timeEstimate': 'Time Estimate',
        'detail.requestService': 'Request this Service',
        'detail.offerHelp': 'Offer Help',
        'offering': 'Offering',
        'requesting': 'Requesting',
        'common:errors.alertTitle': 'Error',
        'common:buttons.cancel': 'Cancel',
        'detail.hours': opts ? `${String(opts.count ?? 0)} hrs` : '0 hrs',
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
    warning: '#f59e0b',
    info: '#3b82f6',
    errorBg: '#fee2e2',
    successBg: '#dcfce7',
    infoBg: '#dbeafe',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Current User' } }),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Medium: 'medium', Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/exchanges', () => ({
  getExchange: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import ExchangeDetailModal from './exchange-detail';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockExchange = {
  id: 5,
  title: 'Homemade Bread Baking Lessons',
  description: 'I will teach you how to bake sourdough bread at home.',
  type: 'offer' as const,
  hours_estimate: 2,
  user: {
    id: 42,
    name: 'Alice Baker',
    avatar_url: null,
  },
};

describe('ExchangeDetailModal', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockExchange }, isLoading: false, error: null, refresh: jest.fn() });

    const { toJSON } = render(<ExchangeDetailModal />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the loading state without crashing', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    expect(() => render(<ExchangeDetailModal />)).not.toThrow();
  });

  it('renders the exchange title when loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockExchange }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<ExchangeDetailModal />);
    expect(getByText('Homemade Bread Baking Lessons')).toBeTruthy();
  });

  it('renders the not found state when data is null after loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<ExchangeDetailModal />);
    expect(getByText('Exchange not found.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });

  it('renders the exchange type (offer/request) badge', () => {
    mockUseApi.mockReturnValue({ data: { data: mockExchange }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<ExchangeDetailModal />);
    // type is 'offer', so badge text is 'Offering'
    expect(getByText('Offering')).toBeTruthy();
  });
});
