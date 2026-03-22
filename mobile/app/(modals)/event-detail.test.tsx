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
  useLocalSearchParams: () => ({ id: '7' }),
  useNavigation: () => ({ setOptions: jest.fn() }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'detail.title': 'Event Details',
        'detail.about': 'About this Event',
        'detail.organizer': 'Organizer',
        'detail.invalidId': 'Invalid event ID.',
        'detail.notFound': 'Event not found.',
        'detail.goBack': 'Go Back',
        'going': 'Going',
        'interested': 'Interested',
        'onlineEvent': 'Online Event',
        'onlineTapToJoin': 'Tap to Join',
        'full': 'Full',
        'rsvpError': 'Failed to update RSVP.',
        'common:errors.alertTitle': 'Error',
        'common:buttons.cancel': 'Cancel',
        'attendees': opts
          ? `${String(opts.going ?? 0)} going · ${String(opts.interested ?? 0)} interested`
          : '0 going · 0 interested',
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
    warning: '#f59e0b',
    info: '#3b82f6',
    errorBg: '#fee2e2',
    successBg: '#dcfce7',
    infoBg: '#dbeafe',
  }),
}));

const mockUseApi = jest.fn();
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: (...args: unknown[]) => mockUseApi(...args),
}));

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn().mockResolvedValue(undefined),
  ImpactFeedbackStyle: { Light: 'light', Medium: 'medium' },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/lib/api/events', () => ({
  getEvent: jest.fn(),
  rsvpEvent: jest.fn().mockResolvedValue({ data: { rsvp: 'going', rsvp_counts: { going: 1, interested: 0 } } }),
  removeRsvp: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import EventDetailScreen from './event-detail';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  mockUseApi.mockReturnValue(defaultApiState);
});

const mockEvent = {
  id: 7,
  title: 'Community Skill Share Workshop',
  description: 'Join us for an afternoon of skill sharing and community building.',
  start_date: '2026-05-15T14:00:00Z',
  is_online: false,
  online_url: null,
  location: 'Community Hall, Main Street',
  is_full: false,
  user_rsvp: null,
  rsvp_counts: { going: 12, interested: 5 },
  category: { name: 'Education', color: '#f59e0b' },
  organizer: { id: 3, name: 'Jane Organizer', avatar: null },
};

describe('EventDetailScreen', () => {
  it('renders without crashing when data is loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { toJSON } = render(<EventDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the loading state', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: true, error: null, refresh: jest.fn() });

    const { toJSON } = render(<EventDetailScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the event title when loaded', () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EventDetailScreen />);
    expect(getByText('Community Skill Share Workshop')).toBeTruthy();
  });

  it('renders the not found state', () => {
    mockUseApi.mockReturnValue({ data: null, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EventDetailScreen />);
    expect(getByText('Event not found.')).toBeTruthy();
    expect(getByText('Go Back')).toBeTruthy();
  });

  it('renders the RSVP button', () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EventDetailScreen />);
    expect(getByText('Going')).toBeTruthy();
  });
});
