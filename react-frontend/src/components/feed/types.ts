// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export type FeedFilter = 'all' | 'posts' | 'listings' | 'events' | 'polls' | 'goals' | 'jobs' | 'challenges' | 'volunteering' | 'blogs' | 'discussions';
export type PostMode = 'text' | 'poll';

/** A single media attachment (image/video) on a post. */
export interface PostMedia {
  id: number;
  media_type: 'image' | 'video';
  file_url: string;
  thumbnail_url: string | null;
  alt_text: string | null;
  width: number | null;
  height: number | null;
  file_size: number | null;
  display_order: number;
}

export interface FeedItem {
  id: number;
  content: string;
  content_truncated?: boolean;
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
  type: 'post' | 'listing' | 'event' | 'poll' | 'goal' | 'review' | 'job' | 'challenge' | 'volunteer' | 'blog' | 'discussion' | 'badge_earned' | 'level_up';
  likes_count: number;
  comments_count: number;
  is_liked: boolean;
  image_url?: string;
  /** Multi-image media attachments (carousel). */
  media?: PostMedia[];
  poll_data?: PollData;
  rating?: number;
  receiver?: {
    id: number;
    name: string;
  };
  /** Event-specific: start date/time */
  start_date?: string;
  /** Event/Job/Volunteer-specific: location name */
  location?: string;
  /** Job-specific: job type (paid, volunteer, timebank) */
  job_type?: string;
  /** Job-specific: commitment level */
  commitment?: string;
  /** Challenge-specific: submission deadline */
  submission_deadline?: string;
  /** Challenge-specific: number of ideas submitted */
  ideas_count?: number;
  /** Volunteer-specific: time credits offered */
  credits_offered?: number;
  /** Volunteer-specific: organization name */
  organization?: string;
  /** Badge-earned-specific: badge key */
  badge_key?: string;
  /** Badge-earned-specific: badge name */
  badge_name?: string;
  /** Badge-earned-specific: badge icon (emoji) */
  badge_icon?: string;
  /** Level-up-specific: new level number */
  new_level?: number;
  /** Scheduling: publish status (published, scheduled, draft) */
  publish_status?: 'published' | 'scheduled' | 'draft';
  /** Scheduling: when the post is scheduled to be published */
  scheduled_at?: string;
  /** Reaction data (replaces simple like system) */
  reactions?: {
    counts: Record<string, number>;
    total: number;
    user_reaction: string | null;
    top_reactors?: Array<{ id: number; name: string; avatar_url?: string | null }>;
  };
  /** Link previews attached to posts */
  link_previews?: Array<{
    url: string;
    title?: string | null;
    description?: string | null;
    image_url?: string | null;
    site_name?: string | null;
    favicon_url?: string | null;
    domain?: string | null;
    content_type?: string | null;
    embed_html?: string | null;
  }>;
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

/**
 * Get the detail page path for a feed item, or null if no detail page exists.
 * Posts and polls live exclusively in the feed — no detail page.
 */
export function getItemDetailPath(item: FeedItem): string | null {
  switch (item.type) {
    case 'listing':
      return `/listings/${item.id}`;
    case 'event':
      return `/events/${item.id}`;
    case 'goal':
      return '/goals';
    case 'review':
      return item.receiver ? `/profile/${item.receiver.id}` : null;
    case 'job':
      return `/jobs/${item.id}`;
    case 'challenge':
      return `/ideation/${item.id}`;
    case 'volunteer':
      return `/volunteering/opportunities/${item.id}`;
    case 'blog':
      return `/blog/${item.id}`;
    case 'discussion':
    case 'badge_earned':
    case 'level_up':
      return null;
    default:
      return null;
  }
}

/** Human-readable label for the "View ..." CTA */
export function getItemDetailLabel(item: FeedItem): string | null {
  switch (item.type) {
    case 'listing':
      return 'card.detail_listing';
    case 'event':
      return 'card.detail_event';
    case 'goal':
      return 'card.detail_goals';
    case 'review':
      return 'card.detail_profile';
    case 'job':
      return 'card.detail_job';
    case 'challenge':
      return 'card.detail_challenge';
    case 'volunteer':
      return 'card.detail_volunteer';
    case 'blog':
      return 'card.detail_blog';
    default:
      return null;
  }
}
