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
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'title': 'Volunteering',
        'subtitle': 'Find opportunities and track your hours.',
        'heroEyebrow': 'Community action',
        'searchPlaceholder': 'Search opportunities…',
        'clearSearch': 'Clear search',
        'empty': 'No opportunities found.',
        'stats.opportunities': 'Opportunities',
        'stats.applications': 'Applications',
        'stats.hours': 'Verified hours',
        'tabs.opportunities': 'Opportunities',
        'tabs.applications': 'My Applications',
        'tabs.hours': 'My Hours',
        'browseOrganisations': 'Browse organisations',
        'viewOpportunity': 'View',
        'apply': 'Apply',
        'applyError': 'Could not apply.',
        'signInRequiredTitle': 'Sign in required',
        'signInRequiredMessage': 'Sign in to apply.',
        'noDescription': 'No description.',
        'deadlineShort': opts ? String(opts.date ?? '') : 'Deadline',
        'tryAgain': 'Try again',
        'noApplications': 'No applications yet.',
        'withdraw': 'Withdraw',
        'withdrawError': 'Could not withdraw.',
        'appliedOn': opts ? `Applied ${String(opts.date ?? '')}` : 'Applied',
        'applicationStatus.pending': 'Pending',
        'applicationStatus.approved': 'Approved',
        'applicationStatus.declined': 'Declined',
        'logHours': 'Log hours',
        'logHoursHint': 'Log your hours.',
        'noLoggableOrganisations': 'No organisations.',
        'hoursPlaceholder': 'Hours',
        'hoursDescriptionPlaceholder': 'Description',
        'submitHours': 'Submit hours',
        'hoursRequired': 'Enter hours.',
        'hoursLogError': 'Could not log hours.',
        'byOrganisation': 'By organisation',
        'hoursValue': opts ? `${String(opts.count ?? 0)}h` : '0h',
        'hoursStats.verified': 'Verified',
        'hoursStats.pending': 'Pending',
        'hoursStats.declined': 'Declined',
        'remote': 'Remote',
        'status.open': 'Open',
        'status.filled': 'Filled',
        'status.closed': 'Closed',
        'deadline': opts ? `Deadline: ${String(opts.date ?? '')}` : 'Deadline',
        'hoursPerWeek': opts ? `${String(opts.hours ?? 0)} hrs/week` : '0 hrs/week',
        'common:actions.retry': 'Retry',
        'common:back': 'Back',
        'common:errors.alertTitle': 'Error',
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
    success: '#16a34a',
    warning: '#f59e0b',
  }),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ isAuthenticated: true }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

const mockUsePaginatedApi = jest.fn();
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (...args: unknown[]) => mockUsePaginatedApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/volunteering', () => ({
  getOpportunities: jest.fn(),
  getMyApplications: jest.fn(),
  getHoursSummary: jest.fn(),
  getMyOrganisations: jest.fn(),
  expressInterest: jest.fn().mockResolvedValue({}),
  withdrawApplication: jest.fn().mockResolvedValue({}),
  logVolunteerHours: jest.fn().mockResolvedValue({ data: {} }),
}));

jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/ui/Avatar', () => 'View');

// --- Tests ---

import VolunteeringScreen from './volunteering';

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
  mockUsePaginatedApi.mockReturnValue(defaultPaginatedState);
  mockUseApi.mockReturnValue({
    data: null,
    isLoading: false,
    error: null,
    refresh: jest.fn(),
  });
});

const mockOpportunity = {
  id: 10,
  title: 'Garden Helper',
  description: 'Help maintain the community garden.',
  status: 'open' as const,
  is_remote: false,
  location: 'Dublin',
  hours_per_week: 3,
  deadline: null,
  skills_needed: ['Gardening'],
  organisation: { id: 5, name: 'Green Spaces' },
};

describe('VolunteeringScreen', () => {
  it('renders the screen title via navigation options (no crash)', () => {
    const { toJSON } = render(<VolunteeringScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<VolunteeringScreen />);
    expect(getByPlaceholderText('Search opportunities…')).toBeTruthy();
  });

  it('renders empty state when no items and not loading', () => {
    const { getByText } = render(<VolunteeringScreen />);
    expect(getByText('No opportunities found.')).toBeTruthy();
  });

  it('renders opportunity cards when items are provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockOpportunity],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<VolunteeringScreen />);
    expect(getByText('Garden Helper')).toBeTruthy();
    expect(getByText('Green Spaces')).toBeTruthy();
  });

  it('does not render empty state when loading', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [],
      isLoading: true,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { queryByText } = render(<VolunteeringScreen />);
    expect(queryByText('No opportunities found.')).toBeNull();
  });

  it('renders status badge on opportunity card', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockOpportunity],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<VolunteeringScreen />);
    expect(getByText('Open')).toBeTruthy();
  });
});
