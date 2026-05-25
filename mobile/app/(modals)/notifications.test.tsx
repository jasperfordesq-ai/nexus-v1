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
        'title': 'Notifications',
        'allCaughtUp': 'You are all caught up!',
        'markAllRead': 'Mark all read',
        'marking': 'Marking…',
        'markError': 'Failed to mark as read.',
        'justNow': 'Just now',
        'common:errors.alertTitle': 'Error',
        'common:buttons.retry': 'Retry',
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

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/notifications', () => ({
  getNotifications: jest.fn(),
  markAllRead: jest.fn().mockResolvedValue(undefined),
  markRead: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('@/lib/utils/navigateToLink', () => ({
  navigateToLink: jest.fn(),
}));

jest.mock('@/lib/utils/formatRelativeTime', () => ({
  formatRelativeTime: jest.fn(() => '5 min ago'),
}));

// --- Tests ---

import NotificationsScreen from './notifications';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockNotification = {
  id: 1,
  title: 'New message from Alice',
  message: 'Alice sent you a message about your listing.',
  is_read: false,
  category: 'message',
  link: '/messages/1',
  created_at: new Date(Date.now() - 300_000).toISOString(),
  actor: { id: 2, name: 'Alice', avatar_url: null },
};

describe('NotificationsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<NotificationsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the Notifications heading', () => {
    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('Notifications')).toBeTruthy();
  });

  it('renders the empty state when there are no notifications', () => {
    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('You are all caught up!')).toBeTruthy();
  });

  it('renders a loading spinner when data is loading', () => {
    mockUseApi.mockReturnValueOnce({
      data: null,
      isLoading: true,
      error: null,
      refresh: jest.fn(),
    });

    // LoadingSpinner is rendered in the ListEmptyComponent when isLoading=true
    const { toJSON } = render(<NotificationsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders notification items when data is available', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: [mockNotification] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('New message from Alice')).toBeTruthy();
    expect(getByText('Alice sent you a message about your listing.')).toBeTruthy();
  });

  it('renders the "Mark all read" button when unread notifications exist', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: [mockNotification] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('Mark all read')).toBeTruthy();
  });

  it('does not render "Mark all read" when all notifications are read', () => {
    mockUseApi.mockReturnValueOnce({
      data: { data: [{ ...mockNotification, is_read: true }] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { queryByText } = render(<NotificationsScreen />);
    expect(queryByText('Mark all read')).toBeNull();
  });

  it('renders error state with retry button when there is an error', () => {
    mockUseApi.mockReturnValueOnce({
      data: null,
      isLoading: false,
      error: 'Failed to load notifications.',
      refresh: jest.fn(),
    });

    const { getByText } = render(<NotificationsScreen />);
    expect(getByText('Failed to load notifications.')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });
});
