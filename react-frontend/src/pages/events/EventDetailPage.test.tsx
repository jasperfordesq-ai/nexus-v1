// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getFormattingLocale } from '@/lib/helpers';
import { createCanonicalEventFixture, renderEventRoute } from '@/test/events-test-harness';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
  },
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi }));

vi.mock('@/contexts/AuthContext', () => ({
  useAuth: () => ({
    user: { id: 99, first_name: 'Alice', last_name: 'Test', name: 'Alice Test' },
    isAuthenticated: true,
  }),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/location/LocationMapCard', () => ({
  LocationMapCard: () => <div aria-label="Event location map" />,
}));
vi.mock('@/components/i18n/TranslateButton', () => ({
  TranslateButton: () => null,
}));
vi.mock('@/components/social/SocialInteractionPanel', () => ({
  SocialInteractionPanel: () => null,
}));
vi.mock('./components/EventCheckinCredentialCard', () => ({
  EventCheckinCredentialCard: ({ eventId }: { eventId: number }) => (
    <section aria-label="Attendee check-in credential">credential-{eventId}</section>
  ),
}));
vi.mock('./components/EventRegistrationAttendeeCard', () => ({
  default: ({ eventId }: { eventId: number }) => (
    <section aria-label="Attendee registration workspace">registration-{eventId}</section>
  ),
}));

import { EventDetailPage } from './EventDetailPage';

const mockEvent = createCanonicalEventFixture();

function installSuccessfulEventResponses() {
  mockApi.get.mockImplementation((url: string) => {
    if (url.includes('/attendees')) {
      return Promise.resolve({ success: true, data: [] });
    }
    if (url.startsWith('/v2/polls?') || url.includes('series_id=')) {
      return Promise.resolve({ success: true, data: [] });
    }
    return Promise.resolve({ success: true, data: mockEvent });
  });
}

async function renderLoadedEvent(includeEventsIndex = false) {
  const result = renderEventRoute(<EventDetailPage />, {
    route: '/test/events/1',
    path: '/:tenantSlug/events/:id',
    routes: includeEventsIndex
      ? [
          { path: '/:tenantSlug/events/:id', element: <EventDetailPage /> },
          { path: '/:tenantSlug/events', element: <div>Events index</div> },
        ]
      : undefined,
  });

  await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });
  await waitFor(() => {
    expect(mockApi.get).toHaveBeenCalledWith(expect.stringContaining('/v2/polls?event_id=1'));
  });

  return result;
}

