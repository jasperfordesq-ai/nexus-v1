// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn(), replace: jest.fn(), canGoBack: jest.fn(() => false) },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'common:buttons.retry': 'Retry',
        'connections.title': 'Connections',
        'connections.eyebrow': 'Member workflows',
        'connections.subtitle': 'Manage members you are connected with.',
        'connections.tabs.accepted': 'Connected',
        'connections.tabs.pending_received': 'Received',
        'connections.tabs.pending_sent': 'Sent',
        'connections.empty.accepted.title': 'No connections yet',
        'connections.empty.accepted.description': 'Find members and send connection requests.',
        'connections.browseMembers': 'Browse members',
        'connections.viewProfile': opts ? `View profile for ${String(opts.name ?? '')}` : 'View profile',
        'connections.status.accepted': 'Connected',
        'connections.message': 'Message',
        'connections.remove': 'Remove',
        'connections.connectedSince': opts ? `Connected ${String(opts.date ?? '')}` : 'Connected',
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

jest.mock('@/lib/api/connections', () => ({
  acceptConnection: jest.fn(),
  getConnections: jest.fn(),
  removeConnection: jest.fn(),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');

import ConnectionsRoute from './connections';

beforeEach(() => {
  jest.clearAllMocks();
  mockUseApi.mockReturnValue({ data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() });
});

const connection = {
  connection_id: 12,
  id: 12,
  status: 'accepted' as const,
  created_at: '2026-05-01T12:00:00Z',
  user: {
    id: 272,
    name: 'Katherine',
    avatar_url: null,
    location: 'Cork',
    bio: 'Gardening and repair swaps',
  },
};

describe('ConnectionsRoute', () => {
  it('renders the accepted empty state with browse members action', () => {
    const { getByText } = render(<ConnectionsRoute />);
    expect(getByText('No connections yet')).toBeTruthy();
    expect(getByText('Browse members')).toBeTruthy();
  });

  it('renders connection cards and routes to profile and thread', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: [connection] }, isLoading: false, error: null, refresh: jest.fn() });
    const { router } = require('expo-router');
    const { getByText, getByLabelText } = render(<ConnectionsRoute />);
    expect(getByText('Katherine')).toBeTruthy();
    expect(getByText('Cork')).toBeTruthy();

    fireEvent.press(getByLabelText('View profile for Katherine'));
    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/member-profile',
      params: { id: '272' },
    });

    router.push.mockClear();
    fireEvent.press(getByText('Message'));
    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/thread',
      params: { recipientId: '272', name: 'Katherine' },
    });
  });
});
