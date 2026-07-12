// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export type ScheduledGroupPostType = 'discussion' | 'announcement';
export type ScheduledGroupPostRecurrence = 'daily' | 'weekly' | 'monthly';

export interface ScheduledGroupPost {
  id: number;
  post_type: ScheduledGroupPostType | string;
  title: string;
  content: string;
  scheduled_at: string;
  is_recurring: boolean;
  recurrence_pattern: ScheduledGroupPostRecurrence | string | null;
}

export interface CreateScheduledGroupPostInput {
  post_type: ScheduledGroupPostType;
  title: string;
  content: string;
  scheduled_at: string;
  is_recurring?: boolean;
  recurrence_pattern?: ScheduledGroupPostRecurrence;
}

export interface ListScheduledGroupPostsOptions {
  signal?: AbortSignal;
}

export async function listScheduledGroupPosts(
  groupId: number,
  options: ListScheduledGroupPostsOptions = {},
): Promise<ScheduledGroupPost[]> {
  try {
    const response = await api.get<ScheduledGroupPost[]>(
      `/v2/groups/${groupId}/scheduled-posts`,
      { signal: options.signal },
    );
    const posts = unwrapGroupResponse(response);
    if (!Array.isArray(posts)) {
      throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
    }
    return posts;
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function createScheduledGroupPost(
  groupId: number,
  input: CreateScheduledGroupPostInput,
): Promise<ScheduledGroupPost | undefined> {
  try {
    return unwrapGroupResponse(await api.post<ScheduledGroupPost>(
      `/v2/groups/${groupId}/scheduled-posts`,
      input,
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function cancelScheduledGroupPost(groupId: number, postId: number): Promise<void> {
  try {
    unwrapGroupResponse<void>(await api.delete<void>(
      `/v2/groups/${groupId}/scheduled-posts/${postId}`,
    ));
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}
