// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';

// --- Mocks ---

const mockRouterPush = jest.fn();
const mockRouterReplace = jest.fn();
const mockSearchParams = jest.fn(() => ({}));

jest.mock('expo-router', () => ({
  __esModule: true,
  useRouter: () => ({ push: mockRouterPush, replace: mockRouterReplace, back: jest.fn() }),
  useSegments: () => ['(tabs)'],
  router: { push: mockRouterPush, replace: mockRouterReplace, back: jest.fn() },
  useLocalSearchParams: () => mockSearchParams(),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'title': 'Messages',
        'newMessage': 'New message',
        'newGroup': 'New group',
        'tabs.inbox': 'Inbox',
        'tabs.archived': 'Archived',
        'restore': 'Restore',
        'restoreConversation': 'Restore conversation',
        'restoreConversationWithName': 'Restore conversation with Bob Builder',
        'empty.title': 'No conversations yet',
        'empty.archivedTitle': 'No archived conversations',
        'empty.archivedSubtitle': 'Archived conversations will appear here.',
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
  restoreConversation: jest.fn().mockResolvedValue({ data: { success: true } }),
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
import { restoreConversation } from '@/lib/api/messages';

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
  mockRouterPush.mockReset();
  mockRouterReplace.mockReset();
  mockSearchParams.mockReturnValue({});
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

  it('routes deep-linked recipients to the native thread composer', () => {
    mockSearchParams.mockReturnValue({ to: '260', name: 'Jasper Ford', listing: '90624' });

    render(<MessagesScreen />);

    expect(mockRouterPush).toHaveBeenCalledWith({
      pathname: '/(modals)/thread',
      params: {
        recipientId: '260',
        name: 'Jasper Ford',
        listing: '90624',
      },
    });
  });

  it('routes contextual user query links to the native thread composer', () => {
    mockSearchParams.mockReturnValue({ user: '260', name: 'Jasper Ford', context: 'job', context_id: '44' });

    render(<MessagesScreen />);

    expect(mockRouterPush).toHaveBeenCalledWith({
      pathname: '/(modals)/thread',
      params: {
        recipientId: '260',
        name: 'Jasper Ford',
        context_type: 'job',
        context_id: '44',
      },
    });
  });

  it('opens the new group route from the header action', () => {
    const { getByLabelText } = render(<MessagesScreen />);

    fireEvent.press(getByLabelText('New group'));

    expect(mockRouterPush).toHaveBeenCalledWith({ pathname: '/(modals)/new-group' });
  });

  it('shows archived conversations and restores them from the Archived tab', async () => {
    const inboxRefresh = jest.fn();
    const archivedRefresh = jest.fn();
    mockUsePaginatedApi.mockImplementation(() => (
      mockUsePaginatedApi.mock.calls.length % 2 === 1
        ? { ...defaultPaginatedState, refresh: inboxRefresh }
        : { ...defaultPaginatedState, items: [mockConversation], refresh: archivedRefresh }
    ));

    const { getByText, getByLabelText } = render(<MessagesScreen />);

    fireEvent.press(getByText('Archived'));

    expect(getByText('Bob Builder')).toBeTruthy();
    expect(getByText('Can you help with plumbing?')).toBeTruthy();

    fireEvent.press(getByLabelText('Restore conversation with Bob Builder'));

    await waitFor(() => {
      expect(restoreConversation).toHaveBeenCalledWith(7);
    });
    expect(inboxRefresh).toHaveBeenCalled();
    expect(archivedRefresh).toHaveBeenCalled();
  });
});
