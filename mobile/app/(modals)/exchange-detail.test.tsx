// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

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
        'detail.communityActions': 'Community actions',
        'detail.like': 'Like',
        'detail.comment': 'Comment',
        'detail.share': 'Share',
        'detail.report': 'Report',
        'detail.ownerTools': 'Listing tools',
        'offering': 'Offering',
        'requesting': 'Requesting',
        'common:errors.alertTitle': 'Error',
        'common:buttons.cancel': 'Cancel',
        'detail.imageThumbnail': opts ? `Show listing image ${String(opts.number ?? '')}` : 'Show listing image',
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
  getExchangeWorkflowConfig: jest.fn().mockResolvedValue({ data: { exchange_workflow_enabled: true } }),
  checkActiveExchange: jest.fn().mockResolvedValue({ data: null }),
  getExchangeComments: jest.fn().mockResolvedValue({ data: { comments: [], count: 0 } }),
  createExchangeRequest: jest.fn(),
  deleteExchange: jest.fn(),
  renewExchange: jest.fn(),
  saveExchange: jest.fn(),
  unsaveExchange: jest.fn(),
  submitExchangeComment: jest.fn(),
  toggleExchangeLike: jest.fn(),
  reportExchange: jest.fn(),
}));

jest.mock('@/lib/api/verification', () => ({
  getUserVerificationBadges: jest.fn().mockResolvedValue([]),
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
  image_url: null,
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

    const { getAllByText, getByText } = render(<ExchangeDetailModal />);
    expect(getByText('Exchange not found.')).toBeTruthy();
    expect(getAllByText('Go Back').length).toBeGreaterThan(0);
  });

  it('renders the exchange type (offer/request) badge', () => {
    mockUseApi.mockReturnValue({ data: { data: mockExchange }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<ExchangeDetailModal />);
    // type is 'offer', so badge text is 'Offering'
    expect(getByText('Offering')).toBeTruthy();
  });

  it('opens author profiles from HeroUI Native-backed author cards', () => {
    mockUseApi.mockReturnValue({ data: { data: mockExchange }, isLoading: false, error: null, refresh: jest.fn() });
    const { router } = require('expo-router');

    const { getByLabelText } = render(<ExchangeDetailModal />);
    fireEvent.press(getByLabelText('Alice Baker'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/member-profile',
      params: { id: '42' },
    });
  });

  it('renders backend listing image galleries from detail responses', () => {
    mockUseApi.mockReturnValue({
      data: {
        data: {
          ...mockExchange,
          images: [
            { id: 10, url: '/uploads/listings/first.jpg', sort_order: 0, alt_text: 'Fresh bread on a table' },
            { id: 11, url: '/uploads/listings/second.jpg', sort_order: 1, alt_text: null },
          ],
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByLabelText } = render(<ExchangeDetailModal />);

    fireEvent.press(getByLabelText('Show listing image 2'));
    expect(getByLabelText('Show listing image 1')).toBeTruthy();
    expect(getByLabelText('Show listing image 2')).toBeTruthy();
  });
});
