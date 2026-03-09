// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface NotificationActor {
  id: number;
  name: string | null;
  avatar_url: string | null;
}

export interface Notification {
  id: number;
  type: string;
  category: 'message' | 'transaction' | 'social' | 'system' | 'other';
  title: string | null;
  /** Primary display text */
  message: string;
  body: string;
  is_read: boolean;
  read_at: string | null;
  actor: NotificationActor | null;
  /** Deep-link URL (web format — e.g. /exchanges/123) */
  link: string | null;
  created_at: string;
}

export interface NotificationListResponse {
  data: Notification[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
    base_url?: string;
  };
}

export interface NotificationCounts {
  total: number;
  messages: number;
  transactions: number;
  social: number;
  system: number;
}

/** GET /api/v2/notifications — paginated notification list */
export function getNotifications(cursor?: string | null): Promise<NotificationListResponse> {
  const params: Record<string, string> = { per_page: '25' };
  if (cursor) params.cursor = cursor;
  return api.get<NotificationListResponse>(`${API_V2}/notifications`, params);
}

/** GET /api/v2/notifications/counts — unread counts by category */
export function getNotificationCounts(): Promise<{ data: NotificationCounts }> {
  return api.get<{ data: NotificationCounts }>(`${API_V2}/notifications/counts`);
}

/** POST /api/v2/notifications/{id}/read */
export function markRead(id: number): Promise<void> {
  return api.post<void>(`${API_V2}/notifications/${id}/read`);
}

/** POST /api/v2/notifications/read-all */
export function markAllRead(): Promise<void> {
  return api.post<void>(`${API_V2}/notifications/read-all`);
}
