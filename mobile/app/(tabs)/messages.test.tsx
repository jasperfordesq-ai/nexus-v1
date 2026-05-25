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
        'title': 'Messages',
        'newMessage': 'New message',
        'empty.title': 'No conversations yet',
        'thread.you': 'You',
        'thread.noMessages': 'No messages yet. Say hello!',
        'common:buttons.retry': 'Retry',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

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

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success', Warning: 'warning' },
}));

// Make Swipeable transparent so FlatList items render their children in tests
jest.mock('react-native-gesture-handler', () => {
  const React = require('react');
  const { View } = require('react-native');
  return {
    Swipeable: ({ children }: { children: React.ReactNode }) => React.createElement(View, null, children),
    GestureHandlerRootView: ({ children }: { children: React.ReactNode }) => React.createElement(View, null, children),
    PanGestureHandler: ({ children }: { children: React.ReactNode }) => React.createElement(View, null, children),
    State: {},
  };
});

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/messages', () => ({
  getConversations: jest.fn(),
  deleteConversation: jest.fn().mockResolvedValue(undefined),
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  displayName: (user: any) => user?.name ?? 'Unknown',
}));

jest.mock('@/lib/utils/formatRelativeTime', () => ({
  formatRelativeTime: () => '2h ago',
}));

jest.mock('@/components/ui/Avatar', () => () => null);
jest.mock('@/components/ui/Skeleton', () => ({
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  ProfileSkeleton: () => null,
}));

// --- Tests ---

import MessagesScreen from './messages';

const defaultPaginatedState = {
  items: [],
  isLoading: false,
  isLoadingMore: false,
  error: null,
  hasMore: false,
  loadMore: jest.fn(),
  refresh: jest.fn(),
};

beforeEach(() => {
  mockUsePaginatedApi.mockReturnValue(defaultPaginatedState);
});

const mockConversation = {
  id: 7,
  other_user: { id: 2, name: 'Bob Builder', avatar_url: null },
  last_message: {
    body: 'Can you help with plumbing?',
    created_at: '2026-03-20T14:30:00Z',
    is_own: false,
  },
  unread_count: 0,
};

describe('MessagesScreen', () => {
  it('renders the screen title', () => {
    const { getByText } = render(<MessagesScreen />);
    expect(getByText('Messages')).toBeTruthy();
  });

  it('renders empty state when there are no conversations and not loading', () => {
    const { getByText } = render(<MessagesScreen />);
    expect(getByText('No conversations yet')).toBeTruthy();
  });

  it('does not show empty text while loading', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      isLoading: true,
    });

    const { queryByText } = render(<MessagesScreen />);
    expect(queryByText('No conversations yet')).toBeNull();
  });

  it('renders conversation rows when conversations are loaded', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      items: [mockConversation],
    });

    const { getByText } = render(<MessagesScreen />);
    expect(getByText('Bob Builder')).toBeTruthy();
    expect(getByText('Can you help with plumbing?')).toBeTruthy();
  });

  it('shows unread badge on conversation with unread messages', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      items: [{ ...mockConversation, unread_count: 4 }],
    });

    const { getByText } = render(<MessagesScreen />);
    expect(getByText('4')).toBeTruthy();
  });

  it('shows error message with Retry when conversations fail to load', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      error: 'Could not load messages.',
    });

    const { getByText } = render(<MessagesScreen />);
    expect(getByText('Could not load messages.')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });

  it('prefixes own last message with "You: "', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      items: [{
        ...mockConversation,
        last_message: { body: 'Sure, on my way!', created_at: '2026-03-20T15:00:00Z', is_own: true },
      }],
    });

    const { getByText } = render(<MessagesScreen />);
    expect(getByText('You: Sure, on my way!')).toBeTruthy();
  });

  it('shows relative timestamp next to the last message', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultPaginatedState,
      items: [mockConversation],
    });

    const { getByText } = render(<MessagesScreen />);
    expect(getByText('2h ago')).toBeTruthy();
  });
});
