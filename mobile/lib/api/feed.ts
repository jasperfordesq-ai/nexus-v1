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

export interface PollOption {
  id: number;
  text: string;
  vote_count: number;
  percentage: number;
}

export interface PollData {
  id: number;
  question: string;
  options: PollOption[];
  total_votes: number;
  user_vote_option_id: number | null;
  is_active: boolean;
}

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
  poll_data?: PollData | null;
  media?: Array<{
    id: number;
    media_type: 'image' | 'video';
    file_url: string;
    thumbnail_url: string | null;
    alt_text: string | null;
    width: number | null;
    height: number | null;
    display_order: number;
  }>;
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

export interface BookmarkResult {
  bookmarked: boolean;
}

/**
 * POST /api/v2/feed/bookmark — toggle bookmark/save on a feed item.
 */
export function toggleBookmark(targetType: string, targetId: number): Promise<{ data: BookmarkResult }> {
  return api.post<{ data: BookmarkResult }>(`${API_V2}/feed/bookmark`, {
    target_type: targetType,
    target_id: targetId,
  });
}

/**
 * GET /api/v2/feed/polls/:pollId — fetch current poll state.
 */
export function getFeedPoll(pollId: number): Promise<{ data: PollData }> {
  return api.get<{ data: PollData }>(`${API_V2}/feed/polls/${pollId}`);
}

/**
 * POST /api/v2/feed/polls/:pollId/vote — cast a vote on a poll option.
 */
export function voteFeedPoll(pollId: number, optionId: number): Promise<{ data: PollData }> {
  return api.post<{ data: PollData }>(`${API_V2}/feed/polls/${pollId}/vote`, { option_id: optionId });
}
