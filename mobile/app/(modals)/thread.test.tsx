// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '5', name: 'Alice' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'thread.invalidConversation': 'Invalid conversation.',
        'thread.loadError': 'Failed to load messages.',
        'thread.inputPlaceholder': 'Type a message...',
        'thread.send': 'Send',
        'thread.voiceMessage': 'Voice message',
        'thread.sendFailed': 'Message not sent.',
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
    subscribeToMessages: jest.fn(() => jest.fn()),
  }),
}));

jest.mock('@/lib/api/messages', () => ({
  getThread: jest.fn(),
  getOrCreateThread: jest.fn(),
  sendMessage: jest.fn().mockResolvedValue({ data: { id: 99 } }),
}));

jest.mock('expo-haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/OfflineBanner', () => () => null);
jest.mock('@/components/VoiceMessageBubble', () => 'View');

// --- Tests ---

import ThreadScreen from './thread';

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

    const { getByPlaceholderText, getByText } = render(<ThreadScreen />);
    expect(getByPlaceholderText('Type a message...')).toBeTruthy();
    expect(getByText('Send')).toBeTruthy();
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
});
