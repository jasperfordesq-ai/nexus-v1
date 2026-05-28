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

export interface CreateEventPayload {
  title: string;
  description: string;
  start_time: string;
  end_time?: string | null;
  group_id?: number | null;
  location?: string | null;
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
