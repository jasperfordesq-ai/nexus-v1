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
import { getEvents, rsvpEvent, removeRsvp, getEvent } from './events';
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
