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
  image_url?: string | null;
  is_featured: boolean;
  member_count: number;
  posts_count: number;
  is_member: boolean;
  owner_id?: number | null;
  location?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  federated_visibility?: 'none' | 'listed' | 'joinable' | string | null;
  created_at: string;
  recent_members: GroupMember[];
}

export interface GroupDetail extends Group {
  long_description?: string | null;
  admin: GroupMember;
  creator?: GroupMember | null;
  tags?: string[];
  viewer_membership?: {
    status?: string | null;
    role?: string | null;
    is_admin?: boolean;
  } | null;
}

export interface GroupMemberListItem extends GroupMember {
  role: 'owner' | 'admin' | 'member' | string;
  joined_at: string | null;
}

export interface GroupDiscussion {
  id: number;
  title: string;
  author: GroupMember;
  reply_count: number;
  is_pinned: boolean;
  created_at: string | null;
  last_reply_at: string | null;
}

export interface GroupAnnouncement {
  id: number;
  title: string;
  content: string;
  is_pinned: boolean;
  priority: number;
  is_expired: boolean;
  author: GroupMember;
  created_at: string | null;
  updated_at: string | null;
  expires_at: string | null;
}

export interface GroupsResponse {
  data: Group[];
  meta: {
    has_more: boolean;
    cursor: string | null;
  };
}

export interface GroupCollectionResponse<T> {
  data: T[];
  meta: {
    has_more: boolean;
    cursor: string | null;
    per_page?: number;
  };
}

export interface GroupAnnouncementsResponse {
  data: {
    items: GroupAnnouncement[];
    cursor: string | null;
    has_more: boolean;
  };
}

export interface CreateGroupPayload {
  name: string;
  description?: string;
  visibility?: 'public' | 'private';
  location?: string | null;
  latitude?: number | null;
  longitude?: number | null;
  federated_visibility?: 'none' | 'listed' | 'joinable';
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
  if (params?.search) query['q'] = params.search;
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
 * POST /api/v2/groups — create a community group.
 */
export function createGroup(payload: CreateGroupPayload): Promise<{ data: GroupDetail }> {
  return api.post<{ data: GroupDetail }>(`${API_V2}/groups`, payload);
}

export function updateGroup(id: number, payload: CreateGroupPayload): Promise<{ data: GroupDetail }> {
  return api.put<{ data: GroupDetail }>(`${API_V2}/groups/${id}`, payload);
}

/**
 * GET /api/v2/groups/{id}/members — list active group members.
 */
export function getGroupMembers(
  id: number,
  cursor: string | null = null,
): Promise<GroupCollectionResponse<GroupMemberListItem>> {
  const query: Record<string, string> = { per_page: '20' };
  if (cursor) query['cursor'] = cursor;
  return api.get<GroupCollectionResponse<GroupMemberListItem>>(`${API_V2}/groups/${id}/members`, query);
}

/**
 * GET /api/v2/groups/{id}/discussions — list member-only discussions.
 */
export function getGroupDiscussions(
  id: number,
  cursor: string | null = null,
): Promise<GroupCollectionResponse<GroupDiscussion>> {
  const query: Record<string, string> = { per_page: '20' };
  if (cursor) query['cursor'] = cursor;
  return api.get<GroupCollectionResponse<GroupDiscussion>>(`${API_V2}/groups/${id}/discussions`, query);
}

/**
 * GET /api/v2/groups/{id}/announcements — list member-only announcements.
 */
export function getGroupAnnouncements(
  id: number,
  cursor: string | null = null,
): Promise<GroupAnnouncementsResponse> {
  const query: Record<string, string> = { limit: '20' };
  if (cursor) query['cursor'] = cursor;
  return api.get<GroupAnnouncementsResponse>(`${API_V2}/groups/${id}/announcements`, query);
}

/**
 * POST /api/v2/groups/{id}/discussions — start a new member discussion.
 */
export function createGroupDiscussion(
  id: number,
  payload: { title: string; content: string },
): Promise<{ data: GroupDiscussion }> {
  return api.post<{ data: GroupDiscussion }>(`${API_V2}/groups/${id}/discussions`, payload);
}

/**
 * POST /api/v2/groups/{id}/join — join a group.
 */
export function joinGroup(id: number): Promise<{ message: string }> {
  return api.post<{ message: string }>(`${API_V2}/groups/${id}/join`, {});
}

/**
 * DELETE /api/v2/groups/{id}/membership — leave a group.
 */
export function leaveGroup(id: number): Promise<void> {
  return api.delete<void>(`${API_V2}/groups/${id}/membership`);
}
