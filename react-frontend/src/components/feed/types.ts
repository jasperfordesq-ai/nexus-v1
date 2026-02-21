// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type FeedFilter = 'all' | 'posts' | 'listings' | 'events' | 'polls' | 'goals';
export type PostMode = 'text' | 'poll';

export interface FeedItem {
  id: number;
  content: string;
  title?: string;
  author_name?: string;
  author_avatar?: string;
  author_id?: number;
  author?: {
    id: number;
    name: string;
    avatar_url?: string;
  };
  created_at: string;
  type: 'post' | 'listing' | 'event' | 'poll' | 'goal' | 'review';
  likes_count: number;
  comments_count: number;
  is_liked: boolean;
  image_url?: string;
  poll_data?: PollData;
  rating?: number;
  receiver?: {
    id: number;
    name: string;
  };
}

export interface PollData {
  id: number;
  question: string;
  options: PollOption[];
  total_votes: number;
  user_vote_option_id: number | null;
  is_active: boolean;
}

export interface PollOption {
  id: number;
  text: string;
  vote_count: number;
  percentage: number;
}

export interface FeedCommentAuthor {
  id: number;
  name: string;
  avatar: string | null;
}

export interface FeedComment {
  id: number;
  content: string;
  created_at: string;
  edited: boolean;
  is_own: boolean;
  author: FeedCommentAuthor;
  reactions: Record<string, number>;
  user_reactions: string[];
  replies: FeedComment[];
}

/** Normalize author fields from API (supports both flat and nested) */
export function getAuthor(item: FeedItem) {
  return {
    id: item.author_id ?? item.author?.id ?? 0,
    name: item.author_name ?? item.author?.name ?? 'Unknown',
    avatar: item.author_avatar ?? item.author?.avatar_url ?? null,
  };
}
