// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockUseApi = jest.fn();
const mockUsePaginatedApi = jest.fn();
const mockRefresh = jest.fn();
const mockMarkRead = jest.fn().mockResolvedValue({});
const mockSendFederationMessage = jest.fn().mockResolvedValue({ data: { id: 202 } });

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'directory.messages.eyebrow': 'Federated inbox',
        'directory.messages.title': 'Federated Messages',
        'directory.messages.subtitle': 'Message members in trusted partner communities.',
        'directory.messages.unknownSender': 'Unknown sender',
        'directory.messages.unreadCount': `${String(opts?.count ?? 0)} unread`,
        'directory.messages.delivered': 'Delivered',
        'directory.messages.threadEyebrow': 'Federated conversation',
        'directory.messages.backToInbox': 'Back to inbox',
        'directory.messages.reply': 'Reply',
        'directory.messages.replyPlaceholder': 'Write a federated reply...',
        'directory.messages.sendReply': 'Send reply',
        'directory.messages.sendFailedTitle': 'Message not sent',
        'directory.messages.sendFailedDescription': 'Please try again.',
        'directory.messages.federated': 'Federated',
        'directory.resultsCount': `${String(opts?.count ?? 0)} results`,
        'directory.unknownCommunity': 'Partner community',
        'common:back': 'Back',
      };
      if (key === 'directory.messages.openThread') return `Open thread with ${String(opts?.name ?? '')}`;
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
    error: '#e53e3e',
    success: '#22c55e',
  }),
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@/lib/api/federation', () => ({
  getFederationEvents: jest.fn(),
  getFederationGroups: jest.fn(),
  getFederationListings: jest.fn(),
  getFederationMembers: jest.fn(),
  getFederationMessages: jest.fn(),
  getFederationPartners: jest.fn(),
  getFederationSettings: jest.fn(),
  getFederationMember: jest.fn(),
  markFederationMessageRead: (...args: unknown[]) => mockMarkRead(...args),
  sendFederationMessage: (...args: unknown[]) => mockSendFederationMessage(...args),
  updateFederationSettings: jest.fn(),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');

  const Button = ({
    children,
    onPress,
    accessibilityLabel,
    isDisabled,
  }: {
    children: React.ReactNode;
    onPress?: () => void;
    accessibilityLabel?: string;
    isDisabled?: boolean;
  }) => (
    <Pressable accessibilityLabel={accessibilityLabel} onPress={isDisabled ? undefined : onPress}>
      <View>{children}</View>
    </Pressable>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

  const Card = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;

  const Chip = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Chip.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;

  return {
    Button,
    Card,
    Chip,
    Spinner: () => null,
    Surface: ({ children }: { children?: React.ReactNode }) => <View>{children}</View>,
  };
});

jest.mock('@/components/ui/AppTopBar', () => 'View');
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/EmptyState', () => 'View');
jest.mock('@/components/ui/Input', () => 'View');
jest.mock('@/components/ui/Toggle', () => 'View');

import FederationMessagesScreen from './federation-messages';

const message = {
  id: 101,
  subject: 'Shared project',
  body: 'Could we coordinate this across communities?',
  direction: 'inbound' as const,
  status: 'unread' as const,
  read_at: null,
  created_at: '2026-05-01T12:00:00Z',
  sender: {
    id: 272,
    name: 'Katherine',
    avatar: null,
    tenant_id: 5,
    tenant_name: 'Cork Timebank',
  },
  receiver: {
    id: 1,
    name: 'Jasper',
    avatar: null,
    tenant_id: 2,
    tenant_name: 'hOUR Timebank',
  },
};

beforeEach(() => {
  mockRefresh.mockClear();
  mockMarkRead.mockClear();
  mockSendFederationMessage.mockClear();
  mockUseApi.mockReturnValue({
    data: { data: [message] },
    isLoading: false,
    error: null,
    refresh: mockRefresh,
  });
  mockUsePaginatedApi.mockReturnValue({
    items: [],
    isLoading: false,
    isLoadingMore: false,
    error: null,
    hasMore: false,
    loadMore: jest.fn(),
    refresh: jest.fn(),
  });
});

describe('FederationMessagesScreen', () => {
  it('opens message cards as usable federated threads and sends replies to the partner', async () => {
    const { getByLabelText, getByPlaceholderText, getByText } = render(<FederationMessagesScreen />);

    fireEvent.press(getByLabelText('Open thread with Katherine'));

    expect(getByText('Federated conversation')).toBeTruthy();
    expect(getByText('Could we coordinate this across communities?')).toBeTruthy();

    await waitFor(() => {
      expect(mockMarkRead).toHaveBeenCalledWith(101);
    });

    fireEvent.changeText(getByPlaceholderText('Write a federated reply...'), 'Yes, let us coordinate.');
    fireEvent.press(getByText('Send reply'));

    await waitFor(() => {
      expect(mockSendFederationMessage).toHaveBeenCalledWith({
        receiver_id: 272,
        receiver_tenant_id: 5,
        subject: 'Shared project',
        body: 'Yes, let us coordinate.',
        reference_message_id: 101,
      });
    });
    expect(mockRefresh).toHaveBeenCalled();
  });
});
