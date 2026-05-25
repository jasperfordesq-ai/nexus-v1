// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), back: jest.fn() }),
  useSegments: () => ['(tabs)'],
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({}),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'timeBalance': 'Time balance',
        'about': 'About',
        'viewWallet': 'View wallet',
        'editProfile': 'Edit profile',
        'browseMembers': 'Browse Members',
        'settings': 'Settings',
        'signOut': 'Sign out',
        'signOutConfirmTitle': 'Sign out',
        'signOutConfirmMessage': 'Are you sure you want to sign out?',
        'changePhoto': 'Change profile photo',
        'permissionNeeded': 'Permission needed',
        'permissionMessage': 'Please allow access to your photo library to change your avatar.',
        'uploadFailed': 'Upload failed',
        'uploadFailedMessage': 'Could not update your avatar. Please try again.',
        'hrs': 'hrs',
        'explore': 'Explore',
        'groups': 'Groups',
        'search': 'Search',
        'aiChat': 'AI Assistant',
        'achievements': 'Achievements',
        'myGoals': 'My Goals',
        'volunteering': 'Volunteering',
        'organisations': 'Organisations',
        'blog': 'Blog',
        'skills': 'Skills & Endorsements',
        'federation': 'Federation',
        'common:buttons.cancel': 'Cancel',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

const mockLogout = jest.fn();
const mockRefreshUser = jest.fn();
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
    bio: 'I love community gardening.',
  },
  displayName: 'Alice Smith',
  logout: mockLogout,
  refreshUser: mockRefreshUser,
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
    borderSubtle: '#eeeeee',
    error: '#e53e3e',
    errorBg: '#fff5f5',
  }),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Warning: 'warning', Error: 'error' },
}));

jest.mock('expo-image-picker', () => ({
  launchImageLibraryAsync: jest.fn().mockResolvedValue({ canceled: true, assets: null }),
  requestMediaLibraryPermissionsAsync: jest.fn().mockResolvedValue({ status: 'granted' }),
  MediaTypeOptions: { Images: 'Images' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/profile', () => ({
  updateAvatar: jest.fn(),
}));

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn() },
}));

jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
}));

jest.mock('@/components/ui/Avatar', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  ProfileSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
}));

// --- Tests ---

import ProfileScreen from './profile';

describe('ProfileScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue(defaultAuthState);
  });

  it('renders the user display name and email', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Alice Smith')).toBeTruthy();
    expect(getByText('alice@example.com')).toBeTruthy();
  });

  it('renders the time balance card when balance is present', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Time balance')).toBeTruthy();
    expect(getByText('4.5 hrs')).toBeTruthy();
  });

  it('renders the bio when provided', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('I love community gardening.')).toBeTruthy();
  });

  it('renders action buttons: View wallet, Edit profile, Browse Members, Settings', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('View wallet')).toBeTruthy();
    expect(getByText('Edit profile')).toBeTruthy();
    expect(getByText('Browse Members')).toBeTruthy();
    expect(getByText('Settings')).toBeTruthy();
  });

  it('renders the Explore section with explore items', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Explore')).toBeTruthy();
    expect(getByText('Groups')).toBeTruthy();
    expect(getByText('AI Assistant')).toBeTruthy();
    expect(getByText('Achievements')).toBeTruthy();
  });

  it('renders the Sign out button', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText('Sign out')).toBeTruthy();
  });

  it('renders ProfileSkeleton when user is null', () => {
    mockUseAuth.mockReturnValueOnce({
      user: null,
      displayName: '',
      logout: jest.fn(),
      refreshUser: jest.fn(),
    });

    // With no user, the screen renders the skeleton — none of the profile content should appear
    const { queryByText } = render(<ProfileScreen />);
    expect(queryByText('Alice Smith')).toBeNull();
    expect(queryByText('alice@example.com')).toBeNull();
  });

  it('renders the AGPL attribution footer', () => {
    const { getByText } = render(<ProfileScreen />);
    expect(getByText(/AGPL-3\.0-or-later/)).toBeTruthy();
  });
});
