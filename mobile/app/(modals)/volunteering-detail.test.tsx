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
  useLocalSearchParams: () => ({ id: '10' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Opportunity Details',
        'detail.organisation': 'Organisation',
        'detail.about': 'About this opportunity',
        'detail.invalidId': 'Invalid opportunity ID.',
        'detail.notFound': 'Opportunity not found.',
        'detail.goBack': 'Go Back',
        'expressInterest': 'Express Interest',
        'interestSent': 'Interest Sent',
        'interestError': 'Failed to send interest.',
        'remote': 'Remote',
        'skills': 'Skills Needed',
        'status.open': 'Open',
        'status.filled': 'Filled',
        'status.closed': 'Closed',
        'deadline': opts ? `Deadline: ${String(opts.date ?? '')}` : 'Deadline',
        'hoursPerWeek': opts ? `${String(opts.hours ?? 0)} hrs/week` : '0 hrs/week',
        'spots': opts ? `${String(opts.count ?? 0)} spots available` : '0 spots available',
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
    success: '#22c55e',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/volunteering', () => ({
  getOpportunity: jest.fn(),
  expressInterest: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import VolunteeringDetailScreen from './volunteering-detail';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockOpportunity = {
  id: 10,
  title: 'Community Garden Volunteer',
  description: 'Help tend the community garden every Saturday morning.',
  status: 'open' as const,
  is_remote: false,
  location: 'Dublin, Ireland',
  hours_per_week: 3,
  commitment: 'Weekly',
  deadline: '2026-08-01T00:00:00Z',
  spots_available: 5,
  skills_needed: ['Gardening', 'Teamwork'],
  organisation: { id: 4, name: 'Green Spaces Dublin', avatar: null },
};

describe('VolunteeringDetailScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { toJSON } = render(<VolunteeringDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders a loading spinner when the API is loading', () => {
    mockUseApi.mockReturnValueOnce({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    const { toJSON } = render(<VolunteeringDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the opportunity title when loaded', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Community Garden Volunteer')).toBeTruthy();
  });

  it('renders the Open status badge', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Open')).toBeTruthy();
  });

  it('renders the organisation name', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Green Spaces Dublin')).toBeTruthy();
  });

  it('renders the description text', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Help tend the community garden every Saturday morning.')).toBeTruthy();
  });

  it('renders the Express Interest button', () => {
    mockUseApi.mockReturnValueOnce({ data: { data: mockOpportunity }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Express Interest')).toBeTruthy();
  });

  it('renders the not found state when data is null after loading', () => {
    mockUseApi.mockReturnValueOnce({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<VolunteeringDetailScreen />);
    expect(getByText('Opportunity not found.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });
});
