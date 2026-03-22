// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface GroupMember {
  id: number;
  name: string;
  avatar_url: string | null;
}

export interface Group {
  id: number;
  name: string;
  description: string | null;
  visibility: 'public' | 'private';
  cover_image: string | null;
  is_featured: boolean;
  member_count: number;
  posts_count: number;
  is_member: boolean;
  created_at: string;
  recent_members: GroupMember[];
}

export interface GroupDetail extends Group {
  long_description?: string | null;
  admin: GroupMember;
  tags?: string[];
}

export interface GroupsResponse {
  data: Group[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

/**
 * GET /api/v2/groups — list groups for the current tenant (cursor-based pagination).
 * Optionally filter by search query or visibility.
 */
export function getGroups(
  cursor: string | null,
  params?: { search?: string; visibility?: string },
): Promise<GroupsResponse> {
  const query: Record<string, string> = { per_page: '20' };
  if (cursor) query['cursor'] = cursor;
  if (params?.search) query['search'] = params.search;
  if (params?.visibility) query['visibility'] = params.visibility;
  return api.get<GroupsResponse>(`${API_V2}/groups`, query);
}

/**
 * GET /api/v2/groups/{id} — get a single group by ID.
 */
export function getGroup(id: number): Promise<{ data: GroupDetail }> {
  return api.get<{ data: GroupDetail }>(`${API_V2}/groups/${id}`);
}

/**
 * POST /api/v2/groups/{id}/join — join a group.
 */
export function joinGroup(id: number): Promise<{ message: string }> {
  return api.post<{ message: string }>(`${API_V2}/groups/${id}/join`, {});
}

/**
 * DELETE /api/v2/groups/{id}/leave — leave a group.
 */
export function leaveGroup(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/groups/${id}/leave`);
}
