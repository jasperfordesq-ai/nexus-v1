// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
    put: jest.fn(),
    patch: jest.fn(),
    delete: jest.fn(),
    upload: jest.fn(),
  },
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(mockStatus: number, mockMessage: string) {
      super(mockMessage);
      this.status = mockStatus;
      this.name = 'ApiResponseError';
    }
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

jest.mock('@sentry/react-native', () => ({ captureMessage: jest.fn() }));

import * as Sentry from '@sentry/react-native';
import { api } from '@/lib/api/client';
import {
  EVENT_AGENDA_CONTRACT_VERSION,
  EVENTS_CONTRACT_HEADER,
  EVENTS_CONTRACT_VERSION,
  acceptEventWaitlistOffer,
  checkInEventAttendee,
  createEvent,
  deleteEventReminders,
  eventAgendaSchema,
  eventAgendaSpeakerSchema,
  eventRegistrationResponseSchema,
  eventAttendanceRosterResponseSchema,
  eventRosterMemberSchema,
  eventSchema,
  eventSeriesSchema,
  eventsResponseSchema,
  getCanonicalEvents,
  getEvent,
  getEventAgenda,
  getEventAttendees,
  getEventAttendanceRoster,
  getEventCategories,
  getEventPolls,
  getEventReminders,
  getEvents,
  getEventSeries,
  joinEventWaitlist,
  leaveEventWaitlist,
  removeRsvp,
  registerEventAgendaSession,
  rsvpEvent,
  transitionEventAttendance,
  updateEvent,
  updateEventReminders,
  voteEventPoll,
  withdrawEventAgendaSession,
} from './events';

const eventDetailFixture: unknown = require('../../../contracts/events/v2/event-detail.json');
const eventListFixture: unknown = require('../../../contracts/events/v2/event-list-response.json');
const eventRegistrationFixture: unknown = require('../../../contracts/events/v2/event-registration.json');
const eventRosterFixture: unknown = require('../../../contracts/events/v2/event-roster-item.json');
const eventSeriesFixture: unknown = require('../../../contracts/events/v2/event-series.json');
const eventAgendaFixture: unknown = require('../../../contracts/events/v2/event-agenda.json');

const contractOptions = {
  headers: { [EVENTS_CONTRACT_HEADER]: String(EVENTS_CONTRACT_VERSION) },
};

const reminderPreferencesFixture = {
  revision: 3,
  overrides: {
    email_enabled: true,
    in_app_enabled: true,
    web_push_enabled: false,
    fcm_enabled: true,
    realtime_enabled: true,
    cadence: 'instant' as const,
    reminders_enabled: true,
  },
  rules: [{
    id: 9,
    offset_minutes: 60,
    enabled: true,
    rule_version: 2,
    email_enabled: null,
    in_app_enabled: null,
    web_push_enabled: null,
    fcm_enabled: null,
    realtime_enabled: null,
  }],
  resolved: {
    channels: { email: true, in_app: true, web_push: false, fcm: true, realtime: true },
    channel_sources: { email: 'event' },
    cadence: 'instant' as const,
    cadence_source: 'event',
    reminders_enabled: true,
    reminders_source: 'event',
  },
  limits: {
    minimum_offset_minutes: 5,
    maximum_offset_minutes: 43_200,
    maximum_rules: 8,
    default_offsets_minutes: [10_080, 1_440, 60],
  },
  capabilities: { independent_channels: true, diagnostics_supported: false },
};

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Events v2 shared contract fixtures', () => {
  it('parses every shared backend fixture', () => {
    expect(eventSchema.parse(eventDetailFixture).id).toBe(101);
    expect(eventsResponseSchema.parse(eventListFixture).data[0].id).toBe(202);
    expect(eventRegistrationResponseSchema.parse(eventRegistrationFixture).event_id).toBe(101);
    expect(eventRosterMemberSchema.parse(eventRosterFixture).member.id).toBe(44);
    expect(eventSeriesSchema.parse(eventSeriesFixture).id).toBe(12);
    expect(eventAgendaSchema.parse(eventAgendaFixture).agenda_version).toBe(3);
  });

  it('rejects agenda resource URLs that are not credential-free HTTPS', () => {
    const unsafeScheme = JSON.parse(JSON.stringify(eventAgendaFixture)) as {
      sessions: Array<{ resources: Array<{ url: string | null }> }>;
    };
    unsafeScheme.sessions[0]!.resources[0]!.url = 'http://events.example.test/slides';
    expect(eventAgendaSchema.safeParse(unsafeScheme).success).toBe(false);

    const embeddedCredentials = JSON.parse(JSON.stringify(eventAgendaFixture)) as typeof unsafeScheme;
    embeddedCredentials.sessions[0]!.resources[0]!.url = 'https://member:secret@events.example.test/slides';
    expect(eventAgendaSchema.safeParse(embeddedCredentials).success).toBe(false);
  });
});

