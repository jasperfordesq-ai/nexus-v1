// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockUseApi = jest.fn();
const mockSubmitIdea = jest.fn();
const mockVoteIdea = jest.fn();

jest.mock('expo-router', () => ({
  useLocalSearchParams: () => ({ id: '12' }),
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/api/ideation', () => ({
  getIdeationChallenge: jest.fn(),
  getIdeationIdeas: jest.fn(),
  submitIdeationIdea: (...args: unknown[]) => mockSubmitIdea(...args),
  voteIdeationIdea: (...args: unknown[]) => mockVoteIdea(...args),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({ hasFeature: () => true }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#111827',
    textSecondary: '#4b5563',
    info: '#2563eb',
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return function MockAppTopBar({ title }: { title: string }) {
    return <Text>{title}</Text>;
  };
});
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'ideation:challengeTitle': 'Challenge',
        'ideation:status.open': 'Open',
        'ideation:ideasCount': opts ? `${String(opts.count)} ideas` : 'ideas',
        'ideation:votesCount': opts ? `${String(opts.count)} votes` : 'votes',
        'ideation:submitIdea': 'Submit idea',
        'ideation:ideaTitleLabel': 'Idea title',
        'ideation:ideaTitlePlaceholder': 'Name your idea',
        'ideation:ideaDescriptionLabel': 'Idea description',
        'ideation:ideaDescriptionPlaceholder': 'Describe what should happen and why it helps',
        'ideation:submitting': 'Submitting...',
        'ideation:submitSuccess': 'Idea submitted.',
        'ideation:vote': 'Vote',
        'ideation:voted': 'Voted',
        'ideation:ideaStatus.submitted': 'Submitted',
        'ideation:sort.votes': 'Top voted',
        'ideation:sort.newest': 'Newest',
      };
      return map[key] ?? key;
    },
  }),
}));

import IdeationDetailScreen from './ideation-detail';

describe('IdeationDetailScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    const refreshChallenge = jest.fn();
    const refreshIdeas = jest.fn();
    let call = 0;
    mockUseApi.mockImplementation(() => {
      call += 1;
      if (call % 2 === 1) {
        return {
          data: {
            id: 12,
            title: 'Improve the park',
            description: '<p>Collect ideas for safer paths.</p>',
            status: 'open',
            ideas_count: 1,
          },
          isLoading: false,
          error: null,
          refresh: refreshChallenge,
        };
      }
      return {
        data: {
          items: [
            {
              id: 44,
              title: 'Better lighting',
              description: 'Add lights near the west gate.',
              status: 'submitted',
              votes_count: 4,
              has_voted: false,
            },
          ],
        },
        isLoading: false,
        error: null,
        refresh: refreshIdeas,
      };
    });
    mockSubmitIdea.mockResolvedValue({ id: 99 });
    mockVoteIdea.mockResolvedValue({ voted: true, votes_count: 5 });
  });

  it('renders challenge ideas, submits a new idea, and votes', async () => {
    const { getAllByText, getByPlaceholderText, getByText } = render(<IdeationDetailScreen />);

    expect(getAllByText('Improve the park').length).toBeGreaterThan(0);
    expect(getByText('Collect ideas for safer paths.')).toBeTruthy();
    expect(getByText('Better lighting')).toBeTruthy();

    fireEvent.changeText(getByPlaceholderText('Name your idea'), 'More benches');
    fireEvent.changeText(getByPlaceholderText('Describe what should happen and why it helps'), 'Add seating near the playground.');
    fireEvent.press(getAllByText('Submit idea')[1]);

    await waitFor(() => expect(mockSubmitIdea).toHaveBeenCalledWith(12, {
      title: 'More benches',
      description: 'Add seating near the playground.',
    }));

    fireEvent.press(getByText('Vote'));
    await waitFor(() => expect(mockVoteIdea).toHaveBeenCalledWith(44));
  });
});
