// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

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
  is_online: boolean;
  online_url: string | null;
  start_date: string;
  end_date: string | null;
  cover_image: string | null;
  max_attendees: number | null;
  spots_left: number | null;
  is_full: boolean;
  status: string;
  organizer: EventOrganizer;
  category: EventCategory | null;
  rsvp_counts: RsvpCounts;
  attendees_count: number;
  /** Present when the request was authenticated. */
  user_rsvp: 'going' | 'interested' | 'not_going' | null;
}

export interface EventsResponse {
  data: Event[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
  };
}

/**
 * GET /api/v2/events — list upcoming events for the current tenant.
 * Supports cursor-based pagination.
 */
export function getEvents(
  when: 'upcoming' | 'past' | 'all' = 'upcoming',
  cursor?: string | null,
  perPage = 20,
): Promise<EventsResponse> {
  const params: Record<string, string> = {
    when,
    per_page: String(perPage),
  };
  if (cursor) params['cursor'] = cursor;
  return api.get<EventsResponse>(`${API_V2}/events`, params);
}

export interface RsvpResponse {
  rsvp: 'going' | 'interested' | 'not_going';
  rsvp_counts: RsvpCounts;
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
 * GET /api/v2/events/{id} — get a single event by ID.
 */
export function getEvent(eventId: number): Promise<{ data: Event }> {
  return api.get<{ data: Event }>(`${API_V2}/events/${eventId}`);
}