describe('read-only Event agenda contract', () => {
  it('strictly parses agenda v1 and negotiates the canonical Events header', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: eventAgendaFixture,
      meta: { base_url: 'https://test.api' },
    });

    const result = await getEventAgenda(101);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/101/agenda', undefined, contractOptions);
    expect(result.data.contract_version).toBe(EVENT_AGENDA_CONTRACT_VERSION);
    expect(result.data.sessions[0].speakers[0]).toEqual(expect.objectContaining({
      kind: 'member',
      member_id: 7,
    }));
    expect(eventAgendaSpeakerSchema.safeParse({
      kind: 'external',
      member_id: 7,
      display_name: 'Private identity mismatch',
      role: null,
      position: 1,
    }).success).toBe(false);
  });

  it('rejects unknown agenda fields without sending response values to drift telemetry', async () => {
    const agenda = eventAgendaFixture as Record<string, unknown>;
    const sessions = agenda.sessions as Array<Record<string, unknown>>;
    (api.get as jest.Mock).mockResolvedValue({
      data: {
        ...agenda,
        sessions: [{ ...sessions[0], private_notes: 'never report this agenda note' }],
      },
      meta: { base_url: 'https://test.api' },
    });

    await expect(getEventAgenda(101)).rejects.toMatchObject({ message: 'EVENTS_CONTRACT_DRIFT' });

    expect(Sentry.captureMessage).toHaveBeenCalledWith('Events contract drift', expect.objectContaining({
      tags: expect.objectContaining({
        module: 'events',
        contract_version: '1',
        endpoint: '/api/v2/events/{id}/agenda',
      }),
      extra: {
        issues: expect.arrayContaining([
          expect.objectContaining({ path: 'data.sessions.0', code: 'unrecognized_keys' }),
        ]),
      },
    }));
    expect(JSON.stringify((Sentry.captureMessage as jest.Mock).mock.calls[0][1]))
      .not.toContain('never report this agenda note');
  });

  it('uses versioned idempotent session registration endpoints without changing event registration', async () => {
    const session = (eventAgendaFixture as { sessions: unknown[] }).sessions[0];
    const mutation = {
      data: {
        session,
        registration_version: 2,
        changed: true,
        idempotent_replay: false,
        history_entry_id: 81,
      },
    };
    (api.post as jest.Mock).mockResolvedValueOnce(mutation).mockResolvedValueOnce(mutation);

    await registerEventAgendaSession(101, 501, 1, 'mobile-session-register');
    await withdrawEventAgendaSession(101, 501, 2, 'mobile-session-withdraw');

    expect(api.post).toHaveBeenNthCalledWith(1,
      '/api/v2/events/101/agenda/sessions/501/registration',
      { expected_version: 1 },
      { headers: { ...contractOptions.headers, 'Idempotency-Key': 'mobile-session-register' } },
    );
    expect(api.post).toHaveBeenNthCalledWith(2,
      '/api/v2/events/101/agenda/sessions/501/registration/withdraw',
      { expected_version: 2 },
      { headers: { ...contractOptions.headers, 'Idempotency-Key': 'mobile-session-withdraw' } },
    );
  });
});

describe('canonical Events boundary', () => {
  it('negotiates and parses the list contract', async () => {
    (api.get as jest.Mock).mockResolvedValue(eventListFixture);

    const result = await getCanonicalEvents('upcoming', 'cursor-abc', 10, {
      groupId: 9,
      stepFree: 'unknown',
    });

    expect(api.get).toHaveBeenCalledWith('/api/v2/events', {
      when: 'upcoming',
      per_page: '10',
      cursor: 'cursor-abc',
      group_id: '9',
      step_free: 'unknown',
    }, contractOptions);
    expect(result.data[0].location.label).toBe('Town square');
  });

  it('keeps the Group tab compatible without leaking legacy shape into Events screens', async () => {
    (api.get as jest.Mock).mockResolvedValue(eventListFixture);

    const result = await getEvents();

    expect(result.data[0]).toEqual(expect.objectContaining({
      id: 202,
      start_date: '2030-06-01T10:00:00+00:00',
      location: 'Town square',
      is_online: false,
      attendees_count: 0,
    }));
  });

  it('negotiates and parses detail, create, and update responses', async () => {
    const envelope = { data: eventDetailFixture };
    (api.get as jest.Mock).mockResolvedValue(envelope);
    (api.post as jest.Mock).mockResolvedValue(envelope);
    (api.put as jest.Mock).mockResolvedValue(envelope);

    const detail = await getEvent(101);
    const payload = {
      title: 'Community repair morning',
      description: 'Repair together.',
      start_time: '2030-05-01T10:15:00+00:00',
      category_id: 4,
    };
    const created = await createEvent(payload);
    const updated = await updateEvent(101, payload);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/101', undefined, contractOptions);
    expect(api.post).toHaveBeenCalledWith('/api/v2/events', payload, contractOptions);
    expect(api.put).toHaveBeenCalledWith('/api/v2/events/101', payload, contractOptions);
    expect(detail.data.organizer.display_name).toBe('Alex Morgan');
    expect(created.data.schedule.state).toBe('upcoming');
    expect(updated.data.permissions.edit).toBe(false);
  });

  it('reports privacy-safe, stable drift telemetry and rejects the response', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: { ...(eventDetailFixture as Record<string, unknown>), title: 42, description: 'private description' },
    });

    await expect(getEvent(101)).rejects.toMatchObject({ message: 'EVENTS_CONTRACT_DRIFT' });

    expect(Sentry.captureMessage).toHaveBeenCalledWith('Events contract drift', expect.objectContaining({
      tags: expect.objectContaining({
        module: 'events',
        contract_version: '2',
        endpoint: '/api/v2/events/{id}',
      }),
      extra: {
        issues: expect.arrayContaining([expect.objectContaining({ path: 'data.title', code: expect.any(String) })]),
      },
    }));
    expect(JSON.stringify((Sentry.captureMessage as jest.Mock).mock.calls[0][1])).not.toContain('private description');
  });
});

