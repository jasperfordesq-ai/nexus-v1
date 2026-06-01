// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import { Linking, ScrollView, StyleSheet } from 'react-native';

// --- Mocks ---

const mockRouterPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockRouterPush, replace: jest.fn(), back: jest.fn() }),
  router: { push: (...args: unknown[]) => mockRouterPush(...args), replace: jest.fn(), back: jest.fn() },
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
        'detail.ownerTools': 'Event tools',
        'detail.edit': 'Edit',
        'detail.organizerAttendance': 'Organizer attendance',
        'detail.loadingAttendees': 'Loading attendees',
        'detail.attendeesLoadError': 'Could not load attendees.',
        'detail.noAttendees': 'No attendees yet.',
        'detail.moreAttendees': opts ? `+${String(opts.count ?? 0)} more` : '+0 more',
        'detail.waitlistCount': opts ? `${String(opts.count ?? 0)} on the waitlist` : '0 on the waitlist',
        'detail.onWaitlistPosition': opts ? `You are on the waitlist: #${String(opts.position ?? '')}` : 'You are on the waitlist',
        'detail.checkInProgress': opts
          ? `${String(opts.checked ?? 0)} / ${String(opts.total ?? 0)} checked in`
          : '0 / 0 checked in',
        'detail.attendanceLoadError': 'Could not load attendance.',
        'detail.noCheckInAttendeesTitle': 'No check-ins yet',
        'detail.noCheckInAttendees': 'No attendees to check in yet.',
        'detail.attendeeGoing': 'Going',
        'detail.attendeeInterested': 'Interested',
        'detail.attendeeCheckedIn': 'Checked in',
        'detail.checkIn': 'Check in',
        'detail.checkingIn': 'Checking in',
        'detail.checkInError': 'Could not check in attendee.',
        'detail.checkInAttendeeLabel': opts ? `Check in ${String(opts.name ?? '')}` : 'Check in attendee',
        'detail.joinWaitlist': 'Join waitlist',
        'detail.leaveWaitlist': 'Leave waitlist',
        'detail.joinWaitlistError': 'Could not join the waitlist.',
        'detail.leaveWaitlistError': 'Could not leave the waitlist.',
        'detail.eventPolls': 'Event polls',
        'detail.loadingPolls': 'Loading polls',
        'detail.pollsLoadError': 'Could not load event polls.',
        'detail.pollVoteError': 'Could not submit your vote.',
        'detail.pollOptionFallback': 'Poll option',
        'detail.pollOptionResult': opts ? `${String(opts.label ?? '')} - ${String(opts.percent ?? 0)}%` : 'Poll option - 0%',
        'detail.pollTotalVotes': opts ? `${String(opts.count ?? 0)} votes` : '0 votes',
        'detail.communityMember': 'Community member',
        'detail.invalidId': 'Invalid event ID.',
        'detail.notFound': 'Event not found.',
        'detail.goBack': 'Go Back',
        'going': 'Going',
        'interested': 'Interested',
        'onlineEvent': 'Online Event',
        'onlineTapToJoin': 'Tap to Join',
        'detail.joinOnline': 'Join online',
        'reminders.title': 'Reminders',
        'reminders.subtitle': 'Choose when Project NEXUS should remind you about this event.',
        'reminders.option.60': '1 hour before',
        'reminders.option.1440': '1 day before',
        'reminders.option.10080': '1 week before',
        'reminders.saved': 'Reminders updated.',
        'reminders.error': 'Could not update event reminders.',
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

jest.mock('react-native-safe-area-context', () => {
  const React = require('react');
  const { View } = require('react-native');
  return {
    SafeAreaView: ({ children, style }: { children: React.ReactNode; style?: unknown }) => (
      <View style={style}>{children}</View>
    ),
    useSafeAreaInsets: () => ({ top: 0, right: 0, bottom: 24, left: 0 }),
  };
});

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: { id: 3, name: 'Current User' } }),
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
  getEventOnlineLink: (event: { online_link?: string | null; online_url?: string | null; video_url?: string | null }) =>
    event.online_link ?? event.online_url ?? event.video_url ?? null,
  rsvpEvent: jest.fn().mockResolvedValue({ data: { rsvp: 'going', rsvp_counts: { going: 1, interested: 0 } } }),
  removeRsvp: jest.fn().mockResolvedValue(undefined),
  getEventAttendees: jest.fn().mockResolvedValue({ data: [], meta: { per_page: 50, has_more: false, cursor: null } }),
  checkInEventAttendee: jest.fn().mockResolvedValue({ data: { checked_in: true, attendee_id: 22, event_id: 7 } }),
  getEventWaitlist: jest.fn().mockResolvedValue({ data: [], meta: { has_more: false, user_position: null } }),
  joinEventWaitlist: jest.fn().mockResolvedValue({ data: { waitlisted: true, position: 2 } }),
  leaveEventWaitlist: jest.fn().mockResolvedValue(undefined),
  getEventPolls: jest.fn().mockResolvedValue({ data: [], meta: { per_page: 50, has_more: false, cursor: null } }),
  voteEventPoll: jest.fn().mockResolvedValue({ data: { id: 91, question: 'Which topic?', total_votes: 1, has_voted: true, voted_option_id: 12, options: [{ id: 12, text: 'Gardening', percentage: 100 }] } }),
  getEventReminders: jest.fn().mockResolvedValue({ data: [] }),
  updateEventReminders: jest.fn().mockResolvedValue({ data: [{ remind_before_minutes: 60, reminder_type: 'both', status: 'pending', scheduled_for: '2026-05-15T13:00:00Z' }] }),
}));

jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

// --- Tests ---

import EventDetailScreen from './event-detail';
import { checkInEventAttendee, joinEventWaitlist, leaveEventWaitlist, rsvpEvent, updateEventReminders, voteEventPoll } from '@/lib/api/events';

const defaultApiState = { data: null, isLoading: false, error: null, refresh: jest.fn() };

beforeEach(() => {
  jest.clearAllMocks();
  mockUseApi.mockReturnValue(defaultApiState);
  mockRouterPush.mockClear();
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

  it('keeps the RSVP footer above the device safe area and reserves scroll space', () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByTestId, UNSAFE_getAllByType } = render(<EventDetailScreen />);
    const mainScroll = UNSAFE_getAllByType(ScrollView).find((node) => node.props.contentContainerStyle?.paddingHorizontal === 16);

    expect(StyleSheet.flatten(getByTestId('event-rsvp-footer').props.style)).toEqual(
      expect.objectContaining({ paddingBottom: 36 }),
    );
    expect(mainScroll?.props.contentContainerStyle).toEqual(expect.objectContaining({ paddingBottom: expect.any(Number) }));
    expect(mainScroll?.props.contentContainerStyle.paddingBottom).toBeGreaterThanOrEqual(124);
  });

  it('submits an RSVP and updates the visible attendee count', async () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EventDetailScreen />);

    fireEvent.press(getByText('Going'));

    await waitFor(() => {
      expect(rsvpEvent).toHaveBeenCalledWith(7, 'going');
      expect(getByText(/1 going.*0 interested/)).toBeTruthy();
    });
  });

  it('opens the edit event route for the organizer', () => {
    mockUseApi.mockReturnValue({ data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() });

    const { getByText } = render(<EventDetailScreen />);

    fireEvent.press(getByText('Edit'));

    expect(mockRouterPush).toHaveBeenCalledWith({ pathname: '/(modals)/edit-event', params: { id: '7' } });
  });

  it('renders organizer attendance and checks in an attendee', async () => {
    let hookIndex = 0;
    const eventState = { data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() };
    const remindersState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const attendeesState = {
        data: {
          data: [
            { id: 22, name: 'Mina Member', avatar: null, rsvp_status: 'going', checked_in: false },
            { id: 23, name: 'Iris Interested', avatar: null, rsvp_status: 'interested', checked_in: false },
          ],
          meta: { per_page: 50, has_more: false, cursor: null },
        },
        isLoading: false,
        error: null,
        refresh: jest.fn(),
      };
    mockUseApi.mockImplementation(() => {
      const state = hookIndex % 5 === 0 ? eventState : hookIndex % 5 === 1 ? remindersState : hookIndex % 5 === 2 ? attendeesState : hookIndex % 5 === 3 ? { data: { data: [], meta: { user_position: null } }, isLoading: false, error: null, refresh: jest.fn() } : { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
      hookIndex += 1;
      return state;
    });

    const { getAllByText, getByLabelText, getByText } = render(<EventDetailScreen />);

    expect(getByText('Organizer attendance')).toBeTruthy();
    expect(getAllByText('Mina Member').length).toBeGreaterThan(0);
    expect(getByText('0 / 1 checked in')).toBeTruthy();

    fireEvent.press(getByLabelText('Check in Mina Member'));

    await waitFor(() => {
      expect(checkInEventAttendee).toHaveBeenCalledWith(7, 22);
      expect(getByText('1 / 1 checked in')).toBeTruthy();
    });
  });

  it('uses backend online_link when opening an online event', async () => {
    const openUrlSpy = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    mockUseApi.mockReturnValue({
      data: {
        data: {
          ...mockEvent,
          is_online: true,
          online_url: null,
          online_link: 'https://meet.example/live',
          location: null,
        },
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    });

    const { getByText } = render(<EventDetailScreen />);

    expect(getByText('Tap to Join')).toBeTruthy();
    fireEvent.press(getByText('Join online'));

    await waitFor(() => {
      expect(openUrlSpy).toHaveBeenCalledWith('https://meet.example/live');
    });
    openUrlSpy.mockRestore();
  });

  it('joins and leaves the event waitlist when the event is full', async () => {
    let hookIndex = 0;
    const fullEvent = { ...mockEvent, is_full: true, waitlist_count: 1 };
    const eventState = { data: { data: fullEvent }, isLoading: false, error: null, refresh: jest.fn() };
    const remindersState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const attendeesState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const waitlistState = { data: { data: [], meta: { has_more: false, user_position: null } }, isLoading: false, error: null, refresh: jest.fn() };
    mockUseApi.mockImplementation(() => {
      const state = hookIndex % 5 === 0 ? eventState : hookIndex % 5 === 1 ? remindersState : hookIndex % 5 === 2 ? attendeesState : hookIndex % 5 === 3 ? waitlistState : { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
      hookIndex += 1;
      return state;
    });

    const { getByText } = render(<EventDetailScreen />);

    expect(getByText('1 on the waitlist')).toBeTruthy();
    fireEvent.press(getByText('Join waitlist'));

    await waitFor(() => {
      expect(joinEventWaitlist).toHaveBeenCalledWith(7);
      expect(getByText('You are on the waitlist: #2')).toBeTruthy();
    });

    fireEvent.press(getByText('Leave waitlist'));

    await waitFor(() => {
      expect(leaveEventWaitlist).toHaveBeenCalledWith(7);
    });
  });

  it('renders and votes on event-linked polls', async () => {
    let hookIndex = 0;
    const eventState = { data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() };
    const remindersState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const attendeesState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    const waitlistState = { data: { data: [], meta: { has_more: false, user_position: null } }, isLoading: false, error: null, refresh: jest.fn() };
    const pollsState = {
      data: {
        data: [{
          id: 91,
          question: 'Which topic should we cover?',
          total_votes: 0,
          has_voted: false,
          options: [
            { id: 12, text: 'Gardening', percentage: 0 },
            { id: 13, text: 'Cooking', percentage: 0 },
          ],
        }],
      },
      isLoading: false,
      error: null,
      refresh: jest.fn(),
    };
    mockUseApi.mockImplementation(() => {
      const state = hookIndex % 5 === 0 ? eventState : hookIndex % 5 === 1 ? remindersState : hookIndex % 5 === 2 ? attendeesState : hookIndex % 5 === 3 ? waitlistState : pollsState;
      hookIndex += 1;
      return state;
    });

    const { getByText } = render(<EventDetailScreen />);

    expect(getByText('Event polls')).toBeTruthy();
    fireEvent.press(getByText('Gardening'));

    await waitFor(() => {
      expect(voteEventPoll).toHaveBeenCalledWith(91, 12);
      expect(getByText('Gardening - 100%')).toBeTruthy();
    });
  });

  it('renders and updates per-event reminders', async () => {
    let hookIndex = 0;
    const eventState = { data: { data: mockEvent }, isLoading: false, error: null, refresh: jest.fn() };
    const remindersState = {
        data: { data: [{ remind_before_minutes: 1440, reminder_type: 'both', status: 'pending', scheduled_for: '2026-05-14T14:00:00Z' }] },
        isLoading: false,
        error: null,
        refresh: jest.fn(),
      };
    const attendeesState = { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
    mockUseApi.mockImplementation(() => {
      const state = hookIndex % 5 === 0 ? eventState : hookIndex % 5 === 1 ? remindersState : hookIndex % 5 === 2 ? attendeesState : hookIndex % 5 === 3 ? { data: { data: [], meta: { user_position: null } }, isLoading: false, error: null, refresh: jest.fn() } : { data: { data: [] }, isLoading: false, error: null, refresh: jest.fn() };
      hookIndex += 1;
      return state;
    });

    const { getByText } = render(<EventDetailScreen />);

    expect(getByText('Reminders')).toBeTruthy();
    fireEvent.press(getByText('1 hour before'));

    await waitFor(() => {
      expect(updateEventReminders).toHaveBeenCalledWith(7, [
        { minutes: 1440, type: 'both' },
        { minutes: 60, type: 'both' },
      ]);
    });
  });
});

