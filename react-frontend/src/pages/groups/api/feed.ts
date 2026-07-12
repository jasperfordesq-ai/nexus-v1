// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { FeedItem } from '@/components/feed/types';
import type { ReactionType } from '@/components/social';
import { api } from '@/lib/api';
import { normalizeGroupApiError, unwrapGroupResponse } from './core';

export interface GroupFeedPage {
  items: FeedItem[];
  nextCursor?: string;
  hasMore: boolean;
}

export interface GroupFeedReadOptions {
  cursor?: string;
  perPage?: number;
  signal?: AbortSignal;
}

export interface GroupFeedLikeResult {
  isLiked: boolean | null;
  likesCount: number;
}

async function normalizeRequest<T>(request: Promise<import('./core').GroupApiResponse<T>>): Promise<T> {
  try {
    return unwrapGroupResponse(await request);
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function listGroupFeed(
  groupId: number,
  options: GroupFeedReadOptions = {},
): Promise<GroupFeedPage> {
  try {
    const params = new URLSearchParams({
      group_id: String(groupId),
      per_page: String(options.perPage ?? 20),
    });
    if (options.cursor) params.set('cursor', options.cursor);
    const response = await api.get<FeedItem[]>(`/v2/feed?${params.toString()}`, {
      signal: options.signal,
    });
    const items = unwrapGroupResponse(response);
    if (!Array.isArray(items)) throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
    return {
      items,
      nextCursor: response.meta?.cursor ?? response.meta?.next_cursor ?? undefined,
      hasMore: response.meta?.has_more ?? false,
    };
  } catch (error) {
    throw normalizeGroupApiError(error);
  }
}

export async function toggleGroupFeedLike(item: FeedItem): Promise<GroupFeedLikeResult> {
  const payload = await normalizeRequest(api.post<{ action?: string; likes_count: number }>(
    '/v2/feed/like',
    { target_type: item.type, target_id: item.id },
  ));
  if (!payload || typeof payload.likes_count !== 'number') {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  }
  return {
    isLiked: payload.action === 'liked' ? true : payload.action === 'unliked' ? false : null,
    likesCount: payload.likes_count,
  };
}

export async function reactToGroupFeedItem(
  item: FeedItem,
  reactionType: ReactionType,
): Promise<FeedItem['reactions']> {
  const payload = await normalizeRequest(api.post<{ reactions: FeedItem['reactions'] }>('/v2/reactions', {
    target_type: item.type,
    target_id: item.id,
    reaction_type: reactionType,
  }));
  if (!payload?.reactions) throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  return payload.reactions;
}

export async function hideGroupFeedItem(item: FeedItem): Promise<void> {
  await normalizeRequest(api.post<void>(`/v2/feed/posts/${item.id}/hide`, { type: item.type }));
}

export async function muteGroupFeedUser(userId: number): Promise<void> {
  await normalizeRequest(api.post<void>(`/v2/feed/users/${userId}/mute`));
}

export async function reportGroupFeedItem(postId: number, reason: string): Promise<void> {
  await normalizeRequest(api.post<void>(`/v2/feed/posts/${postId}/report`, { reason }));
}

export async function deleteGroupFeedItem(item: FeedItem): Promise<void> {
  await normalizeRequest(api.post<void>(`/v2/feed/posts/${item.id}/delete`));
}

export async function voteInGroupFeedPoll(
  pollId: number,
  optionId: number,
): Promise<NonNullable<FeedItem['poll_data']>> {
  const payload = await normalizeRequest(api.post<NonNullable<FeedItem['poll_data']>>(
    `/v2/feed/polls/${pollId}/vote`,
    { option_id: optionId },
  ));
  if (!payload || typeof payload !== 'object') {
    throw normalizeGroupApiError({ code: 'INVALID_RESPONSE' });
  }
  return payload;
}
