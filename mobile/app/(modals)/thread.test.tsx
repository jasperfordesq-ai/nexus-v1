// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';

// --- Mocks ---

let mockThreadSearchParams: Record<string, string> = { id: '5', name: 'Alice' };
const mockRouterPush = jest.fn();
type MockThreadMessage = {
  id: number;
  body: string;
  sender: { id: number; name: string; avatar_url: null };
  sender_id?: number;
  created_at: string;
  is_own: boolean;
  is_voice: boolean;
  audio_url: null;
  reactions: Record<string, number>;
  is_read: boolean;
};
let mockRealtimeCallback: ((message: MockThreadMessage) => void) | null = null;

jest.mock('expo-router', () => ({
  router: { push: (...args: unknown[]) => mockRouterPush(...args), back: jest.fn() },
  useLocalSearchParams: () => mockThreadSearchParams,
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'thread.invalidConversation': 'Invalid conversation.',
        'thread.loadError': 'Failed to load messages.',
        'thread.inputPlaceholder': 'Type a message...',
        'thread.send': 'Send',
        'thread.voiceMessage': 'Voice message',
        'thread.sendFailed': 'Message not sent.',
        'thread.goBack': 'Go back',
        'thread.messageCount': '2 messages',
        'context.regarding': 'Regarding',
        'context.open': 'Open context',
        'context.title': `${String(options?.type ?? '')} #${String(options?.id ?? '')}`,
        'context.type.listing': 'Listing',
        'context.type.event': 'Event',
        'context.type.job': 'Job',
        'context.type.volunteering': 'Volunteering',
        'messages:send': 'Send',
        'errors.sendFailed': 'Send failed',
        'common:buttons.retry': 'Retry',
        'common:labels.you': 'You',
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

jest.mock('@/lib/context/RealtimeContext', () => ({
  useRealtimeContext: () => ({
    subscribeToMessages: jest.fn((_threadId: number, callback: (message: MockThreadMessage) => void) => {
      mockRealtimeCallback = callback;
      return jest.fn();
    }),
  }),
}));

const mockMarkConversationRead = jest.fn().mockResolvedValue({ data: { marked_read: 1 } });

jest.mock('@/lib/api/messages', () => ({
  getThread: jest.fn(),
  getOrCreateThread: jest.fn(),
  markConversationRead: (...args: unknown[]) => mockMarkConversationRead(...args),
  sendMessage: jest.fn().mockResolvedValue({ data: { id: 99 } }),
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  displayName: (user: any) => user?.name ?? 'Unknown',
}));

jest.mock('expo-haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Test User' }, isAuthenticated: true }),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/OfflineBanner', () => () => null);
jest.mock('@/components/VoiceMessageBubble', () => 'View');

// --- Tests ---

import ThreadScreen from './thread';
import { sendMessage } from '@/lib/api/messages';

const mockMessages = [
  {
    id: 1,
    body: 'Hello there!',
    sender: { id: 5, name: 'Alice', avatar_url: null },
    created_at: '2026-03-10T10:00:00Z',
    is_own: false,
    is_voice: false,
    audio_url: null,
    reactions: {},
    is_read: true,
  },
  {
    id: 2,
    body: 'Hi back!',
    sender: { id: 1, name: 'Me', avatar_url: null },
    created_at: '2026-03-10T10:01:00Z',
    is_own: true,
    is_voice: false,
    audio_url: null,
    reactions: {},
    is_read: true,
  },
];

beforeEach(() => {
  mockThreadSearchParams = { id: '5', name: 'Alice' };
  mockRouterPush.mockClear();
  mockRealtimeCallback = null;
  mockMarkConversationRead.mockClear();
  (sendMessage as jest.Mock).mockClear();
  mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });
});

describe('ThreadScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockMessages },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { toJSON } = render(<ThreadScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the message input and send button', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockMessages },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByLabelText, getByPlaceholderText } = render(<ThreadScreen />);
    expect(getByPlaceholderText('Type a message...')).toBeTruthy();
    expect(getByLabelText('Send')).toBeTruthy();
  });

  it('renders message bubbles when messages are loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockMessages },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<ThreadScreen />);
    expect(getByText('Hello there!')).toBeTruthy();
    expect(getByText('Hi back!')).toBeTruthy();
  });

  it('renders loading state without crashing', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    expect(() => render(<ThreadScreen />)).not.toThrow();
  });

  it('renders error state with retry when load fails', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: 'Network error', refresh: jest.fn() });

    const { getByText } = render(<ThreadScreen />);
    expect(getByText('Failed to load messages.')).toBeTruthy();
    expect(getByText('Retry')).toBeTruthy();
  });

  it('sends replies to the other user from conversation metadata', async () => {
    mockUseApi.mockReturnValue({
      data: {
        data: mockMessages,
        meta: {
          conversation: {
            other_user: { id: 42, name: 'Alice' },
          },
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByLabelText, getByPlaceholderText } = render(<ThreadScreen />);

    fireEvent.changeText(getByPlaceholderText('Type a message...'), 'Thanks Alice');
    fireEvent.press(getByLabelText('Send'));

    await waitFor(() => {
      expect(sendMessage).toHaveBeenCalledWith(42, 'Thanks Alice');
    });
  });

  it('sends contextual fields for a new conversation from deep-link params', async () => {
    mockThreadSearchParams = {
      recipientId: '42',
      name: 'Alice',
      listing: '9',
      context_type: 'job',
      context_id: '44',
    };
    mockUseApi.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByLabelText, getByPlaceholderText } = render(<ThreadScreen />);

    fireEvent.changeText(getByPlaceholderText('Type a message...'), 'I can help with this.');
    fireEvent.press(getByLabelText('Send'));

    await waitFor(() => {
      expect(sendMessage).toHaveBeenCalledWith(42, 'I can help with this.', {
        listing_id: 9,
        context_type: 'job',
        context_id: 44,
      });
    });
  });

  it('shows supported context cards that open native detail routes', () => {
    mockThreadSearchParams = {
      recipientId: '42',
      name: 'Alice',
      context_type: 'job',
      context_id: '44',
    };
    mockUseApi.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByLabelText, getByText } = render(<ThreadScreen />);

    expect(getByText('Regarding')).toBeTruthy();
    expect(getByText('Job #44')).toBeTruthy();

    fireEvent.press(getByLabelText('Open context'));

    expect(mockRouterPush).toHaveBeenCalledWith({
      pathname: '/(modals)/job-detail',
      params: { id: '44' },
    });
  });

  it('marks realtime inbound messages as read while the thread is open', async () => {
    mockUseApi.mockReturnValue({
      data: { data: mockMessages },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<ThreadScreen />);

    expect(mockRealtimeCallback).toBeTruthy();
    act(() => {
      mockRealtimeCallback?.({
        id: 3,
        body: 'Fresh update',
        sender: { id: 5, name: 'Alice', avatar_url: null },
        sender_id: 5,
        created_at: '2026-03-10T10:02:00Z',
        is_own: false,
        is_voice: false,
        audio_url: null,
        reactions: {},
        is_read: false,
      });
    });

    await waitFor(() => {
      expect(getByText('Fresh update')).toBeTruthy();
      expect(mockMarkConversationRead).toHaveBeenCalledWith(5);
    });
  });
});
