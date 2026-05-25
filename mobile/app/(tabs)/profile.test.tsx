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
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'timeBalance': 'Time balance',
        'viewWallet': 'View wallet',
        'editProfile': 'Edit profile',
        'browseMembers': 'Browse Members',
        'settings': 'Settings',
        'signOut': 'Sign out',
        'signOutConfirmTitle': 'Sign out',
        'signOutConfirmMessage': 'Are you sure you want to sign out?',
        'hrs': 'hrs',
        'groups': 'Groups',
        'aiChat': 'AI Assistant',
        'achievements': 'Achievements',
        'myGoals': 'My Goals',
        'volunteering': 'Volunteering',
        'organisations': 'Organisations',
        'federation': 'Federation',
        'myProfile': 'My Profile',
        'mySpace': 'My Space',
        'discover': 'Discover',
        'account': 'Account',
        'common:buttons.cancel': 'Cancel',
        'common:attribution': 'Project NEXUS is open-source software licensed under AGPL-3.0-or-later.',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

const mockLogout = jest.fn();
const mockUseAuth = jest.fn();

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
}));

const defaultAuthState = {
  user: {
    id: 1,
    email: 'alice@example.com',
    name: 'Alice Smith',
    avatar_url: null,
    balance: 4.5,
  },
  displayName: 'Alice Smith',
  logout: mockLogout,
  refreshUser: jest.fn(),
};

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
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
  }),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Warning: 'warning', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/components/ui/Avatar', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  ProfileSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
}));

// --- Tests ---

import MoreScreen from './profile';

describe('MoreScreen (More tab)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue(defaultAuthState);
  });

  it('renders the user display name and email', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Alice Smith')).toBeTruthy();
    expect(getByText('alice@example.com')).toBeTruthy();
  });

  it('renders the time balance chip when balance is present', () => {
    const { getByText } = render(<MoreScreen />);
    // Chip text: "{balance} hrs · Time balance"
    expect(getByText('4.5 hrs · Time balance')).toBeTruthy();
  });

  it('renders Edit Profile and View Wallet action buttons', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Edit profile')).toBeTruthy();
    expect(getByText('View wallet')).toBeTruthy();
  });

  it('renders My Space section with profile navigation items', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('My Space')).toBeTruthy();
    expect(getByText('My Profile')).toBeTruthy();
    expect(getByText('Achievements')).toBeTruthy();
    expect(getByText('My Goals')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
  });

  it('renders Discover section with community navigation items', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Discover')).toBeTruthy();
    expect(getByText('Browse Members')).toBeTruthy();
    expect(getByText('Volunteering')).toBeTruthy();
    expect(getByText('AI Assistant')).toBeTruthy();
  });

  it('renders Settings in the Account section', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Account')).toBeTruthy();
    expect(getByText('Settings')).toBeTruthy();
  });

  it('renders the Sign out button', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText('Sign out')).toBeTruthy();
  });

  it('renders ProfileSkeleton when user is null', () => {
    mockUseAuth.mockReturnValueOnce({
      user: null,
      displayName: '',
      logout: jest.fn(),
      refreshUser: jest.fn(),
    });

    const { queryByText } = render(<MoreScreen />);
    expect(queryByText('Alice Smith')).toBeNull();
    expect(queryByText('alice@example.com')).toBeNull();
  });

  it('renders the AGPL attribution footer', () => {
    const { getByText } = render(<MoreScreen />);
    expect(getByText(/AGPL-3\.0-or-later/)).toBeTruthy();
  });
});
