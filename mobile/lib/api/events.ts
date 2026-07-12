// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import * as Sentry from '@sentry/react-native';
import { Platform } from 'react-native';
import { z } from 'zod';

import { api, ApiResponseError, type RequestOptions } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export const EVENTS_CONTRACT_VERSION = 2 as const;
export const EVENTS_CONTRACT_HEADER = 'X-Events-Contract' as const;
export const EVENT_AGENDA_CONTRACT_VERSION = 1 as const;

const eventRequestOptions: RequestOptions = {
  headers: { [EVENTS_CONTRACT_HEADER]: String(EVENTS_CONTRACT_VERSION) },
};

const nullableString = z.string().nullable();

export const eventEngagementSchema = z.object({
  state: z.enum(['none', 'interested']),
  can_change: z.boolean(),
});

export const eventRegistrationSchema = z.object({
  state: z.enum(['none', 'invited', 'pending', 'confirmed', 'waitlisted', 'offered', 'declined', 'cancelled']),
  waitlist_position: z.number().int().nonnegative().nullable(),
  can_register: z.boolean(),
  can_withdraw: z.boolean(),
  can_join_waitlist: z.boolean(),
  can_leave_waitlist: z.boolean(),
});

export const eventAttendanceSchema = z.object({
  state: z.enum(['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show']),
  checked_in_at: nullableString,
  checked_out_at: nullableString,
});

export const eventCapacitySchema = z.object({
  limit: z.number().int().nonnegative().nullable(),
  confirmed: z.number().int().nonnegative(),
  remaining: z.number().int().nonnegative().nullable(),
  is_full: z.boolean(),
  waitlist_count: z.number().int().nonnegative(),
});

export const eventRelationshipSchema = z.object({
  engagement: eventEngagementSchema,
  registration: eventRegistrationSchema,
  attendance: eventAttendanceSchema,
  capacity: eventCapacitySchema,
});

export const eventMetricsSchema = z.object({
  confirmed_count: z.number().int().nonnegative(),
  interested_count: z.number().int().nonnegative(),
  waitlist_count: z.number().int().nonnegative(),
});

const eventSeriesOccurrenceSchema = z.object({
  id: z.number().int().nonnegative(),
  start_at: nullableString,
  date: nullableString,
});

export const eventSchema = z.object({
  contract_version: z.literal(EVENTS_CONTRACT_VERSION),
  id: z.number().int().positive(),
  title: z.string(),
  description: nullableString,
  primary_image: z.object({
    url: z.string(),
    alt_text: z.string(),
  }).nullable(),
  organizer: z.object({
    id: z.number().int().nonnegative(),
    display_name: nullableString,
    avatar_url: nullableString,
    relationship: z.enum(['self', 'member']),
    actions: z.object({
      view_profile: z.boolean(),
      message: z.boolean(),
    }),
  }),
  category: z.object({
    id: z.number().int().positive().nullable(),
    name: nullableString,
    slug: nullableString,
    colour: nullableString,
  }).nullable(),
  location: z.object({
    label: nullableString,
    latitude: z.number().nullable(),
    longitude: z.number().nullable(),
    mode: z.enum(['in_person', 'online', 'hybrid']),
  }),
  schedule: z.object({
    start_at: nullableString,
    end_at: nullableString,
    timezone: z.string(),
    all_day: z.boolean(),
    state: z.enum([
      'draft',
      'pending_review',
      'upcoming',
      'ongoing',
      'ended',
      'postponed',
      'cancelled',
      'completed',
      'archived',
    ]),
    publication_state: z.enum(['draft', 'pending_review', 'published', 'archived']),
    operational_state: z.enum(['scheduled', 'postponed', 'cancelled', 'completed']),
    lifecycle_version: z.number().int().nonnegative(),
    cancellation_reason: nullableString,
  }),
  relationship: eventRelationshipSchema,
  online_access: z.object({
    mode: z.enum(['in_person', 'online', 'hybrid']),
    reveal_state: z.enum(['not_applicable', 'not_configured', 'restricted', 'scheduled', 'available', 'expired']),
    join_url: nullableString,
    video_url: nullableString,
    reveal_at: nullableString,
    expires_at: nullableString,
  }),
  series: z.object({
    named: z.object({
      id: z.number().int().positive().nullable(),
      title: nullableString,
      description: nullableString,
      event_count: z.number().int().nonnegative(),
    }).nullable(),
    recurrence: z.object({
      parent_event_id: z.number().int().positive().nullable(),
      root_event_id: z.number().int().nonnegative(),
      is_template: z.boolean(),
      frequency: nullableString,
      interval: z.number().int().positive(),
      rrule: nullableString,
      occurrence_count: z.number().int().nonnegative(),
      occurrences: z.array(eventSeriesOccurrenceSchema),
    }).nullable(),
  }),
  permissions: z.object({
    edit: z.boolean(),
    cancel: z.boolean(),
    manage_people: z.boolean(),
    check_in: z.boolean(),
    message: z.boolean(),
    export: z.boolean(),
    publish: z.boolean(),
    manage_agenda: z.boolean(),
    manage_staff: z.boolean(),
    manage_registration: z.boolean(),
    broadcast: z.boolean(),
    manage_finance: z.boolean(),
    reconcile_credits: z.boolean(),
    reconcile_tickets: z.boolean(),
    transfer_ownership: z.boolean(),
  }),
  metrics: eventMetricsSchema,
  created_at: nullableString,
  updated_at: nullableString,
  group: z.object({
    id: z.number().int().positive(),
    name: z.string(),
    slug: nullableString,
  }).nullable().optional(),
  distance_km: z.number().nonnegative().optional(),
  federated_visibility: z.enum(['none', 'listed', 'bookable']).nullable().optional(),
}).passthrough();

