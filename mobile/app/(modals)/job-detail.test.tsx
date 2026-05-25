// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '1' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'title': 'Jobs',
        'detail.invalidId': 'Invalid job ID.',
        'detail.notFound': 'Job not found.',
        'detail.goBack': 'Go back',
        'detail.share': 'Share',
        'detail.save': 'Save',
        'detail.saved': 'Saved',
        'detail.apply': 'Apply Now',
        'detail.applied': 'Applied',
        'detail.closedBadge': 'Closed',
        'detail.closesToday': 'Closes today',
        'detail.skills': 'Skills Required',
        'detail.description': 'Description',
        'detail.about': 'About',
        'detail.matchPercentage': opts ? `${String(opts.percentage ?? 0)}% match` : '0% match',
        'detail.closesIn': opts ? `Closes in ${String(opts.count ?? 0)} days` : 'Closes in 0 days',
        'detail.timeCredits': opts ? `${String(opts.count ?? 0)} time credits` : '0 time credits',
        'detail.salaryAnnual': 'year',
        'detail.salaryMonthly': 'month',
        'detail.salaryHourly': 'hour',
        'detail.saveError': 'Could not update saved state.',
        'apply.title': opts ? `Apply: ${String(opts.jobTitle ?? '')}` : 'Apply',
        'apply.success': 'Application Sent!',
        'apply.successMessage': 'The employer will be in touch.',
        'apply.messageLabel': 'Cover Message',
        'apply.messagePlaceholder': 'Why are you a great fit?',
        'apply.submit': 'Submit Application',
        'apply.error': 'Application failed.',
        'card.applications': opts ? `${String(opts.count ?? 0)} applications` : '0 applications',
        'card.remote': 'Remote',
        'filters.type.paid': 'Paid',
        'filters.type.volunteer': 'Volunteer',
        'filters.type.timebank': 'Timebank',
        'filters.commitment.full_time': 'Full Time',
        'filters.commitment.part_time': 'Part Time',
        'filters.commitment.flexible': 'Flexible',
        'filters.commitment.one_off': 'One-off',
        'saved_profile.use': 'Use Saved Cover Letter',
        'common:errors.alertTitle': 'Error',
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
    errorBg: '#fff5f5',
    success: '#22c55e',
    warning: '#f59e0b',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('@/lib/api/jobs', () => ({
  getJobDetail: jest.fn(),
  applyToJob: jest.fn().mockResolvedValue(undefined),
  saveJob: jest.fn().mockResolvedValue(undefined),
  unsaveJob: jest.fn().mockResolvedValue(undefined),
  getSavedProfile: jest.fn().mockResolvedValue(null),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import JobDetailScreen from './job-detail';

const mockJob = {
  id: 1,
  title: 'Community Coordinator',
  description: 'Lead and coordinate community activities across the region.',
  location: 'Dublin',
  is_remote: false,
  type: 'paid' as const,
  commitment: 'full_time' as const,
  category: 'Community',
  skills_required: ['Communication', 'Leadership'],
  hours_per_week: 40,
  time_credits: null,
  salary_min: 30000,
  salary_max: 40000,
  salary_currency: '€',
  salary_type: 'annual' as const,
  salary_negotiable: false,
  deadline: null,
  status: 'open' as const,
  views_count: 50,
  applications_count: 3,
  is_featured: false,
  created_at: '2026-03-01T00:00:00Z',
  creator: { id: 2, name: 'Hour Timebank', avatar_url: null },
  organization: { id: 3, name: 'Dublin Community Hub', logo_url: null },
  is_saved: false,
  has_applied: false,
  match_percentage: null,
};

beforeEach(() => {
  mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });
});

describe('JobDetailScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockJob },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { toJSON } = render(<JobDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the job title', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockJob },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<JobDetailScreen />);
    expect(getByText('Community Coordinator')).toBeTruthy();
  });

  it('renders the organisation name', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockJob },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<JobDetailScreen />);
    expect(getByText('Dublin Community Hub')).toBeTruthy();
  });

  it('renders the Apply Now button for open jobs', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockJob },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<JobDetailScreen />);
    expect(getByText('Apply Now')).toBeTruthy();
  });

  it('renders the job description', () => {
    mockUseApi.mockReturnValue({
      data: { data: mockJob },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<JobDetailScreen />);
    expect(getByText('Lead and coordinate community activities across the region.')).toBeTruthy();
  });

  it('renders loading state without crashing', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    expect(() => render(<JobDetailScreen />)).not.toThrow();
  });

  it('renders not found state when data is null after loading', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<JobDetailScreen />);
    expect(getByText('Job not found.')).toBeTruthy();
    expect(getByText('Go back')).toBeTruthy();
  });
});
