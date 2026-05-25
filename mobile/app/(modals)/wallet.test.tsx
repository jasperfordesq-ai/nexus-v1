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
    t: (key: string) => {
      const map: Record<string, string> = {
        'title': 'Wallet',
        'timeCredits': 'Time Credits',
        'noTransactions': 'No transactions yet.',
        'hrs': 'hrs',
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

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/wallet', () => ({
  getWalletBalance: jest.fn(),
  getWalletTransactions: jest.fn(),
}));

jest.mock('@/components/ui/Avatar', () => 'View');

// --- Tests ---

import WalletModal from './wallet';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockTransaction = {
  id: 101,
  type: 'credit' as const,
  amount: 2.5,
  description: 'Tutoring session',
  status: 'completed' as const,
  created_at: '2026-02-14T10:00:00Z',
  other_user: { id: 5, name: 'Bob Smith', avatar_url: null },
};

const mockDebitTransaction = {
  id: 102,
  type: 'debit' as const,
  amount: 1.0,
  description: 'Garden help',
  status: 'completed' as const,
  created_at: '2026-02-15T12:00:00Z',
  other_user: { id: 6, name: 'Carol Jones', avatar_url: null },
};

describe('WalletModal', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<WalletModal />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the Time Credits label', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 12.5 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('Time Credits')).toBeTruthy();
  });

  it('renders the balance value when loaded', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 12.5 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('12.5')).toBeTruthy();
  });

  it('renders empty state when there are no transactions', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 0.0 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('No transactions yet.')).toBeTruthy();
  });

  it('renders transaction rows when transactions are available', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 12.5 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [mockTransaction] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('Bob Smith')).toBeTruthy();
    expect(getByText('Tutoring session')).toBeTruthy();
  });

  it('renders credit amount with + sign', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 12.5 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [mockTransaction] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('+2.5 hrs')).toBeTruthy();
  });

  it('renders debit transaction row', () => {
    mockUseApi
      .mockReturnValueOnce({ data: { data: { balance: 12.5 } }, isLoading: false, error: null, refresh: jest.fn() })
      .mockReturnValueOnce({ data: { data: [mockDebitTransaction] }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<WalletModal />);
    expect(getByText('Carol Jones')).toBeTruthy();
    expect(getByText('Garden help')).toBeTruthy();
  });
});
