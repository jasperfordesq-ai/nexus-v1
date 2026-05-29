// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import { Platform } from 'react-native';

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

export interface GroupFileItem {
  id: number;
  group_id: number;
  file_name: string;
  file_path?: string | null;
  file_type: string;
  file_size: number;
  uploaded_by: number;
  uploader_name?: string | null;
  uploader_avatar?: string | null;
  folder?: string | null;
  description?: string | null;
  created_at: string | null;
}

export interface GroupQuestionAuthor {
  id: number;
  name: string;
  avatar?: string | null;
  avatar_url?: string | null;
}

export interface GroupQuestion {
  id: number;
  group_id?: number;
  title: string;
  body: string | null;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  answer_count: number;
  has_accepted_answer: boolean;
  is_closed?: boolean;
  author: GroupQuestionAuthor;
  created_at: string | null;
}

export interface GroupAnswer {
  id: number;
  question_id: number;
  body: string;
  vote_count: number;
  user_vote: 1 | -1 | 0;
  is_accepted: boolean;
  author: GroupQuestionAuthor;
  created_at: string | null;
}

export interface GroupQuestionDetail extends GroupQuestion {
  answers: GroupAnswer[];
}

export interface GroupTemplate {
  id: number;
  name: string;
  description?: string | null;
  icon?: string | null;
  default_visibility?: 'public' | 'private' | string | null;
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

export interface GroupFilesResponse {
  data: {
    items: GroupFileItem[];
    cursor: string | null;
    has_more: boolean;
  };
}

export interface GroupQuestionsResponse {
  data: {
    items: GroupQuestion[];
    cursor: string | null;
    has_more: boolean;
  };
}

export interface CreateGroupAnnouncementPayload {
  title: string;
  content: string;
  is_pinned?: boolean;
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

export function getGroupTemplates(): Promise<{ data: GroupTemplate[] } | GroupTemplate[]> {
  return api.get<{ data: GroupTemplate[] } | GroupTemplate[]>(`${API_V2}/group-templates`);
}

type UploadGroupImageResponse = {
  data?: { image_url?: string | null } | null;
  image_url?: string | null;
  message?: string;
};

function getUploadFilename(uri: string): string {
  const cleanUri = uri.split('?')[0] ?? uri;
  const lastSegment = cleanUri.split('/').pop();
  return lastSegment && lastSegment.includes('.') ? lastSegment : 'group.jpg';
}

function getMimeType(filename: string, fallback?: string | null): string {
  if (fallback?.startsWith('image/')) return fallback;
  const extension = filename.split('.').pop()?.toLowerCase();
  if (extension === 'png') return 'image/png';
  if (extension === 'webp') return 'image/webp';
  if (extension === 'gif') return 'image/gif';
  return 'image/jpeg';
}

async function appendGroupImageFile(formData: FormData, uri: string): Promise<void> {
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
 * POST /api/v2/groups/{id}/image — upload or replace a group image.
 */
export async function uploadGroupImage(id: number, uri: string): Promise<{ data: { image_url: string } }> {
  const formData = new FormData();
  await appendGroupImageFile(formData, uri);

  const response = await api.upload<UploadGroupImageResponse>(`${API_V2}/groups/${id}/image`, formData);
  const imageUrl = response.data?.image_url ?? response.image_url ?? null;
  if (!imageUrl) {
    throw new Error(response.message ?? 'Group image upload did not return an image URL.');
  }

  return { data: { image_url: imageUrl } };
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
 * GET /api/v2/groups/{id}/files — list member-only group files.
 */
export function getGroupFiles(
  id: number,
  cursor: string | null = null,
): Promise<GroupFilesResponse> {
  const query: Record<string, string> = { per_page: '20' };
  if (cursor) query['cursor'] = cursor;
  return api.get<GroupFilesResponse>(`${API_V2}/groups/${id}/files`, query);
}

/**
 * GET /api/v2/groups/{id}/questions — list member-only group Q&A questions.
 */
export function getGroupQuestions(
  id: number,
  cursor: string | null = null,
  sort: 'newest' | 'votes' | 'unanswered' = 'newest',
): Promise<GroupQuestionsResponse> {
  const query: Record<string, string> = { per_page: '20', sort };
  if (cursor) query['cursor'] = cursor;
  return api.get<GroupQuestionsResponse>(`${API_V2}/groups/${id}/questions`, query);
}

export function getGroupQuestion(id: number, questionId: number): Promise<{ data: GroupQuestionDetail }> {
  return api.get<{ data: GroupQuestionDetail }>(`${API_V2}/groups/${id}/questions/${questionId}`);
}

export function createGroupQuestion(
  id: number,
  payload: { title: string; body: string },
): Promise<{ data: { id: number; title: string } }> {
  return api.post<{ data: { id: number; title: string } }>(`${API_V2}/groups/${id}/questions`, payload);
}

export function answerGroupQuestion(
  id: number,
  questionId: number,
  payload: { body: string },
): Promise<{ data: { id: number; question_id: number } }> {
  return api.post<{ data: { id: number; question_id: number } }>(
    `${API_V2}/groups/${id}/questions/${questionId}/answers`,
    payload,
  );
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
 * POST /api/v2/groups/{id}/announcements — create an admin announcement.
 */
export function createGroupAnnouncement(
  id: number,
  payload: CreateGroupAnnouncementPayload,
): Promise<{ data: GroupAnnouncement }> {
  return api.post<{ data: GroupAnnouncement }>(`${API_V2}/groups/${id}/announcements`, payload);
}

/**
 * PUT /api/v2/groups/{id}/announcements/{announcementId} — update announcement fields.
 */
export function updateGroupAnnouncement(
  id: number,
  announcementId: number,
  payload: Partial<CreateGroupAnnouncementPayload>,
): Promise<{ data: GroupAnnouncement }> {
  return api.put<{ data: GroupAnnouncement }>(`${API_V2}/groups/${id}/announcements/${announcementId}`, payload);
}

/**
 * DELETE /api/v2/groups/{id}/announcements/{announcementId} — delete an admin announcement.
 */
export function deleteGroupAnnouncement(id: number, announcementId: number): Promise<{ data: { deleted: boolean } }> {
  return api.delete<{ data: { deleted: boolean } }>(`${API_V2}/groups/${id}/announcements/${announcementId}`);
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
