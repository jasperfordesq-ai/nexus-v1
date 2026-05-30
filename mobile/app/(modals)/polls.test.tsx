// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  router: { back: jest.fn(), canGoBack: jest.fn(() => false), push: jest.fn() },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'pollsScreen.title': 'Polls',
        'pollsScreen.heroEyebrow': 'Community voice',
        'pollsScreen.subtitle': 'Vote on open questions and see what your community thinks.',
        'pollsScreen.emptyTitle': 'No polls yet',
        'pollsScreen.emptySubtitle': 'When members create polls, they will appear here.',
        'pollsScreen.errorTitle': 'Could not load polls',
        'pollsScreen.feedItemTitle': opts ? String(opts.title ?? 'Poll') : 'Poll',
        'pollsScreen.statusOpen': 'Open',
        'pollsScreen.statusClosed': 'Closed',
        'pollsScreen.totalVotes': opts ? `${String(opts.count ?? 0)} votes` : '0 votes',
        'pollsScreen.createPoll': 'Create poll',
        'pollsScreen.createTitle': 'Create a poll',
        'pollsScreen.questionLabel': 'Question',
        'pollsScreen.questionPlaceholder': 'Ask a question',
        'pollsScreen.descriptionLabel': 'Description',
        'pollsScreen.descriptionPlaceholder': 'Add context',
        'pollsScreen.optionsLabel': 'Options',
        'pollsScreen.optionPlaceholder': opts ? `Option ${String(opts.number ?? 1)}` : 'Option',
        'pollsScreen.addOption': 'Add option',
        'pollsScreen.removeOption': opts ? `Remove option ${String(opts.number ?? 1)}` : 'Remove option',
        'pollsScreen.submitPoll': 'Publish poll',
        'pollsScreen.creating': 'Publishing',
        'pollsScreen.createMissingTitle': 'Poll not ready',
        'pollsScreen.createQuestionRequired': 'Add a question.',
        'pollsScreen.createOptionsRequired': 'Add at least two options.',
        'pollsScreen.createdTitle': 'Poll created',
        'pollsScreen.createdMessage': 'Your poll is now open.',
        'pollsScreen.createError': 'Could not create poll.',
        'common:buttons.retry': 'Retry',
        'common:cancel': 'Cancel',
        'common:errors.alertTitle': 'Something went wrong',
        'common:endOfList': 'End of list',
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#111827',
    textSecondary: '#4b5563',
    onPrimary: '#ffffff',
    border: '#d1d5db',
    error: '#dc2626',
  }),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@/components/PollCard', () => {
  const React = require('react');
  const { Text, View } = require('react-native');
  return ({ pollData }: { pollData: { question: string; total_votes?: number } }) => (
    <View>
      <Text>{pollData.question}</Text>
      <Text>{`${String(pollData.total_votes ?? 0)} votes`}</Text>
    </View>
  );
});

jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);

jest.mock('@/lib/api/feed', () => ({
  getFeed: jest.fn(),
  getFeedAuthor: () => ({ id: 4, name: 'Poll Author', avatar: null }),
}));

jest.mock('@/lib/api/polls', () => ({
  createPoll: jest.fn().mockResolvedValue({ data: { id: 99 } }),
}));

import PollsScreen from './polls';
import { createPoll } from '@/lib/api/polls';

const defaultState = {
  items: [],
  isLoading: false,
  isLoadingMore: false,
  error: null,
  hasMore: false,
  loadMore: jest.fn(),
  refresh: jest.fn(),
};

describe('PollsScreen', () => {
  beforeEach(() => {
    mockUsePaginatedApi.mockReset();
    mockUsePaginatedApi.mockReturnValue(defaultState);
    jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
    (createPoll as jest.Mock).mockClear();
  });

  it('renders the translated polls empty state', () => {
    const { getAllByText, getByText } = render(<PollsScreen />);

    expect(getAllByText('Polls').length).toBeGreaterThan(0);
    expect(getByText('No polls yet')).toBeTruthy();
    expect(getByText('When members create polls, they will appear here.')).toBeTruthy();
  });

  it('renders active poll cards from the feed polls query', () => {
    mockUsePaginatedApi.mockReturnValue({
      ...defaultState,
      items: [
        {
          id: 12,
          type: 'poll',
          title: 'Lunch choice',
          content: null,
          poll_data: {
            id: 12,
            question: 'Which lunch should we host?',
            total_votes: 4,
            user_vote_option_id: null,
            is_active: true,
            options: [
              { id: 1, text: 'Soup', vote_count: 2, percentage: 50 },
              { id: 2, text: 'Sandwiches', vote_count: 2, percentage: 50 },
            ],
          },
        },
      ],
    });

    const { getByText } = render(<PollsScreen />);

    expect(getByText('Lunch choice')).toBeTruthy();
    expect(getByText('Which lunch should we host?')).toBeTruthy();
    expect(getByText('4 votes')).toBeTruthy();
  });

  it('creates a standard poll from the native create form', async () => {
    const refresh = jest.fn();
    mockUsePaginatedApi.mockReturnValue({ ...defaultState, refresh });

    const { getByPlaceholderText, getByText } = render(<PollsScreen />);

    fireEvent.press(getByText('Create poll'));
    fireEvent.changeText(getByPlaceholderText('Ask a question'), 'Which lunch should we host?');
    fireEvent.changeText(getByPlaceholderText('Option 1'), 'Soup');
    fireEvent.changeText(getByPlaceholderText('Option 2'), 'Sandwiches');
    fireEvent.press(getByText('Publish poll'));

    expect(createPoll).toHaveBeenCalledWith({
      question: 'Which lunch should we host?',
      description: undefined,
      options: ['Soup', 'Sandwiches'],
      poll_type: 'standard',
      is_anonymous: false,
    });
    await Promise.resolve();
    expect(Alert.alert).toHaveBeenCalledWith('Poll created', 'Your poll is now open.');
    expect(refresh).toHaveBeenCalled();
  });
});