describe('canonical registration, roster, and series resources', () => {
  it('parses RSVP relationship state from the shared fixture', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: eventRegistrationFixture });

    const result = await rsvpEvent(101, 'going');

    expect(api.post).toHaveBeenCalledWith('/api/v2/events/101/rsvp', { status: 'going' }, contractOptions);
    expect(result.data.relationship.registration.state).toBe('confirmed');
    expect(result.data.metrics.confirmed_count).toBe(19);
  });

  it('parses roster members and negotiates organizer filters', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [eventRosterFixture],
      meta: { per_page: 25, has_more: false, cursor: null },
    });

    const result = await getEventAttendees(101, { perPage: 25, status: 'all' });

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/101/attendees', {
      per_page: '25',
      status: 'all',
    }, contractOptions);
    expect(result.data[0].attendance.state).toBe('checked_in');
  });

  it('parses the canonical series resource', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: eventSeriesFixture });

    const result = await getEventSeries(12);

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/series/12', undefined, contractOptions);
    expect(result.data.occurrences[0].id).toBe(101);
  });

  it('parses dynamic event categories including legacy plural type rows', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ id: 4, name: 'Workshops', slug: 'workshops', color: '#2563eb', type: 'events' }],
    });

    const result = await getEventCategories();

    expect(api.get).toHaveBeenCalledWith('/api/v2/categories', { type: 'event' }, contractOptions);
    expect(result.data[0].slug).toBe('workshops');
  });
});

describe('bounded mobile attendance operations', () => {
  const attendancePerson = {
    member: { id: 44, display_name: 'Taylor Member', avatar_url: null },
    registration: { state: 'confirmed' as const },
    attendance: {
      id: null,
      state: 'not_checked_in' as const,
      version: null,
      changed_at: null,
      checked_in_at: null,
      checked_out_at: null,
    },
    management_actions: {
      check_in: true,
      check_out: false,
      no_show: true,
      undo_attendance: false,
      idempotency_key_required: true as const,
    },
    privacy: { projection: 'attendance' as const, sensitive_fields_redacted: true as const },
  };
  const attendanceMeta = {
    base_url: 'https://test.api',
    current_page: 1,
    per_page: 25,
    total: 1,
    total_pages: 1,
    has_more: false,
    search: null,
    registration_state: null,
    waitlist_state: null,
    attendance_state: null,
    engagement_state: null,
    sort: 'name' as const,
    direction: 'asc' as const,
    sensitive_fields_redacted: true as const,
    projection: 'attendance' as const,
    capabilities: {
      view_roster: true as const,
      view_waitlist: false,
      manage_registration: false,
      manage_attendance: true as const,
      export_people: false,
      view_history: true,
    },
    metrics: { confirmed: 1, checked_in: 0, checked_out: 0, no_show: 0, attended: 0 },
  };

  it('strictly parses the redacted projection and sends only bounded query fields', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [attendancePerson], meta: attendanceMeta });

    const result = await getEventAttendanceRoster(101, {
      page: 2,
      search: ' Taylor ',
      attendanceState: 'not_checked_in',
    });

    expect(api.get).toHaveBeenCalledWith('/api/v2/events/101/people', {
      page: '2',
      per_page: '25',
      sort: 'name',
      direction: 'asc',
      search: 'Taylor',
      attendance_state: 'not_checked_in',
    }, contractOptions);
    expect(result.meta.projection).toBe('attendance');
    expect(result.data[0].member.display_name).toBe('Taylor Member');
  });

  it('rejects accidental sensitive fields in the mobile roster contract', () => {
    const parsed = eventAttendanceRosterResponseSchema.safeParse({
      data: [{ ...attendancePerson, member: { ...attendancePerson.member, email: 'private@example.test' } }],
      meta: attendanceMeta,
    });

    expect(parsed.success).toBe(false);
  });

  it('binds the caller-stable idempotency key to both attendance request locations', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: {
        member: { id: 44, display_name: 'Taylor Member' },
        mutation: {
          attendance_id: 501,
          event_id: 101,
          user_id: 44,
          action: 'check_in',
          from_state: 'not_checked_in',
          to_state: 'checked_in',
          changed: true,
          idempotent_replay: false,
          attendance_version: 1,
          changed_at: '2030-06-01T10:00:00Z',
          checked_in_at: '2030-06-01T10:00:00Z',
          checked_out_at: null,
          history_entry_id: 700,
        },
      },
      meta: { base_url: 'https://test.api' },
    });

    const result = await transitionEventAttendance(101, 44, {
      action: 'check_in',
      expectedVersion: 0,
      idempotencyKey: 'mobile-attendance-stable-1',
    });

    expect(result.data.mutation.attendance_version).toBe(1);
    expect(api.post).toHaveBeenCalledWith(
      '/api/v2/events/101/people/44/attendance',
      {
        action: 'check_in',
        expected_version: 0,
        idempotency_key: 'mobile-attendance-stable-1',
      },
      {
        headers: {
          ...contractOptions.headers,
          'Idempotency-Key': 'mobile-attendance-stable-1',
        },
      },
    );
  });
});

