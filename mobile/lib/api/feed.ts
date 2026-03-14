// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export type FeedItemType =
  | 'post'
  | 'listing'
  | 'event'
  | 'poll'
  | 'goal'
  | 'job'
  | 'challenge'
  | 'volunteer'
  | 'review';

export interface FeedItem {
  id: number;
  type: FeedItemType;
  title: string;
  content: string | null;
  image_url: string | null;
  user_id: number;
  author_name: string;
  author_avatar: string | null;
  is_liked?: boolean;
  likes_count: number;
  comments_count: number;
  created_at: string;
  location: string | null;
  rating: number | null;
  start_date: string | null;
  job_type: string | null;
  commitment: string | null;
  submission_deadline: string | null;
  receiver: { id: number; name: string } | null;
}

export interface FeedResponse {
  data: FeedItem[];
  meta: {
    per_page: number;
    has_more: boolean;
    cursor: string | null;
    base_url?: string;
  };
}

/**
 * GET /api/v2/feed — personalised activity feed for the current tenant.
 *
 * Pass `cursor` for cursor-based pagination (preferred). If `cursor` is
 * provided it takes precedence and `page` is ignored by the server.
 */
export function getFeed(page = 1, cursor?: string | null): Promise<FeedResponse> {
  const params: Record<string, string> = { page: String(page) };
  if (cursor) {
    params['cursor'] = cursor;
  }
  return api.get<FeedResponse>(`${API_V2}/feed`, params);
}

export interface LikeResult {
  liked: boolean;
  likes_count: number;
}

/**
 * POST /api/v2/feed/like — toggle like on a feed item.
 * target_type maps the feed item type to the like target:
 *   post → 'post', listing → 'listing', event → 'event'
 */
export function toggleLike(targetType: string, targetId: number): Promise<{ data: LikeResult }> {
  return api.post<{ data: LikeResult }>(`${API_V2}/feed/like`, {
    target_type: targetType,
    target_id: targetId,
  });
}
