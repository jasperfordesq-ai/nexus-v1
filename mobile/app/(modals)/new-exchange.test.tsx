// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'newExchange': 'New Exchange',
        'type': 'Type',
        'offer': 'Offer',
        'request': 'Request',
        'typeLabel': opts ? `Type: ${String(opts.type ?? '')}` : 'Type',
        'titleLabel': 'Title',
        'offerPlaceholder': 'What are you offering?',
        'requestPlaceholder': 'What do you need?',
        'description': 'Description',
        'descriptionPlaceholder': 'Add more details...',
        'timeCredits': 'Time Credits',
        'category': 'Category',
        'categoryLabel': opts ? String(opts.name ?? '') : '',
        'postOffer': 'Post Offer',
        'postRequest': 'Post Request',
        'createError': 'Failed to create exchange.',
        'validation.titleRequired': 'Title is required.',
        'validation.invalidCredits': 'Enter a valid number of credits.',
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
    errorBg: '#fff5f5',
    success: '#22c55e',
    infoBg: '#ebf8ff',
    info: '#3182ce',
    successBg: '#f0fff4',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/api/exchanges', () => ({
  createExchange: jest.fn().mockResolvedValue({ data: { id: 1 } }),
}));

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn().mockResolvedValue({ data: [] }) },
  ApiResponseError: class ApiResponseError extends Error {
    constructor(msg: string) { super(msg); }
  },
}));

jest.mock('@/lib/constants', () => ({ API_V2: '/api/v2' }));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));

jest.mock('@/components/OfflineBanner', () => () => null);

// --- Tests ---

import NewExchangeModal from './new-exchange';

beforeEach(() => {
  mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });
});

describe('NewExchangeModal', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<NewExchangeModal />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the type toggle with Offer and Request options', () => {
    const { getByText } = render(<NewExchangeModal />);
    expect(getByText('Offer')).toBeTruthy();
    expect(getByText('Request')).toBeTruthy();
  });

  it('renders the title and description inputs', () => {
    const { getByPlaceholderText } = render(<NewExchangeModal />);
    expect(getByPlaceholderText('What are you offering?')).toBeTruthy();
    expect(getByPlaceholderText('Add more details...')).toBeTruthy();
  });

  it('renders the Post Offer submit button by default', () => {
    const { getByText } = render(<NewExchangeModal />);
    expect(getByText('Post Offer')).toBeTruthy();
  });

  it('switches to Request mode when Request is pressed', () => {
    const { getByText } = render(<NewExchangeModal />);
    fireEvent.press(getByText('Request'));
    expect(getByText('Post Request')).toBeTruthy();
  });

  it('shows category chips when categories are loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: [{ id: 1, name: 'Gardening' }, { id: 2, name: 'Teaching' }] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<NewExchangeModal />);
    expect(getByText('Gardening')).toBeTruthy();
    expect(getByText('Teaching')).toBeTruthy();
  });
});