describe('remaining Events calls', () => {
  it('accepts a waitlist offer with a caller-stable idempotency key', async () => {
    (api.post as jest.Mock).mockResolvedValue({
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

    const result = await acceptEventWaitlistOffer(101, 'mobile-accept-offer-1');

    expect(result.data.relationship.registration.state).toBe('confirmed');
    expect(api.post).toHaveBeenCalledWith(
      '/api/v2/events/101/registration/waitlist/accept',
      {},
      {
        headers: {
          ...contractOptions.headers,
          'Idempotency-Key': 'mobile-accept-offer-1',
        },
      },
    );
  });

  it('negotiates check-in, waitlist, RSVP removal, and reminders', async () => {
    (api.post as jest.Mock)
      .mockResolvedValueOnce({ data: { checked_in: true, attendee_id: 44, event_id: 101, credit_status: 'disabled', hours_credited: null } })
      .mockResolvedValueOnce({ data: { waitlisted: true, position: 3 } });
    (api.delete as jest.Mock)
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce(undefined)
      .mockResolvedValueOnce({ data: reminderPreferencesFixture });
    (api.get as jest.Mock).mockResolvedValue({ data: reminderPreferencesFixture });
    (api.put as jest.Mock).mockResolvedValue({ data: reminderPreferencesFixture });

    await checkInEventAttendee(101, 44);
    await joinEventWaitlist(101);
    await leaveEventWaitlist(101);
    await removeRsvp(101);
    await getEventReminders(101);
    await updateEventReminders(101, {
      expected_revision: 3,
      overrides: reminderPreferencesFixture.overrides,
      rules: reminderPreferencesFixture.rules,
    });
    await deleteEventReminders(101, 3);

    expect(api.post).toHaveBeenNthCalledWith(1, '/api/v2/events/101/attendees/44/check-in', undefined, contractOptions);
    expect(api.post).toHaveBeenNthCalledWith(2, '/api/v2/events/101/waitlist', undefined, contractOptions);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/events/101/waitlist', contractOptions);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/events/101/rsvp', contractOptions);
    expect(api.get).toHaveBeenCalledWith('/api/v2/events/101/reminders', undefined, contractOptions);
    expect(api.put).toHaveBeenCalledWith('/api/v2/events/101/reminders', {
      expected_revision: 3,
      overrides: reminderPreferencesFixture.overrides,
      rules: reminderPreferencesFixture.rules,
    }, contractOptions);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/events/101/reminders?expected_revision=3', contractOptions);
  });

  it('leaves non-Events poll endpoints on their own contract', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 5, question: 'Which time?', options: [] } });

    await getEventPolls(101);
    await voteEventPoll(5, 9);

    expect(api.get).toHaveBeenCalledWith('/api/v2/polls', {
      event_id: '101',
      status: 'all',
      per_page: '50',
    });
    expect(api.post).toHaveBeenCalledWith('/api/v2/polls/5/vote', { option_id: 9 });
  });
});
