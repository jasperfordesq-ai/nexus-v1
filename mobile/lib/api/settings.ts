// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

type ApiEnvelope<T> = { success?: boolean; data?: T } | T;

export type DataExportFormat = 'json' | 'zip';

export interface DataExportHistoryRow {
  id: number;
  format: string;
  requested_at: string | null;
  completed_at: string | null;
  file_size_bytes: number | null;
}

export interface BlockedUser {
  block_id: number;
  user_id: number;
  name: string;
  first_name: string | null;
  last_name: string | null;
  avatar_url: string | null;
  reason: string | null;
  blocked_at: string | null;
}

export interface UserPreferences {
  feed?: {
    prefers_chronological?: boolean;
  };
  translation?: {
    auto_translate_ugc?: boolean;
    auto_translate_target_locale?: string | null;
  };
}

function unwrap<T>(response: ApiEnvelope<T>, fallback: T): T {
  if (response && typeof response === 'object' && 'data' in response) {
    return (response as { data?: T }).data ?? fallback;
  }
  return (response as T) ?? fallback;
}

export async function getDataExportHistory(): Promise<DataExportHistoryRow[]> {
  const response = await api.get<ApiEnvelope<{ exports?: DataExportHistoryRow[] }>>(`${API_V2}/me/data-export/history`);
  return unwrap(response, {}).exports ?? [];
}

export function requestDataExport(format: DataExportFormat): Promise<unknown> {
  return api.post<unknown>(`${API_V2}/me/data-export`, { format });
}

export async function getBlockedUsers(): Promise<BlockedUser[]> {
  const response = await api.get<ApiEnvelope<BlockedUser[]>>(`${API_V2}/users/blocked`);
  return unwrap(response, []);
}

export function unblockUser(userId: number): Promise<unknown> {
  return api.delete<unknown>(`${API_V2}/users/${userId}/block`);
}

export async function getUserPreferences(): Promise<UserPreferences> {
  const response = await api.get<ApiEnvelope<UserPreferences>>(`${API_V2}/users/me/preferences`);
  return unwrap(response, {});
}

export function saveUserPreferences(preferences: UserPreferences): Promise<unknown> {
  return api.put<unknown>(`${API_V2}/users/me/preferences`, preferences);
}
