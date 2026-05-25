// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

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
        'title': 'Settings',
        'pushNotifications': 'Push Notifications',
        'emailNotifications': 'Email Notifications',
        'push.messages': 'Messages',
        'push.transactions': 'Transactions',
        'push.social': 'Social Activity',
        'email.messages': 'Email Messages',
        'email.connections': 'Connections',
        'email.transactions': 'Email Transactions',
        'email.reviews': 'Reviews',
        'about': 'About',
        'version': 'Version',
        'license': 'License',
        'security': 'Security',
        'changePassword': 'Change Password',
        'saveError': 'Failed to save.',
        'common:errors.generic': 'Error',
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

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: () => ({
    data: {
      data: {
        email_messages: true,
        email_connections: true,
        email_transactions: false,
        email_reviews: true,
        push_messages: true,
        push_transactions: true,
        push_social: false,
      },
    },
    isLoading: false,
    error: null,
    refresh: jest.fn(),
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn().mockResolvedValue({ data: {} }),
    put: jest.fn().mockResolvedValue(undefined),
  },
}));

jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
}));

jest.mock('expo-constants', () => ({
  default: {
    expoConfig: { version: '1.0.0' },
  },
}));

// --- Tests ---

import SettingsScreen from './settings';

describe('SettingsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<SettingsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the Push Notifications section heading', () => {
    const { getByText } = render(<SettingsScreen />);
    expect(getByText('Push Notifications')).toBeTruthy();
  });

  it('renders the Email Notifications section heading', () => {
    const { getByText } = render(<SettingsScreen />);
    expect(getByText('Email Notifications')).toBeTruthy();
  });

  it('renders the About section with version info', () => {
    const { getByText } = render(<SettingsScreen />);
    expect(getByText('About')).toBeTruthy();
    expect(getByText('Version')).toBeTruthy();
    expect(getByText('1.0.0')).toBeTruthy();
  });

  it('renders the AGPL-3.0-or-later license label', () => {
    const { getByText } = render(<SettingsScreen />);
    expect(getByText('AGPL-3.0-or-later')).toBeTruthy();
  });

  it('renders the Security section with Change Password button', () => {
    const { getByText } = render(<SettingsScreen />);
    expect(getByText('Security')).toBeTruthy();
    expect(getByText('Change Password')).toBeTruthy();
  });

  it('navigates to change-password when Change Password is pressed', () => {
    const { router } = require('expo-router');
    const { getByText } = render(<SettingsScreen />);
    fireEvent.press(getByText('Change Password'));
    expect(router.push).toHaveBeenCalledWith('/(modals)/change-password');
  });
});
