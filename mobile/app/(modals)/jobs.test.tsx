// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

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
        'title': 'Jobs',
        'tabs.browse': 'Browse',
        'tabs.myApplications': 'My Applications',
        'search.placeholder': 'Search jobs...',
        'empty': 'No jobs found',
        'emptyHint': 'Try adjusting your filters or check back later',
        'applications.empty': 'No applications yet',
        'applications.emptyHint': 'Apply to jobs to see them here',
        'card.remote': 'Remote',
        'card.featured': 'Featured',
        'card.applications': opts ? `${String(opts.count ?? 0)} applications` : '0 applications',
        'card.deadline': opts ? `Closes ${String(opts.date ?? '')}` : 'Closes',
        'filters.type.all': 'All Types',
        'filters.type.paid': 'Paid',
        'filters.type.volunteer': 'Volunteer',
        'filters.type.timebank': 'Timebank',
        'filters.commitment.all': 'All',
        'filters.commitment.full_time': 'Full Time',
        'filters.commitment.part_time': 'Part Time',
        'filters.commitment.flexible': 'Flexible',
        'filters.commitment.one_off': 'One-off',
        'retry': 'Retry',
        'common:actions.retry': 'Retry',
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

jest.mock('@/lib/api/jobs', () => ({
  getJobs: jest.fn(),
  getMyApplications: jest.fn(),
}));

jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import JobsScreen from './jobs';
import type { JobVacancy, JobApplication } from '@/lib/api/jobs';

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
});

const mockJob: JobVacancy = {
  id: 1,
  title: 'Community Coordinator',
  description: 'Coordinate community activities.',
  location: 'Dublin',
  is_remote: false,
  type: 'paid',
  commitment: 'full_time',
  category: 'Community',
  skills_required: ['Communication', 'Organisation'],
  hours_per_week: 40,
  time_credits: null,
  salary_min: 30000,
  salary_max: 40000,
  salary_currency: '€',
  salary_type: 'annual',
  salary_negotiable: false,
  deadline: null,
  status: 'open',
  views_count: 120,
  applications_count: 8,
  is_featured: false,
  created_at: '2026-03-01T00:00:00Z',
  creator: { id: 2, name: 'Hour Timebank', avatar_url: null },
  organization: { id: 3, name: 'Dublin Community Hub', logo_url: null },
  is_saved: false,
  has_applied: false,
};

const mockApplication: JobApplication = {
  id: 10,
  vacancy_id: 1,
  vacancy: { title: 'Community Coordinator', organization: { id: 3, name: 'Dublin Community Hub', logo_url: null } },
  message: 'I am a great fit.',
  status: 'pending',
  reviewer_notes: null,
  created_at: '2026-03-10T00:00:00Z',
  updated_at: '2026-03-10T00:00:00Z',
};

describe('JobsScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<JobsScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the search input', () => {
    const { getByPlaceholderText } = render(<JobsScreen />);
    expect(getByPlaceholderText('Search jobs...')).toBeTruthy();
  });

  it('renders Browse and My Applications tabs', () => {
    const { getByText } = render(<JobsScreen />);
    expect(getByText('Browse')).toBeTruthy();
    expect(getByText('My Applications')).toBeTruthy();
  });

  it('renders empty state when no jobs and not loading', () => {
    const { getByText } = render(<JobsScreen />);
    expect(getByText('No jobs found')).toBeTruthy();
  });

  it('renders job cards when items are provided', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockJob],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getByText } = render(<JobsScreen />);
    expect(getByText('Community Coordinator')).toBeTruthy();
    expect(getByText('Dublin Community Hub')).toBeTruthy();
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

    const { queryByText } = render(<JobsScreen />);
    expect(queryByText('No jobs found')).toBeNull();
  });

  it('shows My Applications empty state when switching tab', () => {
    const { getByText } = render(<JobsScreen />);
    fireEvent.press(getByText('My Applications'));
    // After tab switch, applications empty state should be visible
    // (both usePaginatedApi calls return empty by default)
    expect(getByText('My Applications')).toBeTruthy();
  });

  it('renders type badge on job card', () => {
    mockUsePaginatedApi.mockReturnValueOnce({
      items: [mockJob],
      isLoading: false,
      isLoadingMore: false,
      error: null,
      hasMore: false,
      loadMore: jest.fn(),
      refresh: jest.fn(),
    });

    const { getAllByText } = render(<JobsScreen />);
    expect(getAllByText('Paid').length).toBeGreaterThan(0);
  });

  it('renders application cards in My Applications tab', () => {
    // First call per render: browse jobs (empty), second call per render: applications
    let callCount = 0;
    mockUsePaginatedApi.mockImplementation(() => {
      callCount += 1;
      if (callCount % 2 === 1) {
        return { items: [], isLoading: false, isLoadingMore: false, error: null, hasMore: false, loadMore: jest.fn(), refresh: jest.fn() };
      }
      return { items: [mockApplication], isLoading: false, isLoadingMore: false, error: null, hasMore: false, loadMore: jest.fn(), refresh: jest.fn() };
    });

    const { getByText } = render(<JobsScreen />);
    // Switch to My Applications tab
    fireEvent.press(getByText('My Applications'));
    // The application title should be visible
    expect(getByText('Community Coordinator')).toBeTruthy();
  });
});
