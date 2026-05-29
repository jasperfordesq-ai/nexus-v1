// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockUseApi = jest.fn();
const mockPush = jest.fn();
const mockDismissMatch = jest.fn();

jest.mock('expo-router', () => ({
  router: { push: (...args: unknown[]) => mockPush(...args) },
}));

jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#6b7280',
    success: '#22c55e',
    warning: '#f59e0b',
    error: '#ef4444',
    onPrimary: '#ffffff',
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
        'matches.title': 'Matches',
        'matches.subtitle': 'Recommended opportunities across your timebank.',
        'matches.total': 'Total matches',
        'matches.average': 'Average score',
        'matches.hot': 'Hot matches',
        'matches.sources': 'Sources',
        'matches.filter.all': 'All',
        'matches.filter.listing': 'Listings',
        'matches.filter.job': 'Jobs',
        'matches.filter.volunteering': 'Volunteering',
        'matches.filter.group': 'Groups',
        'matches.source.listing': 'Listing',
        'matches.source.job': 'Job',
        'matches.source.volunteering': 'Volunteering',
        'matches.source.group': 'Group',
        'matches.score': opts ? `${String(opts.score)}% match` : '0% match',
        'matches.open': 'Open match',
        'matches.dismiss': 'Dismiss',
        'matches.emptyTitle': 'No matches yet',
        'matches.emptySubtitle': 'New recommendations will appear here when they are available.',
        'matches.errorTitle': 'Could not load matches',
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@/lib/api/matches', () => ({
  getMatches: jest.fn(),
  dismissMatch: (...args: unknown[]) => mockDismissMatch(...args),
}));

import MatchesScreen from './matches';

const listingMatch = {
  id: 1,
  source_type: 'listing',
  source_id: 10,
  match_score: 87,
  title: 'Garden help',
  description: 'Someone nearby needs help.',
  reasons: ['Shared skill', 'Nearby'],
  matched_at: '2026-05-29T10:00:00Z',
};

const jobMatch = {
  id: 2,
  source_type: 'job',
  source_id: 20,
  match_score: 62,
  title: 'Community organiser',
  description: null,
  reasons: ['Experience'],
  matched_at: '2026-05-29T11:00:00Z',
};

describe('MatchesScreen', () => {
  beforeEach(() => {
    mockPush.mockClear();
    mockDismissMatch.mockResolvedValue({});
    mockUseApi.mockReturnValue({
      data: { data: [listingMatch, jobMatch] },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });
  });

  it('renders match stats and cards', () => {
    const { getAllByText, getByText } = render(<MatchesScreen />);

    expect(getAllByText('Matches').length).toBeGreaterThan(0);
    expect(getByText('Total matches')).toBeTruthy();
    expect(getByText('Garden help')).toBeTruthy();
    expect(getByText('87% match')).toBeTruthy();
    expect(getByText('Shared skill')).toBeTruthy();
  });

  it('filters matches by source type', () => {
    const { getByText, queryByText } = render(<MatchesScreen />);

    fireEvent.press(getByText('Jobs'));

    expect(getByText('Community organiser')).toBeTruthy();
    expect(queryByText('Garden help')).toBeNull();
  });

  it('opens and dismisses listing matches', async () => {
    const { getAllByText, getByText } = render(<MatchesScreen />);

    fireEvent.press(getAllByText('Open match')[0]);
    expect(mockPush).toHaveBeenCalledWith({ pathname: '/(modals)/exchange-detail', params: { id: '10' } });

    fireEvent.press(getByText('Dismiss'));
    await waitFor(() => expect(mockDismissMatch).toHaveBeenCalledWith(10));
  });
});
