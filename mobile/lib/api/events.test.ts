// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), put: jest.fn(), patch: jest.fn(), delete: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) { super(message); this.status = status; this.name = 'ApiResponseError'; }
  },
  registerUnauthorizedCallback: jest.fn(),
}));
jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
  API_BASE_URL: 'https://test.api',
  STORAGE_KEYS: { AUTH_TOKEN: 'auth_token', REFRESH_TOKEN: 'refresh_token', TENANT_SLUG: 'tenant_slug', USER_DATA: 'user_data' },
  TIMEOUTS: { API_REQUEST: 15_000 },
  DEFAULT_TENANT: 'test-tenant',
}));

import { api } from '@/lib/api/client';
import {
  checkInEventAttendee,
  getEventAttendees,
  getEventPolls,
  getEventWaitlist,
  getEvents,
  joinEventWaitlist,
  leaveEventWaitlist,
  rsvpEvent,
  removeRsvp,
  getEvent,
  updateEvent,
  getEventReminders,
  updateEventReminders,
  voteEventPoll,
} from './events';
import type { EventsResponse, Event } from './events';

const mockEvent: Event = {
  id: 10,
  title: 'Community Cleanup',
  description: 'Let us clean up the park',
  location: 'Central Park',
  is_online: false,
  online_url: null,
  start_date: '2026-04-01T10:00:00Z',
  end_date: '2026-04-01T14:00:00Z',
  cover_image: null,
  max_attendees: 50,
  spots_left: 20,
  is_full: false,
  status: 'published',
  organizer: { id: 1, name: 'Jane Doe', avatar: null },
  category: { id: 2, name: 'Environment', color: '#00ff00' },
  rsvp_counts: { going: 30, interested: 5 },
  attendees_count: 30,
  user_rsvp: null,
};

const mockEventsResponse: EventsResponse = {
  data: [mockEvent],
  meta: { per_page: 20, has_more: false, cursor: null },
};

describe('getEvents', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls /api/v2/events with default when=upcoming and per_page=20', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    const result = await getEvents();
    expect(api.get).toHaveBeenCalledWith('/api/v2/events', { when: 'upcoming', per_page: '20' });
    expect(result.data).toHaveLength(1);
  });

  it('passes when=past when requested', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    await getEvents('past');
    expect(api.get).toHaveBeenCalledWith('/api/v2/events', { when: 'past', per_page: '20' });
  });

  it('includes cursor param when provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    await getEvents('upcoming', 'cursor-abc');
    expect(api.get).toHaveBeenCalledWith('/api/v2/events', {
      when: 'upcoming',
      per_page: '20',
      cursor: 'cursor-abc',
    });
  });

  it('respects custom perPage value', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    await getEvents('all', null, 10);
    expect(api.get).toHaveBeenCalledWith('/api/v2/events', { when: 'all', per_page: '10' });
  });

  it('includes a group filter when requested', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    await getEvents('upcoming', null, 20, { groupId: 90036 });
    expect(api.get).toHaveBeenCalledWith('/api/v2/events', {
      when: 'upcoming',
      per_page: '20',
      group_id: '90036',
    });
  });

  it('omits cursor when not provided', async () => {
    (api.get as jest.Mock).mockResolvedValue(mockEventsResponse);
    await getEvents('upcoming');
    const params = (api.get as jest.Mock).mock.calls[0][1] as Record<string, string>;
    expect(params).not.toHaveProperty('cursor');
  });

  it('propagates errors from the API', async () => {
    (api.get as jest.Mock).mockRejectedValue(new Error('Server error'));
    await expect(getEvents()).rejects.toThrow('Server error');
  });
});

describe('getEvent', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('calls the correct endpoint with the event ID', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: mockEvent });
    const result = await getEvent(10);
    expect(api.get).toHaveBeenCalledWith('/api/v2/events/10');
    expect(result.data.title).toBe('Community Cleanup');
  });
});

describe('updateEvent', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends PUT to the event endpoint with the update payload', async () => {
    (api.put as jest.Mock).mockResolvedValue({ data: mockEvent });
    const payload = {
      title: 'Updated cleanup',
      description: 'Updated details for attendees',
      start_time: '2026-04-02T10:00:00.000Z',
      end_time: null,
      location: 'Updated hall',
      category_name: 'workshop',
      is_online: false,
      online_link: null,
      max_attendees: 40,
      federated_visibility: 'none' as const,
    };

    const result = await updateEvent(10, payload);

    expect(api.put).toHaveBeenCalledWith('/api/v2/events/10', payload);
    expect(result.data.title).toBe('Community Cleanup');
  });
});

