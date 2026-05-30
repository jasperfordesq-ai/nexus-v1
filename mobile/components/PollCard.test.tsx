// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

import type { PollData } from '@/lib/api/feed';
import PollCard from './PollCard';

const mockVoteFeedPoll = jest.fn();

jest.mock('@/lib/api/feed', () => ({
  voteFeedPoll: (...args: unknown[]) => mockVoteFeedPoll(...args),
}));

jest.mock('@/lib/haptics', () => ({
  ImpactFeedbackStyle: { Light: 'Light' },
  impactAsync: jest.fn(),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    surface: '#FFFFFF',
    border: '#E4E4E7',
    borderSubtle: '#F0F0F0',
    text: '#11181C',
    textSecondary: '#687076',
    onPrimary: '#FFFFFF',
  }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (key === 'poll.totalVotes') return `${String(opts?.count ?? 0)} votes`;
      if (key === 'poll.voted') return 'You voted';
      if (key === 'poll.closed') return 'Poll closed';
      return key;
    },
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Text, View } = require('react-native');

  const Chip = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;
  Chip.Label = ({ children }: { children?: React.ReactNode }) => <Text>{children}</Text>;

  return { Chip };
});

describe('PollCard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('lets members vote when the API omits user_vote_option_id', async () => {
    const updatedPoll: PollData = {
      id: 9,
      question: 'Which session should we run?',
      total_votes: 1,
      user_vote_option_id: 11,
      is_active: true,
      options: [
        { id: 11, text: 'Skill swap clinic', vote_count: 1, percentage: 100 },
        { id: 12, text: 'Repair cafe', vote_count: 0, percentage: 0 },
      ],
    };
    mockVoteFeedPoll.mockResolvedValue({ data: updatedPoll });
    const onVoted = jest.fn();

    const pollWithoutVoteFlag = {
      id: 9,
      question: 'Which session should we run?',
      total_votes: 0,
      is_active: true,
      options: [
        { id: 11, text: 'Skill swap clinic', vote_count: 0, percentage: 0 },
        { id: 12, text: 'Repair cafe', vote_count: 0, percentage: 0 },
      ],
    } as PollData;

    const { getByLabelText } = render(
      <PollCard pollData={pollWithoutVoteFlag} itemId={77} onVoted={onVoted} />,
    );

    fireEvent.press(getByLabelText('Skill swap clinic'));

    await waitFor(() => expect(mockVoteFeedPoll).toHaveBeenCalledWith(77, 11));
    await waitFor(() => expect(onVoted).toHaveBeenCalledWith(updatedPoll));
  });
});