describe('EventDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    installSuccessfulEventResponses();
  });

  it('shows an aria-busy loading region while the event request is pending', async () => {
    let resolveEvent!: (value: { success: boolean; data: typeof mockEvent }) => void;
    const eventRequest = new Promise<{ success: boolean; data: typeof mockEvent }>((resolve) => {
      resolveEvent = resolve;
    });

    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees')) return Promise.resolve({ success: true, data: [] });
      if (url.startsWith('/v2/polls?')) return Promise.resolve({ success: true, data: [] });
      return eventRequest;
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });

    expect(screen.getByRole('status')).toHaveAttribute('aria-busy', 'true');

    await act(async () => {
      resolveEvent({ success: true, data: mockEvent });
    });
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });
  });

  it('renders the event title after loading', async () => {
    await renderLoadedEvent();

    expect(screen.getByRole('heading', { level: 1, name: 'Community Garden Day' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Attendee registration workspace' }))
      .toHaveTextContent('registration-1');
  });

  it('loads the policy-filtered agenda only when its detail tab is opened', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1/agenda?include_cancelled=false') {
        return Promise.resolve({
          success: true,
          data: {
            contract_version: 1,
            event_id: 1,
            agenda_version: 1,
            timezone: 'UTC',
            permissions: { manage: false },
            sessions: [{
              id: 501,
              version: 1,
              title: 'Community welcome',
              description: null,
              type: 'session',
              visibility: 'public',
              capacity: { limit: null, registered: 0, remaining: null, is_full: false },
              registration: {
                state: 'not_registered',
                version: 0,
                can_register: false,
                can_withdraw: false,
              },
              status: 'scheduled',
              start_at: '2030-06-01T10:00:00Z',
              end_at: '2030-06-01T10:30:00Z',
              timezone: 'UTC',
              track: null,
              room: null,
              position: 1,
              cancellation_reason: null,
              speakers: [],
              resources: [],
            }],
          },
        });
      }
      if (url.includes('/attendees') || url.startsWith('/v2/polls?') || url.includes('series_id=')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: mockEvent });
    });
    const user = userEvent.setup();
    await renderLoadedEvent();

    expect(mockApi.get).not.toHaveBeenCalledWith(
      '/v2/events/1/agenda?include_cancelled=false',
      expect.anything(),
    );
    await user.click(screen.getByRole('tab', { name: 'Agenda' }));

    expect(await screen.findByRole('heading', { name: 'Community welcome' })).toBeInTheDocument();
    expect(screen.getByText(/View the event running order/)).toBeInTheDocument();
  });

  it('deep-links confirmed attendees to the free-ticket catalogue', async () => {
    const confirmedEvent = createCanonicalEventFixture();
    confirmedEvent.relationship = {
      ...confirmedEvent.relationship,
      registration: {
        ...confirmedEvent.relationship.registration,
        state: 'confirmed',
        can_register: false,
        can_withdraw: true,
      },
    };
    mockApi.get.mockImplementation((url: string) => {
      if (url === '/v2/events/1/tickets') {
        return Promise.resolve({
          success: true,
          data: {
            contract_version: 1,
            event_id: 1,
            currency: 'time_credit',
            payment_gateway: { free_supported: true, time_credit_supported: false, money_supported: false },
            permissions: { manage: false, reconcile: false, allocate_self: true },
            ticket_types: [],
            own_entitlements: [],
          },
        });
      }
      if (url.includes('/attendees') || url.startsWith('/v2/polls?') || url.includes('series_id=')) {
        return Promise.resolve({ success: true, data: [] });
      }
      if (url.endsWith('/calendar-actions') || url.endsWith('/safety') || url.endsWith('/reminders')) {
        return Promise.resolve({ success: false });
      }
      return Promise.resolve({ success: true, data: confirmedEvent });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1?tab=tickets',
      path: '/:tenantSlug/events/:id',
    });

    expect(await screen.findByRole('tab', { name: 'Tickets' }))
      .toHaveAttribute('aria-selected', 'true');
    expect(await screen.findByRole('heading', { name: 'Tickets' })).toBeInTheDocument();
    expect(screen.getByText('No ticket types have been configured for this event.')).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith(
      '/v2/events/1/tickets',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
    expect(screen.getByRole('region', { name: 'Attendee check-in credential' }))
      .toHaveTextContent('credential-1');
  });

  it('does not expose an attendee check-in credential before registration is confirmed', async () => {
    await renderLoadedEvent();

    expect(screen.queryByRole('region', { name: 'Attendee check-in credential' }))
      .not.toBeInTheDocument();
  });

  it('shows calendar actions only after a successful fetch and translates download failure', async () => {
    let resolveCalendarActions!: (value: {
      success: boolean;
      data: { google_url: string; outlook_url: string; download_path: string };
    }) => void;
    const calendarActionsRequest = new Promise<{
      success: boolean;
      data: { google_url: string; outlook_url: string; download_path: string };
    }>((resolve) => {
      resolveCalendarActions = resolve;
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.endsWith('/calendar-actions')) return calendarActionsRequest;
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: mockEvent });
    });
    mockApi.download.mockRejectedValueOnce(new Error('raw download transport detail'));

    const user = userEvent.setup();
    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });
    expect(screen.queryByRole('heading', { name: 'Add to your calendar' })).not.toBeInTheDocument();

    await act(async () => resolveCalendarActions({
      success: true,
      data: {
        google_url: 'https://calendar.google.com/calendar/r/eventedit?action=TEMPLATE',
        outlook_url: 'https://outlook.office.com/calendar/deeplink/compose/',
        download_path: '/v2/events/1/calendar.ics',
      },
    }));
    expect(await screen.findByRole('heading', { name: 'Add to your calendar' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Download .ics' }));
    await waitFor(() => expect(mockApi.download).toHaveBeenCalledWith(
      '/v2/events/1/calendar.ics',
      expect.objectContaining({ filename: 'event-1.ics' }),
    ));
    expect(mockToast.error).toHaveBeenCalledWith('The event calendar file could not be downloaded.');
    expect(mockToast.error).not.toHaveBeenCalledWith('raw download transport detail');
  });

  it('renders the event description', async () => {
    await renderLoadedEvent();

    expect(screen.getByText('Join us for a community garden event')).toBeInTheDocument();
  });

  it('renders the event location', async () => {
    await renderLoadedEvent();

    expect(screen.getAllByText('Dublin Park').length).toBeGreaterThan(0);
  });

  it('renders the event-local day and time instead of the browser timezone', async () => {
    const zonedEvent = createCanonicalEventFixture({
      schedule: {
        ...mockEvent.schedule,
        start_at: '2026-07-11T00:30:00+00:00',
        end_at: '2026-07-11T01:30:00+00:00',
        timezone: 'America/Los_Angeles',
        all_day: false,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: zonedEvent });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });

    const locale = getFormattingLocale();
    const start = new Date('2026-07-11T00:30:00+00:00');
    const expectedDate = new Intl.DateTimeFormat(locale, {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      timeZone: 'America/Los_Angeles',
    }).format(start);
    const expectedTime = start.toLocaleString(locale, {
      hour: '2-digit',
      minute: '2-digit',
      timeZone: 'America/Los_Angeles',
      timeZoneName: 'short',
    });

    expect(screen.getByText(expectedDate)).toBeInTheDocument();
    expect(screen.getByText(expectedTime)).toBeInTheDocument();
  });

  it('labels all-day events without exposing midnight as a meeting time', async () => {
    const allDayEvent = createCanonicalEventFixture({
      schedule: {
        ...mockEvent.schedule,
        start_at: '2026-07-11T00:00:00+00:00',
        end_at: '2026-07-12T00:00:00+00:00',
        timezone: 'Europe/Dublin',
        all_day: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: allDayEvent });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });
    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });

    expect(screen.getByText('All day')).toBeInTheDocument();
    expect(screen.queryByText(/00:00/)).not.toBeInTheDocument();
  });

  it('links authorised event staff to the server-gated management workspace', async () => {
    const staffEvent = createCanonicalEventFixture({
      permissions: {
        ...mockEvent.permissions,
        manage_staff: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: staffEvent });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });

    const manageLink = await screen.findByRole('link', { name: 'Manage Community Garden Day' });
    expect(manageLink).toHaveAttribute('href', '/test/events/1/manage');
  });

  it('announces a postponed operational state without relying on colour', async () => {
    const postponedEvent = createCanonicalEventFixture({
      schedule: {
        ...mockEvent.schedule,
        state: 'postponed',
        operational_state: 'postponed',
        lifecycle_version: 2,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: postponedEvent });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });

    await screen.findByRole('heading', { level: 1, name: 'Community Garden Day' });
    expect(screen.getByRole('status')).toHaveTextContent('Event postponed');
  });

  it('archives instead of claiming to delete event history', async () => {
    const ownerEvent = createCanonicalEventFixture({
      permissions: {
        ...mockEvent.permissions,
        edit: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: ownerEvent });
    });
    mockApi.delete.mockResolvedValueOnce({
      success: true,
      data: {
        action: 'archive',
        requested_action: 'delete',
        outcome: 'archived',
        event_id: 1,
        changed: true,
        replayed: false,
        idempotent_replay: false,
        archived: true,
        already_archived: false,
        deleted: false,
        publication_status: 'archived',
        operational_status: 'cancelled',
        lifecycle_version: 2,
        reason: null,
      },
    });

    const user = userEvent.setup();
    await renderLoadedEvent(true);
    await user.click(screen.getByRole('button', { name: 'Archive Community Garden Day' }));

    expect(screen.getByRole('dialog')).toHaveTextContent('preserving its history');
    await user.click(screen.getByRole('button', { name: 'Archive Event' }));

    await waitFor(() => expect(mockApi.delete).toHaveBeenCalledWith(
      '/v2/events/1',
      expect.objectContaining({ headers: expect.any(Headers) }),
    ));
    expect(mockToast.success).toHaveBeenCalledWith('Event archived');
    expect(screen.queryByText(/cannot be undone/i)).not.toBeInTheDocument();
  });

  it('requires a cancellation reason before enabling the destructive transition', async () => {
    const ownerEvent = createCanonicalEventFixture({
      permissions: {
        ...mockEvent.permissions,
        cancel: true,
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: ownerEvent });
    });

    const user = userEvent.setup();
    await renderLoadedEvent();
    await user.click(screen.getByRole('button', { name: 'Cancel Community Garden Day' }));

    expect(screen.getByRole('button', { name: 'Cancel Event' })).toBeDisabled();
    expect(screen.getByRole('textbox', { name: /Reason for cancellation/i })).toBeRequired();
  });

  it('shows an error state when the API rejects', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees')) return Promise.resolve({ success: true, data: [] });
      return Promise.reject(new Error('Network error'));
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to Load Event');
  });

  it('shows the not-found description when the event response is unsuccessful', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees')) return Promise.resolve({ success: true, data: [] });
      return Promise.resolve({ success: false, data: null });
    });

    renderEventRoute(<EventDetailPage />, {
      route: '/test/events/1',
      path: '/:tenantSlug/events/:id',
    });

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The event you are looking for does not exist',
    );
  });

  it('renders accessible RSVP controls for an authenticated non-organizer', async () => {
    await renderLoadedEvent();

    expect(screen.getByRole('radiogroup', { name: 'RSVP options' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Mark yourself as going' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Mark yourself as interested' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: 'Mark yourself as not going' })).toBeInTheDocument();
  });

  it('presents and accepts a live waitlist offer without conflicting RSVP controls', async () => {
    const offeredEvent = createCanonicalEventFixture({
      relationship: {
        ...mockEvent.relationship,
        registration: {
          ...mockEvent.relationship.registration,
          state: 'offered',
          waitlist_position: 1,
          can_register: false,
          can_join_waitlist: false,
          can_leave_waitlist: true,
        },
        capacity: {
          ...mockEvent.relationship.capacity,
          remaining: 0,
          is_full: true,
          waitlist_count: 1,
        },
      },
    });
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees') || url.startsWith('/v2/polls?')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: offeredEvent });
    });
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: {
        relationship: {
          registration: { state: 'confirmed' },
          waitlist: { state: 'accepted', position: 1, offer_active: false },
        },
        mutation: {
          changed: true,
          idempotent_replay: false,
          history_entry_id: 901,
          next_offer_created: false,
        },
      },
    });

    const user = userEvent.setup();
    await renderLoadedEvent();

    expect(screen.getByText('A place is available for you')).toBeInTheDocument();
    expect(screen.queryByRole('radiogroup', { name: 'RSVP options' })).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Accept the available place for this event' }));

    await waitFor(() => expect(mockApi.post).toHaveBeenCalledWith(
      '/v2/events/1/registration/waitlist/accept',
      {},
      expect.objectContaining({ headers: expect.any(Headers) }),
    ));
    const options = mockApi.post.mock.calls[0]?.[2];
    expect(new Headers(options.headers).get('Idempotency-Key')).toBeTruthy();
    expect(new Headers(options.headers).get('X-Events-Contract')).toBe('2');
    expect(mockToast.success).toHaveBeenCalledWith('Your place at the event is confirmed.');
  });

  it('clears a loaded event after navigation to an event that fails to load', async () => {
    const { router } = await renderLoadedEvent();

    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/attendees')) return Promise.resolve({ success: true, data: [] });
      return Promise.resolve({ success: false, data: null });
    });

    await act(async () => {
      await router.navigate('/test/events/2');
    });

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The event you are looking for does not exist',
    );
    expect(screen.queryByText('Community Garden Day')).not.toBeInTheDocument();
  });
});