describe('rsvpEvent', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends POST with status=going to the correct endpoint', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: { rsvp: 'going', rsvp_counts: { going: 31, interested: 5 } },
    });
    const result = await rsvpEvent(10, 'going');
    expect(api.post).toHaveBeenCalledWith('/api/v2/events/10/rsvp', { status: 'going' });
    expect(result.data.rsvp).toBe('going');
    expect(result.data.rsvp_counts.going).toBe(31);
  });

  it('sends POST with status=not_going', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: { rsvp: 'not_going', rsvp_counts: { going: 29, interested: 5 } },
    });
    await rsvpEvent(10, 'not_going');
    expect(api.post).toHaveBeenCalledWith('/api/v2/events/10/rsvp', { status: 'not_going' });
  });
});

describe('removeRsvp', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('sends DELETE to the correct RSVP endpoint', async () => {
    (api.delete as jest.Mock).mockResolvedValue(undefined);
    await removeRsvp(10);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/events/10/rsvp');
  });

  it('propagates errors from the API', async () => {
    (api.delete as jest.Mock).mockRejectedValue(new Error('Not found'));
    await expect(removeRsvp(99)).rejects.toThrow('Not found');
  });
});

describe('event reminders', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('loads event reminders for the authenticated user', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [{ remind_before_minutes: 60, reminder_type: 'both', status: 'pending', scheduled_for: '2026-04-01T09:00:00Z' }] });

    const result = await getEventReminders(10);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/10/reminders');
    expect(result.data[0].remind_before_minutes).toBe(60);
  });

  it('replaces event reminders for the authenticated user', async () => {
    const reminders = [{ minutes: 60 as const, type: 'both' as const }, { minutes: 1440 as const, type: 'both' as const }];
    (api.put as jest.Mock).mockResolvedValue({ data: [] });

    await updateEventReminders(10, reminders);

    expect(api.put).toHaveBeenCalledWith('/api/v2/events/10/reminders', { reminders });
  });
});

describe('event attendees', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('loads event attendees with organizer-friendly defaults', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ id: 44, name: 'Mina Member', rsvp_status: 'going', checked_in: false }],
      meta: { per_page: 50, has_more: false, cursor: null },
    });

    const result = await getEventAttendees(10);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/10/attendees', {
      per_page: '50',
      status: 'all',
    });
    expect(result.data[0].name).toBe('Mina Member');
  });

  it('passes attendee filters through to the endpoint', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [], meta: { per_page: 25, has_more: false, cursor: null } });

    await getEventAttendees(10, { perPage: 25, status: 'going', cursor: 'cursor-1' });

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/10/attendees', {
      per_page: '25',
      status: 'going',
      cursor: 'cursor-1',
    });
  });

  it('checks in an attendee through the event check-in endpoint', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: { checked_in: true, attendee_id: 44, event_id: 10, hours_credited: 1.5 },
    });

    const result = await checkInEventAttendee(10, 44);

    expect(api.post).toHaveBeenCalledWith('/api/v2/events/10/attendees/44/check-in');
    expect(result.data.checked_in).toBe(true);
  });
});

describe('event waitlist', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('loads waitlist state for the authenticated user', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ id: 1, name: 'Waiting Member', position: 2 }],
      meta: { has_more: false, user_position: 2 },
    });

    const result = await getEventWaitlist(10);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/10/waitlist', { per_page: '20' });
    expect(result.meta.user_position).toBe(2);
  });

  it('joins an event waitlist', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { waitlisted: true, position: 3 } });

    const result = await joinEventWaitlist(10);

    expect(api.post).toHaveBeenCalledWith('/api/v2/events/10/waitlist');
    expect(result.data.position).toBe(3);
  });

  it('leaves an event waitlist', async () => {
    (api.delete as jest.Mock).mockResolvedValue(undefined);

    await leaveEventWaitlist(10);

    expect(api.delete).toHaveBeenCalledWith('/api/v2/events/10/waitlist');
  });
});

describe('event polls', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('loads polls linked to an event', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ id: 5, question: 'Which time works?', options: [] }],
      meta: { per_page: 50, has_more: false, cursor: null },
    });

    const result = await getEventPolls(10);

    expect(api.get).toHaveBeenCalledWith('/api/v2/polls', {
      event_id: '10',
      status: 'all',
      per_page: '50',
    });
    expect(result.data[0].question).toBe('Which time works?');
  });

  it('votes on an event-linked poll', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: { id: 5, question: 'Which time works?', total_votes: 1, options: [] },
    });

    const result = await voteEventPoll(5, 9);

    expect(api.post).toHaveBeenCalledWith('/api/v2/polls/5/vote', { option_id: 9 });
    expect(result.data.total_votes).toBe(1);
  });
});
