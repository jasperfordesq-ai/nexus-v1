// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import { Platform } from 'react-native';

export interface EventOrganizer {
  id: number;
  name: string;
  avatar: string | null;
}

export interface EventCategory {
  id: number;
  name: string;
  color: string | null;
}

export interface RsvpCounts {
  going: number;
  interested: number;
}

export interface Event {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  latitude?: number | null;
  longitude?: number | null;
  is_online: boolean;
  online_link?: string | null;
  online_url: string | null;
  video_url?: string | null;
  start_date: string;
  end_date: string | null;
  cover_image: string | null;
  max_attendees: number | null;
  spots_left: number | null;
  is_full: boolean;
  status: string;
  federated_visibility?: 'none' | 'listed' | 'bookable' | string | null;
  organizer: EventOrganizer;
  category: EventCategory | null;
  rsvp_counts: RsvpCounts;
  attendees_count: number;
  waitlist_count?: number | null;
  /** Present when the request was authenticated. */
  user_rsvp: 'going' | 'interested' | 'not_going' | null;
  user_waitlist_position?: number | null;
}

export interface EventsResponse {
  data: Event[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
  };
}

export interface CreateEventPayload {
  title: string;
  description: string;
  start_time: string;
  end_time?: string | null;
  group_id?: number | null;
  location?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  category_name?: string | null;
  is_online?: boolean;
  online_link?: string | null;
  max_attendees?: number | null;
  federated_visibility?: 'none' | 'listed' | 'bookable';
}

export type UpdateEventPayload = CreateEventPayload;

export function getEventOnlineLink(event: Pick<Event, 'online_link' | 'online_url' | 'video_url'>): string | null {
  return event.online_link ?? event.online_url ?? event.video_url ?? null;
}

/**
 * GET /api/v2/events — list upcoming events for the current tenant.
 * Supports cursor-based pagination.
 */
export function getEvents(
  when: 'upcoming' | 'past' | 'all' = 'upcoming',
  cursor?: string | null,
  perPage = 20,
  filters: { groupId?: number | null } = {},
): Promise<EventsResponse> {
  const params: Record<string, string> = {
    when,
    per_page: String(perPage),
  };
  if (cursor) params['cursor'] = cursor;
  if (filters.groupId) params['group_id'] = String(filters.groupId);
  return api.get<EventsResponse>(`${API_V2}/events`, params);
}

export interface RsvpResponse {
  rsvp: 'going' | 'interested' | 'not_going';
  rsvp_counts: RsvpCounts;
}

export interface EventReminder {
  remind_before_minutes: number;
  reminder_type: 'platform' | 'email' | 'both';
  status: 'pending' | 'sent' | 'cancelled' | string;
  scheduled_for: string;
}

export interface EventAttendee {
  id: number;
  name: string;
  first_name?: string | null;
  last_name?: string | null;
  avatar?: string | null;
  avatar_url?: string | null;
  rsvp_status?: 'going' | 'interested' | 'invited' | 'attended' | string | null;
  status?: string | null;
  checked_in?: boolean;
  rsvp_at?: string | null;
}

export interface EventAttendeesResponse {
  data: EventAttendee[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
  };
}

export interface CheckInEventAttendeeResponse {
  data: {
    checked_in: boolean;
    attendee_id: number;
    event_id: number;
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
  meta: {
    has_more: boolean;
    user_position: number | null;
  };
}

export interface JoinEventWaitlistResponse {
  data: {
    waitlisted: boolean;
    position: number | null;
  };
}

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
  meta?: {
    per_page?: number;
    has_more?: boolean;
    cursor?: string | null;
  };
}

export interface UpdateEventReminderInput {
  minutes: 60 | 1440 | 10080;
  type?: 'platform' | 'email' | 'both';
}

/**
 * POST /api/v2/events/{id}/rsvp — set or update RSVP status.
 * status: 'going' | 'interested' | 'not_going'
 */
export function rsvpEvent(
  eventId: number,
  status: 'going' | 'interested' | 'not_going',
): Promise<{ data: RsvpResponse }> {
  return api.post<{ data: RsvpResponse }>(`${API_V2}/events/${eventId}/rsvp`, { status });
}

/**
 * DELETE /api/v2/events/{id}/rsvp — remove RSVP entirely.
 */