export const eventsResponseSchema = z.object({
  data: z.array(eventSchema),
  meta: z.object({
    base_url: z.string().optional(),
    per_page: z.number().int().positive(),
    has_more: z.boolean(),
    cursor: nullableString.optional(),
  }).passthrough(),
}).passthrough();

export const eventRegistrationResponseSchema = z.object({
  contract_version: z.literal(EVENTS_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  relationship: eventRelationshipSchema,
  metrics: eventMetricsSchema,
  status: nullableString,
  rsvp_counts: z.object({
    going: z.number().int().nonnegative(),
    interested: z.number().int().nonnegative(),
  }),
  waitlist_position: z.number().int().nonnegative().nullable(),
  message: nullableString,
}).passthrough();

export const eventRosterMemberSchema = z.object({
  contract_version: z.literal(EVENTS_CONTRACT_VERSION),
  member: z.object({
    id: z.number().int().positive(),
    display_name: nullableString,
    avatar_url: nullableString,
  }),
  engagement: eventEngagementSchema,
  registration: eventRegistrationSchema,
  attendance: eventAttendanceSchema,
  registered_at: nullableString,
}).passthrough();

export const eventSeriesSchema = z.object({
  contract_version: z.literal(EVENTS_CONTRACT_VERSION),
  id: z.number().int().positive(),
  title: z.string(),
  description: nullableString,
  event_count: z.number().int().nonnegative(),
  next_event_at: nullableString,
  creator: nullableString,
  created_at: nullableString,
  occurrences: z.array(z.object({
    id: z.number().int().positive(),
    title: nullableString,
    start_at: nullableString,
    end_at: nullableString,
    status: z.string(),
    location_label: nullableString,
  })),
}).passthrough();

export const eventCategorySchema = z.object({
  id: z.number().int().positive(),
  name: z.string(),
  slug: z.string(),
  color: nullableString.optional(),
  type: z.enum(['event', 'events']).optional(),
}).passthrough();

const eventAgendaSpeakerFields = {
  display_name: nullableString,
  role: nullableString,
  position: z.number().int().nonnegative(),
};

export const eventAgendaSpeakerSchema = z.discriminatedUnion('kind', [
  z.object({
    kind: z.literal('member'),
    member_id: z.number().int().positive(),
    ...eventAgendaSpeakerFields,
  }).strict(),
  z.object({
    kind: z.literal('external'),
    member_id: z.null(),
    ...eventAgendaSpeakerFields,
  }).strict(),
]);

const eventAgendaHttpsUrlSchema = z.string().url().refine((value) => {
  try {
    const parsed = new URL(value);
    return parsed.protocol === 'https:'
      && parsed.hostname !== ''
      && parsed.username === ''
      && parsed.password === '';
  } catch {
    return false;
  }
});

export const eventAgendaResourceSchema = z.object({
  id: z.number().int().positive(),
  type: z.enum(['link', 'document', 'slides', 'download', 'stream', 'recording']),
  title: z.string().min(1),
  visibility: z.enum(['public', 'registered', 'staff']),
  position: z.number().int().nonnegative(),
  protected: z.boolean(),
  available: z.boolean(),
  url: eventAgendaHttpsUrlSchema.nullable(),
}).strict();

export const eventAgendaSessionSchema = z.object({
  id: z.number().int().positive(),
  version: z.number().int().positive(),
  title: z.string().min(1),
  description: nullableString,
  type: z.enum(['session', 'keynote', 'workshop', 'panel', 'break', 'networking', 'other']),
  visibility: z.enum(['public', 'registered', 'staff']),
  capacity: z.object({
    limit: z.number().int().positive().nullable(),
    registered: z.number().int().nonnegative(),
    remaining: z.number().int().nonnegative().nullable(),
    is_full: z.boolean(),
  }).strict(),
  registration: z.object({
    state: z.enum(['not_registered', 'registered', 'withdrawn', 'ineligible']),
    version: z.number().int().nonnegative(),
    can_register: z.boolean(),
    can_withdraw: z.boolean(),
  }).strict(),
  status: z.enum(['scheduled', 'cancelled']),
  start_at: z.string().datetime({ offset: true }),
  end_at: z.string().datetime({ offset: true }),
  timezone: z.string().min(1),
  track: nullableString,
  room: nullableString,
  position: z.number().int().nonnegative(),
  cancellation_reason: nullableString,
  speakers: z.array(eventAgendaSpeakerSchema),
  resources: z.array(eventAgendaResourceSchema),
}).strict();

export const eventAgendaSchema = z.object({
  contract_version: z.literal(EVENT_AGENDA_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  agenda_version: z.number().int().nonnegative(),
  timezone: z.string().min(1),
  permissions: z.object({ manage: z.boolean() }).strict(),
  sessions: z.array(eventAgendaSessionSchema),
}).strict();

const eventEnvelopeSchema = z.object({ data: eventSchema }).passthrough();
const eventRegistrationEnvelopeSchema = z.object({ data: eventRegistrationResponseSchema }).passthrough();
const eventRosterResponseSchema = z.object({
  data: z.array(eventRosterMemberSchema),
  meta: z.object({
    per_page: z.number().int().positive().optional(),
    has_more: z.boolean().optional(),
    cursor: nullableString.optional(),
  }).passthrough().optional(),
}).passthrough();

const eventPeopleMemberSchema = z.object({
  id: z.number().int().positive(),
  display_name: nullableString,
  avatar_url: nullableString,
}).strict();

const eventPeopleAttendanceFactSchema = z.object({
  id: z.number().int().positive().nullable(),
  state: z.enum(['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show']),
  version: z.number().int().positive().nullable(),
  changed_at: nullableString,
  checked_in_at: nullableString,
  checked_out_at: nullableString,
}).strict();

const eventPeopleAttendanceActionsSchema = z.object({
  check_in: z.boolean(),
  check_out: z.boolean(),
  no_show: z.boolean(),
  undo_attendance: z.boolean(),
  idempotency_key_required: z.literal(true),
}).strict();

const eventPeopleAttendancePersonSchema = z.object({
  member: eventPeopleMemberSchema,
  registration: z.object({
    state: z.enum(['invited', 'pending', 'confirmed', 'declined', 'cancelled']).nullable(),
  }).strict(),
  attendance: eventPeopleAttendanceFactSchema,
  management_actions: eventPeopleAttendanceActionsSchema,
  privacy: z.object({
    projection: z.literal('attendance'),
    sensitive_fields_redacted: z.literal(true),
  }).strict(),
}).strict();

const eventPeopleFullPersonSchema = z.object({
  member: eventPeopleMemberSchema,
  engagement: z.object({
    state: z.enum(['none', 'interested']),
    consumes_capacity: z.literal(false),
  }).strict(),
  registration: z.object({
    id: z.number().int().positive().nullable(),
    state: z.enum(['invited', 'pending', 'confirmed', 'declined', 'cancelled']).nullable(),
    version: z.number().int().nonnegative().nullable(),
    capacity_pool_key: z.string().min(1),
    allocation_key: nullableString,
    changed_at: nullableString,
    confirmed_at: nullableString,
  }).strict(),
  waitlist: z.object({
    id: z.number().int().positive().nullable(),
    state: z.enum(['waiting', 'offered', 'accepted', 'expired', 'cancelled']).nullable(),
    version: z.number().int().positive().nullable(),
    position: z.number().int().positive().nullable(),
    sequence: z.number().int().positive().nullable(),
    offered_at: nullableString,
    offer_expires_at: nullableString,
    accepted_at: nullableString,
  }).strict(),
  attendance: eventPeopleAttendanceFactSchema,
  management_actions: eventPeopleAttendanceActionsSchema.extend({
    approve: z.boolean(),
    reject: z.boolean(),
    cancel: z.boolean(),
  }).strict(),
  privacy: z.object({ sensitive_fields_redacted: z.literal(true) }).strict(),
}).strict();

export const eventAttendanceRosterPersonSchema = z.union([
  eventPeopleAttendancePersonSchema,
  eventPeopleFullPersonSchema,
]);

const eventAttendanceRosterMetaBaseSchema = z.object({
  base_url: z.string(),
  current_page: z.number().int().positive(),
  per_page: z.number().int().min(1).max(25),
  total: z.number().int().nonnegative(),
  total_pages: z.number().int().nonnegative(),
  has_more: z.boolean(),
  search: nullableString,
  registration_state: nullableString,
  waitlist_state: nullableString,
  attendance_state: nullableString,
  engagement_state: nullableString,
  sort: z.enum(['name', 'registration_changed', 'queue_rank', 'attendance_changed']),
  direction: z.enum(['asc', 'desc']),
  sensitive_fields_redacted: z.literal(true),
  capabilities: z.object({
    view_roster: z.literal(true),
    view_waitlist: z.boolean(),
    manage_registration: z.boolean(),
    manage_attendance: z.literal(true),
    export_people: z.boolean(),
    view_history: z.boolean(),
  }).strict(),
}).strict();

export const eventAttendanceRosterMetaSchema = z.discriminatedUnion('projection', [
  eventAttendanceRosterMetaBaseSchema.extend({
    projection: z.literal('attendance'),
    metrics: z.object({
      confirmed: z.number().int().nonnegative(),
      checked_in: z.number().int().nonnegative(),
      checked_out: z.number().int().nonnegative(),
      no_show: z.number().int().nonnegative(),
      attended: z.number().int().nonnegative(),
    }).strict(),
  }).strict(),
  eventAttendanceRosterMetaBaseSchema.extend({
    projection: z.literal('full'),
    metrics: z.object({
      confirmed: z.number().int().nonnegative(),
      waitlisted: z.number().int().nonnegative(),
      checked_in: z.number().int().nonnegative(),
      checked_out: z.number().int().nonnegative(),
      no_show: z.number().int().nonnegative(),
      attended: z.number().int().nonnegative(),
    }).strict(),
  }).strict(),
]);

export const eventAttendanceRosterResponseSchema = z.object({
  data: z.array(eventAttendanceRosterPersonSchema).max(25),
  meta: eventAttendanceRosterMetaSchema,
}).strict();

export const eventAttendanceMutationSchema = z.object({
  member: z.object({
    id: z.number().int().positive(),
    display_name: nullableString,
  }).strict(),
  mutation: z.object({
    attendance_id: z.number().int().positive(),
    event_id: z.number().int().positive(),
    user_id: z.number().int().positive(),
    action: z.enum(['check_in', 'check_out', 'no_show', 'undo']),
    from_state: z.enum(['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show']),
    to_state: z.enum(['not_checked_in', 'checked_in', 'checked_out', 'attended', 'no_show']),
    changed: z.boolean(),
    idempotent_replay: z.boolean(),
    attendance_version: z.number().int().positive(),
    changed_at: nullableString,
    checked_in_at: nullableString,
    checked_out_at: nullableString,
    history_entry_id: z.number().int().positive().nullable(),
  }).strict(),
}).strict();

const eventAttendanceMutationEnvelopeSchema = z.object({
  data: eventAttendanceMutationSchema,
  meta: z.object({ base_url: z.string() }).passthrough(),
}).strict();
const eventSeriesEnvelopeSchema = z.object({ data: eventSeriesSchema }).passthrough();
const eventAgendaEnvelopeSchema = z.object({
  data: eventAgendaSchema,
  meta: z.object({ base_url: z.string().min(1) }).passthrough(),
}).strict();
const eventAgendaRegistrationMutationEnvelopeSchema = z.object({
  data: z.object({
    session: eventAgendaSessionSchema.nullable(),
    registration_version: z.number().int().nonnegative(),
    changed: z.boolean(),
    idempotent_replay: z.boolean(),
    history_entry_id: z.number().int().positive().nullable(),
  }).passthrough(),
}).passthrough();
const eventCategoriesResponseSchema = z.union([
  z.object({ data: z.array(eventCategorySchema) }).passthrough(),
  z.array(eventCategorySchema).transform((data) => ({ data })),
]);
const eventCheckInResponseSchema = z.object({
  data: z.object({
    checked_in: z.literal(true),
    attendee_id: z.number().int().positive(),
    event_id: z.number().int().positive(),
    credit_status: z.string().optional(),
    hours_credited: z.number().nullable().optional(),
  }).passthrough(),
}).passthrough();
const joinEventWaitlistResponseSchema = z.object({
  data: z.object({
    waitlisted: z.literal(true),
    position: z.number().int().nonnegative().nullable(),
  }).passthrough(),
}).passthrough();
const eventWaitlistOfferAcceptanceEnvelopeSchema = z.object({
  data: z.object({
    relationship: z.object({
      registration: z.object({ state: z.literal('confirmed') }).passthrough(),
      waitlist: z.object({
        state: z.literal('accepted'),
        position: z.number().int().nonnegative().nullable(),
        offer_active: z.literal(false),
      }).passthrough(),
    }).passthrough(),
    mutation: z.object({
      changed: z.boolean(),
      idempotent_replay: z.boolean(),
      history_entry_id: z.number().int().positive().nullable(),
      next_offer_created: z.boolean(),
    }).passthrough(),
  }).passthrough(),
}).passthrough();

export type CanonicalEvent = z.infer<typeof eventSchema>;
export type CanonicalEventsResponse = z.infer<typeof eventsResponseSchema>;
export type EventRelationship = z.infer<typeof eventRelationshipSchema>;
export type EventMetrics = z.infer<typeof eventMetricsSchema>;
export type EventRegistrationResponse = z.infer<typeof eventRegistrationResponseSchema>;
export type EventAttendee = z.infer<typeof eventRosterMemberSchema>;
export type EventAttendeesResponse = z.infer<typeof eventRosterResponseSchema>;
export type EventAttendanceRosterPerson = z.infer<typeof eventAttendanceRosterPersonSchema>;
export type EventAttendanceRosterResponse = z.infer<typeof eventAttendanceRosterResponseSchema>;
export type EventAttendanceAction = 'check_in' | 'check_out' | 'no_show';
export type EventSeries = z.infer<typeof eventSeriesSchema>;
export type EventCategory = z.infer<typeof eventCategorySchema>;
export type EventAgenda = z.infer<typeof eventAgendaSchema>;
export type EventAgendaSession = z.infer<typeof eventAgendaSessionSchema>;
export type EventAgendaSpeaker = z.infer<typeof eventAgendaSpeakerSchema>;
export type EventAgendaResource = z.infer<typeof eventAgendaResourceSchema>;

/**
 * Compatibility projection used by the existing Group detail tab. New Events
 * surfaces must consume CanonicalEvent directly.
 */
export interface Event {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  is_online: boolean;
  start_date: string | null;
  end_date: string | null;
  timezone: string;
  all_day: boolean;
  cover_image: string | null;
  max_attendees: number | null;
  spots_left: number | null;
  is_full: boolean;
  status: string;
  organizer: { id: number; name: string | null; avatar: string | null };
  category: { id: number | null; name: string | null; color: string | null } | null;
  rsvp_counts: { going: number; interested: number };
  attendees_count: number;
  waitlist_count: number;
  user_rsvp: 'going' | 'interested' | null;
}

export interface EventsResponse {
  data: Event[];
  meta: CanonicalEventsResponse['meta'];
}

function stableEndpoint(endpoint: string): string {
  return endpoint.split('?')[0].replace(/\/\d+(?=\/|$)/g, '/{id}');
}

function reportContractDrift(
  endpoint: string,
  error: z.ZodError,
  contractVersion: number = EVENTS_CONTRACT_VERSION,
): void {
  Sentry.captureMessage('Events contract drift', {
    level: 'warning',
    tags: {
      module: 'events',
      contract_version: String(contractVersion),
      endpoint: stableEndpoint(endpoint),
    },
    extra: {
      issues: error.issues.map((issue) => ({
        path: issue.path.map(String).join('.'),
        code: issue.code,
      })),
    },
  });
}

function parseContract<T>(
  endpoint: string,
  schema: z.ZodType<T>,
  value: unknown,
  contractVersion: number = EVENTS_CONTRACT_VERSION,
): T {
  const parsed = schema.safeParse(value);
  if (parsed.success) return parsed.data;

  reportContractDrift(endpoint, parsed.error, contractVersion);
  throw new ApiResponseError(422, 'EVENTS_CONTRACT_DRIFT');
}

export interface CreateEventPayload {
  title: string;
  description: string;
  start_time: string;
  end_time?: string | null;
  timezone: string;
  all_day: boolean;
  group_id?: number | null;
  location?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  category_id?: number | null;
  category_name?: string | null;
  series_id?: number | null;
  is_online?: boolean;
  online_link?: string | null;
  video_url?: string | null;
  max_attendees?: number | null;
  federated_visibility?: 'none' | 'listed' | 'bookable';
}

export type UpdateEventPayload = CreateEventPayload;

export function getEventOnlineLink(event: Pick<CanonicalEvent, 'online_access'>): string | null {
  if (event.online_access.reveal_state !== 'available') return null;
  return event.online_access.join_url ?? event.online_access.video_url ?? null;
}

export type EventStepFreeFilter = 'yes' | 'no' | 'unknown';

export interface EventDiscoveryFilters {
  groupId?: number | null;
  stepFree?: EventStepFreeFilter | null;
}

export async function getCanonicalEvents(
  when: 'upcoming' | 'past' | 'all' = 'upcoming',
  cursor?: string | null,
  perPage = 20,
  filters: EventDiscoveryFilters = {},
): Promise<CanonicalEventsResponse> {
  const endpoint = `${API_V2}/events`;
  const params: Record<string, string> = { when, per_page: String(perPage) };
  if (cursor) params.cursor = cursor;
  if (filters.groupId) params.group_id = String(filters.groupId);
  if (filters.stepFree) params.step_free = filters.stepFree;
  const response = await api.get<unknown>(endpoint, params, eventRequestOptions);
  return parseContract(endpoint, eventsResponseSchema, response);
}

export async function getEvents(
  when: 'upcoming' | 'past' | 'all' = 'upcoming',
  cursor?: string | null,
  perPage = 20,
  filters: EventDiscoveryFilters = {},
): Promise<EventsResponse> {
  const response = await getCanonicalEvents(when, cursor, perPage, filters);
  return {
    ...response,
    data: response.data.map((event) => ({
      id: event.id,
      title: event.title,
      description: event.description,
      location: event.location.label,
      is_online: event.location.mode !== 'in_person',
      start_date: event.schedule.start_at,
      end_date: event.schedule.end_at,
      timezone: event.schedule.timezone,
      all_day: event.schedule.all_day,
      cover_image: event.primary_image?.url ?? null,
      max_attendees: event.relationship.capacity.limit,
      spots_left: event.relationship.capacity.remaining,
      is_full: event.relationship.capacity.is_full,
      status: event.schedule.state,
      organizer: {
        id: event.organizer.id,
        name: event.organizer.display_name,
        avatar: event.organizer.avatar_url,
      },
      category: event.category ? {
        id: event.category.id,
        name: event.category.name,
        color: event.category.colour,
      } : null,
      rsvp_counts: {
        going: event.metrics.confirmed_count,
        interested: event.metrics.interested_count,
      },
      attendees_count: event.metrics.confirmed_count,
      waitlist_count: event.metrics.waitlist_count,
      user_rsvp: event.relationship.registration.state === 'confirmed'
        ? 'going'
        : event.relationship.engagement.state === 'interested'
          ? 'interested'
          : null,
    })),
  };
}

const eventReminderPreferencesSchema = z.object({
  revision: z.number().int().nonnegative(),
  overrides: z.object({
    email_enabled: z.boolean().nullable(),
    in_app_enabled: z.boolean().nullable(),
    web_push_enabled: z.boolean().nullable(),
    fcm_enabled: z.boolean().nullable(),
    realtime_enabled: z.boolean().nullable(),
    cadence: z.enum(['instant', 'daily', 'monthly', 'off']).nullable(),
    reminders_enabled: z.boolean().nullable(),
  }),
  rules: z.array(z.object({
    id: z.number().int().positive().optional(),
    offset_minutes: z.number().int().positive(),
    enabled: z.boolean(),
    rule_version: z.number().int().nonnegative().optional(),
    email_enabled: z.boolean().nullable(),
    in_app_enabled: z.boolean().nullable(),
    web_push_enabled: z.boolean().nullable(),
    fcm_enabled: z.boolean().nullable(),
    realtime_enabled: z.boolean().nullable(),
  })),
  resolved: z.object({
    channels: z.object({
      email: z.boolean(),
      in_app: z.boolean(),
      web_push: z.boolean(),
      fcm: z.boolean(),
      realtime: z.boolean(),
    }),
    channel_sources: z.record(z.string(), z.string()),
    cadence: z.enum(['instant', 'daily', 'monthly', 'off']),
    cadence_source: z.string(),
    reminders_enabled: z.boolean(),
    reminders_source: z.string(),
  }),
  limits: z.object({
    minimum_offset_minutes: z.number().int().positive(),
    maximum_offset_minutes: z.number().int().positive(),
    maximum_rules: z.number().int().positive(),
    default_offsets_minutes: z.array(z.number().int().positive()),
  }),
  capabilities: z.object({
    independent_channels: z.boolean(),
    diagnostics_supported: z.boolean(),
  }),
});

const eventReminderPreferencesEnvelopeSchema = z.object({ data: eventReminderPreferencesSchema }).passthrough();
export type EventReminderPreferences = z.infer<typeof eventReminderPreferencesSchema>;
export type EventReminderRule = EventReminderPreferences['rules'][number];

export interface CheckInEventAttendeeResponse {
  data: {
    checked_in: true;
    attendee_id: number;
    event_id: number;
    credit_status?: string;
    hours_credited?: number | null;
  };
}

export interface EventWaitlistEntry {
  id: number;
  user_id?: number | null;
  name: string;
  first_name?: string | null;
  last_name?: string | null;
  avatar?: string | null;
  avatar_url?: string | null;
  position: number;
  status?: string | null;
}

export interface EventWaitlistResponse {
  data: EventWaitlistEntry[];
  meta: { has_more: boolean; user_position: number | null };
}

export interface JoinEventWaitlistResponse {
  data: { waitlisted: true; position: number | null };
}

export type EventWaitlistOfferAcceptanceResponse = z.infer<typeof eventWaitlistOfferAcceptanceEnvelopeSchema>;

export interface EventPollOption {
  id: number;
  text?: string | null;
  label?: string | null;
  vote_count?: number;
  percentage?: number;
}

export interface EventPoll {
  id: number;
  question: string;
  description?: string | null;
  status?: 'open' | 'closed' | string;
  is_active?: boolean;
  total_votes?: number;
  has_voted?: boolean;
  voted_option_id?: number | null;
  user_vote_option_id?: number | null;
  options: EventPollOption[];
}

export interface EventPollsResponse {
  data: EventPoll[];
  meta?: { per_page?: number; has_more?: boolean; cursor?: string | null };
}

export interface UpdateEventReminderInput {
  expected_revision: number;
  overrides: EventReminderPreferences['overrides'];
  rules: Array<Pick<EventReminderRule, 'offset_minutes' | 'enabled' | 'email_enabled' | 'in_app_enabled' | 'web_push_enabled' | 'fcm_enabled' | 'realtime_enabled'>>;
}

export async function rsvpEvent(
  eventId: number,
  status: 'going' | 'interested' | 'not_going',
): Promise<{ data: EventRegistrationResponse }> {
  const endpoint = `${API_V2}/events/${eventId}/rsvp`;
  const response = await api.post<unknown>(endpoint, { status }, eventRequestOptions);
  return parseContract(endpoint, eventRegistrationEnvelopeSchema, response);
}

export function removeRsvp(eventId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/events/${eventId}/rsvp`, eventRequestOptions);
}

export async function getEventReminders(eventId: number): Promise<{ data: EventReminderPreferences }> {
  const endpoint = `${API_V2}/events/${eventId}/reminders`;
  return parseContract(
    endpoint,
    eventReminderPreferencesEnvelopeSchema,
    await api.get<unknown>(endpoint, undefined, eventRequestOptions),
  );
}

export async function updateEventReminders(
  eventId: number,
  update: UpdateEventReminderInput,
): Promise<{ data: EventReminderPreferences }> {
  const endpoint = `${API_V2}/events/${eventId}/reminders`;
  return parseContract(
    endpoint,
    eventReminderPreferencesEnvelopeSchema,
    await api.put<unknown>(endpoint, update, eventRequestOptions),
  );
}

export async function deleteEventReminders(
  eventId: number,
  expectedRevision: number,
): Promise<{ data: EventReminderPreferences }> {
  const endpoint = `${API_V2}/events/${eventId}/reminders?expected_revision=${encodeURIComponent(String(expectedRevision))}`;
  return parseContract(
    endpoint,
    eventReminderPreferencesEnvelopeSchema,
    await api.delete<unknown>(endpoint, eventRequestOptions),
  );
}

export async function getEventAttendees(
  eventId: number,
  options: { perPage?: number; status?: 'going' | 'interested' | 'invited' | 'attended' | 'all'; cursor?: string | null } = {},
): Promise<EventAttendeesResponse> {
  const endpoint = `${API_V2}/events/${eventId}/attendees`;
  const params: Record<string, string> = {
    per_page: String(options.perPage ?? 50),
    status: options.status ?? 'all',
  };
  if (options.cursor) params.cursor = options.cursor;
  const response = await api.get<unknown>(endpoint, params, eventRequestOptions);
  return parseContract(endpoint, eventRosterResponseSchema, response);
}

export async function getEventAttendanceRoster(
  eventId: number,
  options: {
    page?: number;
    search?: string;
    attendanceState?: 'not_checked_in' | 'checked_in' | 'checked_out' | 'attended' | 'no_show' | null;
  } = {},
): Promise<EventAttendanceRosterResponse> {
  const endpoint = `${API_V2}/events/${eventId}/people`;
  const params: Record<string, string> = {
    page: String(options.page ?? 1),
    per_page: '25',
    sort: 'name',
    direction: 'asc',
  };
  const search = options.search?.trim();
  if (search) params.search = search;
  if (options.attendanceState) params.attendance_state = options.attendanceState;
  const response = await api.get<unknown>(endpoint, params, eventRequestOptions);
  return parseContract(endpoint, eventAttendanceRosterResponseSchema, response);
}

export async function transitionEventAttendance(
  eventId: number,
  userId: number,
  input: {
    action: EventAttendanceAction;
    expectedVersion: number;
    idempotencyKey: string;
  },
): Promise<z.infer<typeof eventAttendanceMutationEnvelopeSchema>> {
  const endpoint = `${API_V2}/events/${eventId}/people/${userId}/attendance`;
  const payload = {
    action: input.action,
    expected_version: input.expectedVersion,
    idempotency_key: input.idempotencyKey,
  };
  const response = await api.post<unknown>(endpoint, payload, {
    headers: {
      ...eventRequestOptions.headers,
      'Idempotency-Key': input.idempotencyKey,
    },
  });
  return parseContract(endpoint, eventAttendanceMutationEnvelopeSchema, response);
}

export async function checkInEventAttendee(eventId: number, attendeeId: number): Promise<CheckInEventAttendeeResponse> {
  const endpoint = `${API_V2}/events/${eventId}/attendees/${attendeeId}/check-in`;
  const response = await api.post<unknown>(endpoint, undefined, eventRequestOptions);
  return parseContract(endpoint, eventCheckInResponseSchema, response);
}

export function getEventWaitlist(eventId: number, perPage = 20): Promise<EventWaitlistResponse> {
  return api.get<EventWaitlistResponse>(
    `${API_V2}/events/${eventId}/waitlist`,
    { per_page: String(perPage) },
    eventRequestOptions,
  );
}

export async function joinEventWaitlist(eventId: number): Promise<JoinEventWaitlistResponse> {
  const endpoint = `${API_V2}/events/${eventId}/waitlist`;
  const response = await api.post<unknown>(endpoint, undefined, eventRequestOptions);
  return parseContract(endpoint, joinEventWaitlistResponseSchema, response);
}

export function leaveEventWaitlist(eventId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/events/${eventId}/waitlist`, eventRequestOptions);
}

export async function acceptEventWaitlistOffer(
  eventId: number,
  idempotencyKey: string,
): Promise<EventWaitlistOfferAcceptanceResponse> {
  const endpoint = `${API_V2}/events/${eventId}/registration/waitlist/accept`;
  const response = await api.post<unknown>(endpoint, {}, {
    headers: {
      ...eventRequestOptions.headers,
      'Idempotency-Key': idempotencyKey,
    },
  });
  return parseContract(endpoint, eventWaitlistOfferAcceptanceEnvelopeSchema, response);
}

export function getEventPolls(eventId: number): Promise<EventPollsResponse> {
  return api.get<EventPollsResponse>(`${API_V2}/polls`, {
    event_id: String(eventId),
    status: 'all',
    per_page: '50',
  });
}

export function voteEventPoll(pollId: number, optionId: number): Promise<{ data: EventPoll }> {
  return api.post<{ data: EventPoll }>(`${API_V2}/polls/${pollId}/vote`, { option_id: optionId });
}

export async function getEvent(eventId: number): Promise<{ data: CanonicalEvent }> {
  const endpoint = `${API_V2}/events/${eventId}`;
  const response = await api.get<unknown>(endpoint, undefined, eventRequestOptions);
  return parseContract(endpoint, eventEnvelopeSchema, response);
}

export async function getEventAgenda(eventId: number): Promise<{ data: EventAgenda; meta: { base_url: string } }> {
  const endpoint = `${API_V2}/events/${eventId}/agenda`;
  const response = await api.get<unknown>(endpoint, undefined, eventRequestOptions);
  return parseContract(
    endpoint,
    eventAgendaEnvelopeSchema,
    response,
    EVENT_AGENDA_CONTRACT_VERSION,
  );
}

export async function registerEventAgendaSession(
  eventId: number,
  sessionId: number,
  expectedVersion: number,
  idempotencyKey: string,
): Promise<z.infer<typeof eventAgendaRegistrationMutationEnvelopeSchema>> {
  const endpoint = `${API_V2}/events/${eventId}/agenda/sessions/${sessionId}/registration`;
  const response = await api.post<unknown>(endpoint, { expected_version: expectedVersion }, {
    headers: {
      ...eventRequestOptions.headers,
      'Idempotency-Key': idempotencyKey,
    },
  });
  return parseContract(endpoint, eventAgendaRegistrationMutationEnvelopeSchema, response);
}

export async function withdrawEventAgendaSession(
  eventId: number,
  sessionId: number,
  expectedVersion: number,
  idempotencyKey: string,
): Promise<z.infer<typeof eventAgendaRegistrationMutationEnvelopeSchema>> {
  const endpoint = `${API_V2}/events/${eventId}/agenda/sessions/${sessionId}/registration/withdraw`;
  const response = await api.post<unknown>(endpoint, { expected_version: expectedVersion }, {
    headers: {
      ...eventRequestOptions.headers,
      'Idempotency-Key': idempotencyKey,
    },
  });
  return parseContract(endpoint, eventAgendaRegistrationMutationEnvelopeSchema, response);
}

export async function getEventSeries(seriesId: number): Promise<{ data: EventSeries }> {
  const endpoint = `${API_V2}/events/series/${seriesId}`;
  const response = await api.get<unknown>(endpoint, undefined, eventRequestOptions);
  return parseContract(endpoint, eventSeriesEnvelopeSchema, response);
}

export async function getEventCategories(): Promise<{ data: EventCategory[] }> {
  const endpoint = `${API_V2}/categories`;
  const response = await api.get<unknown>(endpoint, { type: 'event' }, eventRequestOptions);
  return parseContract(endpoint, eventCategoriesResponseSchema, response);
}

export async function createEvent(payload: CreateEventPayload): Promise<{ data: CanonicalEvent }> {
  const endpoint = `${API_V2}/events`;
  const response = await api.post<unknown>(endpoint, payload, eventRequestOptions);
  return parseContract(endpoint, eventEnvelopeSchema, response);
}

export async function updateEvent(id: number, payload: UpdateEventPayload): Promise<{ data: CanonicalEvent }> {
  const endpoint = `${API_V2}/events/${id}`;
  const response = await api.put<unknown>(endpoint, payload, eventRequestOptions);
  return parseContract(endpoint, eventEnvelopeSchema, response);
}

type UploadEventImageResponse = {
  data?: { image_url?: string | null } | null;
  image_url?: string | null;
};

function getUploadFilename(uri: string): string {
  const cleanUri = uri.split('?')[0] ?? uri;
  const lastSegment = cleanUri.split('/').pop();
  return lastSegment && lastSegment.includes('.') ? lastSegment : 'event.jpg';
}

function getMimeType(filename: string, fallback?: string | null): string {
  if (fallback?.startsWith('image/')) return fallback;
  const extension = filename.split('.').pop()?.toLowerCase();
  if (extension === 'png') return 'image/png';
  if (extension === 'webp') return 'image/webp';
  if (extension === 'gif') return 'image/gif';
  return 'image/jpeg';
}

async function appendEventImageFile(formData: FormData, uri: string): Promise<void> {
  const filename = getUploadFilename(uri);

  if (Platform.OS === 'web') {
    const response = await fetch(uri);
    const blob = await response.blob();
    const type = getMimeType(filename, blob.type);
    if (typeof File !== 'undefined') {
      formData.append('image', new File([blob], filename, { type }));
      return;
    }
    formData.append('image', blob, filename);
    return;
  }

  const type = getMimeType(filename);
  formData.append('image', { uri, name: filename, type } as unknown as Blob);
}

export async function uploadEventImage(id: number, uri: string): Promise<{ data: { image_url: string } }> {
  const formData = new FormData();
  await appendEventImageFile(formData, uri);

  const response = await api.upload<UploadEventImageResponse>(
    `${API_V2}/events/${id}/image`,
    formData,
    eventRequestOptions,
  );
  const imageUrl = response.data?.image_url ?? response.image_url ?? null;
  if (!imageUrl) throw new ApiResponseError(422, 'EVENT_IMAGE_RESPONSE_INVALID');
  return { data: { image_url: imageUrl } };
}
