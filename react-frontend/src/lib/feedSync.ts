// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * feedSync — lightweight event bus for cross-page social state synchronisation.
 *
 * Problem: FeedPage caches item state. When the user likes/comments on a detail
 * page (listing, event, post, etc.) and returns to the feed, the card shows stale
 * counts. This bus lets any social interaction anywhere in the app broadcast a
 * patch, and FeedPage applies it to the matching item immediately.
 *
 * Usage:
 *   dispatchFeedSync({ targetType: 'listing', targetId: 42, patch: { is_liked: true, likes_count: 5 } });
 *
 * Sources that dispatch:
 *   - useSocialInteractions (listing/event/blog/goal detail pages)
 *   - FeedCard inline comments
 *   - PostDetailPage like handler
 *   - GroupDetailPage embedded-feed like handler
 *
 * Listeners:
 *   - FeedPage (patches items state for the matching card)
 *   - Any other feed-like page that renders FeedCard items
 */

export const FEED_SYNC_EVENT = 'nexus:feedSync';

export interface FeedSyncPatch {
  is_liked?: boolean;
  likes_count?: number;
  /** Delta applied to comments_count: +1 on add, -1 on delete. */
  comments_count_delta?: number;
  reactions?: {
    counts: Record<string, number>;
    total: number;
    user_reaction: string | null;
  };
}

export interface FeedSyncPayload {
  targetType: string;
  targetId: number;
  patch: FeedSyncPatch;
}

/** Broadcast a social state change to all active feed listeners. */
export function dispatchFeedSync(payload: FeedSyncPayload): void {
  window.dispatchEvent(new CustomEvent<FeedSyncPayload>(FEED_SYNC_EVENT, { detail: payload }));
}
