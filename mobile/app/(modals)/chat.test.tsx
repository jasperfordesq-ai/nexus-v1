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
        'chat:title': 'AI Chat',
        'chat:placeholder': 'Ask me anything…',
        'chat:send': 'Send',
        'chat:empty': 'Start a conversation! Ask me anything about your community.',
        'chat:thinking': 'Thinking…',
        'chat:error': 'Sorry, something went wrong. Please try again.',
        'chat:disclaimer': 'AI responses may be inaccurate. Do not share personal data.',
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
  }),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/chat', () => ({
  sendChatMessage: jest.fn().mockResolvedValue({
    data: {
      conversation_id: 'conv-1',
      message: { id: 'msg-2', role: 'assistant', content: 'Hello!', created_at: new Date().toISOString() },
    },
  }),
}));

// --- Tests ---

import ChatScreen from './chat';

describe('ChatScreen', () => {
  it('renders the chat screen without crashing', () => {
    const { toJSON } = render(<ChatScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the message text input', () => {
    const { getByPlaceholderText } = render(<ChatScreen />);
    expect(getByPlaceholderText('Ask me anything…')).toBeTruthy();
  });

  it('renders the send button', () => {
    const { getByAccessibilityHint, getByLabelText } = render(<ChatScreen />);
    // Send button has accessibilityLabel="Send"
    expect(getByLabelText('Send')).toBeTruthy();
  });

  it('renders the disclaimer text', () => {
    const { getByText } = render(<ChatScreen />);
    expect(getByText('AI responses may be inaccurate. Do not share personal data.')).toBeTruthy();
  });

  it('renders the empty state message when no messages exist', () => {
    const { getByText } = render(<ChatScreen />);
    expect(getByText('Start a conversation! Ask me anything about your community.')).toBeTruthy();
  });
});