export function removeRsvp(eventId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/events/${eventId}/rsvp`);
}

/**
 * GET /api/v2/events/{id}/reminders — get reminders for the authenticated user.
 */
export function getEventReminders(eventId: number): Promise<{ data: EventReminder[] }> {
  return api.get<{ data: EventReminder[] }>(`${API_V2}/events/${eventId}/reminders`);
}

/**
 * PUT /api/v2/events/{id}/reminders — replace reminders for the authenticated user.
 */
export function updateEventReminders(
  eventId: number,
  reminders: UpdateEventReminderInput[],
): Promise<{ data: EventReminder[] }> {
  return api.put<{ data: EventReminder[] }>(`${API_V2}/events/${eventId}/reminders`, { reminders });
}

/**
 * GET /api/v2/events/{id}/attendees — list event RSVPs for organizer workflows.
 */
export function getEventAttendees(
  eventId: number,
  options: { perPage?: number; status?: 'going' | 'interested' | 'invited' | 'attended' | 'all'; cursor?: string | null } = {},
): Promise<EventAttendeesResponse> {
  const params: Record<string, string> = {
    per_page: String(options.perPage ?? 50),
    status: options.status ?? 'all',
  };
  if (options.cursor) params['cursor'] = options.cursor;
  return api.get<EventAttendeesResponse>(`${API_V2}/events/${eventId}/attendees`, params);
}

/**
 * POST /api/v2/events/{id}/attendees/{attendeeId}/check-in — organizer attendee check-in.
 */
export function checkInEventAttendee(eventId: number, attendeeId: number): Promise<CheckInEventAttendeeResponse> {
  return api.post<CheckInEventAttendeeResponse>(`${API_V2}/events/${eventId}/attendees/${attendeeId}/check-in`);
}

/**
 * GET /api/v2/events/{id}/waitlist — load waitlist state for the authenticated user.
 */
export function getEventWaitlist(eventId: number, perPage = 20): Promise<EventWaitlistResponse> {
  return api.get<EventWaitlistResponse>(`${API_V2}/events/${eventId}/waitlist`, { per_page: String(perPage) });
}

/**
 * POST /api/v2/events/{id}/waitlist — join an event waitlist.
 */
export function joinEventWaitlist(eventId: number): Promise<JoinEventWaitlistResponse> {
  return api.post<JoinEventWaitlistResponse>(`${API_V2}/events/${eventId}/waitlist`);
}

/**
 * DELETE /api/v2/events/{id}/waitlist — leave an event waitlist.
 */
export function leaveEventWaitlist(eventId: number): Promise<void> {
  return api.delete<void>(`${API_V2}/events/${eventId}/waitlist`);
}

/**
 * GET /api/v2/polls?event_id={id} — load polls linked to an event.
 */
export function getEventPolls(eventId: number): Promise<EventPollsResponse> {
  return api.get<EventPollsResponse>(`${API_V2}/polls`, {
    event_id: String(eventId),
    status: 'all',
    per_page: '50',
  });
}

/**
 * POST /api/v2/polls/{id}/vote — vote on an event-linked poll.
 */
export function voteEventPoll(pollId: number, optionId: number): Promise<{ data: EventPoll }> {
  return api.post<{ data: EventPoll }>(`${API_V2}/polls/${pollId}/vote`, { option_id: optionId });
}

/**
 * GET /api/v2/events/{id} — get a single event by ID.
 */
export function getEvent(eventId: number): Promise<{ data: Event }> {
  return api.get<{ data: Event }>(`${API_V2}/events/${eventId}`);
}

/**
 * POST /api/v2/events — create a community event.
 */
export function createEvent(payload: CreateEventPayload): Promise<{ data: Event }> {
  return api.post<{ data: Event }>(`${API_V2}/events`, payload);
}

/**
 * PUT /api/v2/events/{id} — update a community event.
 */
export function updateEvent(id: number, payload: UpdateEventPayload): Promise<{ data: Event }> {
  return api.put<{ data: Event }>(`${API_V2}/events/${id}`, payload);
}

type UploadEventImageResponse = {
  data?: { image_url?: string | null } | null;
  image_url?: string | null;
  message?: string;
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

/**
 * POST /api/v2/events/{id}/image — upload or replace an event cover image.
 */
export async function uploadEventImage(id: number, uri: string): Promise<{ data: { image_url: string } }> {
  const formData = new FormData();
  await appendEventImageFile(formData, uri);

  const response = await api.upload<UploadEventImageResponse>(`${API_V2}/events/${id}/image`, formData);
  const imageUrl = response.data?.image_url ?? response.image_url ?? null;
  if (!imageUrl) {
    throw new Error(response.message ?? 'Event image upload did not return an image URL.');
  }

  return { data: { image_url: imageUrl } };
}
