// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

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
        'common:buttons.retry': 'Retry',
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
  }),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('@/components/PollCard', () => {
  const { Text } = require('react-native');
  return ({ pollData }: { pollData: { question: string } }) => <Text>{pollData.question}</Text>;
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

import PollsScreen from './polls';

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
});
