// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useRef, useEffect } from 'react';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FeedComment } from '@/components/feed/types';

/* ─── Types ─────────────────────────────────────────────────── */

export interface SocialInteractionsOptions {
  targetType: string;
  targetId: number;
  initialLiked?: boolean;
  initialLikesCount?: number;
  initialCommentsCount?: number;
}

export interface LikerUser {
  id: number;
  name: string;
  avatar_url: string | null;
  liked_at: string;
  liked_at_formatted?: string;
}

export interface LikersResult {
  likers: LikerUser[];
  total_count: number;
  has_more: boolean;
}

export interface MentionUser {
  id: number;
  name: string;
  avatar_url: string | null;
}

export const AVAILABLE_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🎉'] as const;

/* ─── Hook ──────────────────────────────────────────────────── */

export function useSocialInteractions(options: SocialInteractionsOptions) {
  const { targetType, targetId, initialLiked = false, initialLikesCount = 0, initialCommentsCount = 0 } = options;

  // Like state
  const [isLiked, setIsLiked] = useState(initialLiked);
  const [likesCount, setLikesCount] = useState(initialLikesCount);
  const [isLiking, setIsLiking] = useState(false);

  // Comments state
  const [comments, setComments] = useState<FeedComment[]>([]);
  const [commentsCount, setCommentsCount] = useState(initialCommentsCount);
  const [commentsLoading, setCommentsLoading] = useState(false);
  const [commentsLoaded, setCommentsLoaded] = useState(false);

  // Guard against double-loading
  const loadingRef = useRef(false);

  // Sync state when initial values change (e.g. after API data loads)
  const initializedRef = useRef(false);
  useEffect(() => {
    if (!initializedRef.current) {
      initializedRef.current = true;
      return;
    }
    setIsLiked(initialLiked);
    setLikesCount(initialLikesCount);
    setCommentsCount(initialCommentsCount);
  }, [initialLiked, initialLikesCount, initialCommentsCount]);

  /* ───── Like ───── */

  const toggleLike = useCallback(async () => {
    if (isLiking) return;
    // Optimistic update
    const wasLiked = isLiked;
    setIsLiked(!wasLiked);
    setLikesCount((prev) => wasLiked ? prev - 1 : prev + 1);
    setIsLiking(true);
    try {
      const res = await api.post<{ status: string; likes_count: number }>('/v2/feed/like', {
        target_type: targetType,
        target_id: targetId,
      });
      if (res.success && res.data) {
        setIsLiked(res.data.status === 'liked');
        setLikesCount(res.data.likes_count);
      }
    } catch (err) {
      // Revert on error
      setIsLiked(wasLiked);
      setLikesCount((prev) => wasLiked ? prev + 1 : prev - 1);
      logError('Failed to toggle like', err);
    } finally {
      setIsLiking(false);
    }
  }, [targetType, targetId, isLiked, isLiking]);

  /* ───── Comments ───── */

  const loadComments = useCallback(async () => {
    if (loadingRef.current) return;
    loadingRef.current = true;
    setCommentsLoading(true);
    try {
      const res = await api.get<{ comments: FeedComment[]; count: number }>(
        `/v2/comments?target_type=${encodeURIComponent(targetType)}&target_id=${targetId}`
      );
      if (res.success && res.data) {
        setComments(res.data.comments ?? []);
        setCommentsCount(res.data.count ?? res.data.comments?.length ?? 0);
      }
    } catch (err) {
      logError('Failed to load comments', err);
    } finally {
      setCommentsLoading(false);
      setCommentsLoaded(true);
      loadingRef.current = false;
    }
  }, [targetType, targetId]);

  const submitComment = useCallback(async (content: string, parentId?: number): Promise<boolean> => {
    if (!content.trim()) return false;
    try {
      const res = await api.post('/v2/comments', {
        target_type: targetType,
        target_id: targetId,
        content: content.trim(),
        ...(parentId ? { parent_id: parentId } : {}),
      });
      if (res.success) {
        setCommentsCount((prev) => prev + 1);
        // Reload to get server-rendered comment with author info
        loadingRef.current = false;
        await loadComments();
        return true;
      }
      return false;
    } catch (err) {
      logError('Failed to submit comment', err);
      return false;
    }
  }, [targetType, targetId, loadComments]);

  const editComment = useCallback(async (commentId: number, content: string): Promise<boolean> => {
    if (!content.trim()) return false;
    try {
      const res = await api.put(`/v2/comments/${commentId}`, { content: content.trim() });
      if (res.success) {
        // Update locally
        const updateInTree = (list: FeedComment[]): FeedComment[] =>
          list.map((c) => {
            if (c.id === commentId) return { ...c, content: content.trim(), edited: true };
            if (c.replies?.length) return { ...c, replies: updateInTree(c.replies) };
            return c;
          });
        setComments(updateInTree);
        return true;
      }
      return false;
    } catch (err) {
      logError('Failed to edit comment', err);
      return false;
    }
  }, []);

  const deleteComment = useCallback(async (commentId: number): Promise<boolean> => {
    try {
      const res = await api.delete(`/v2/comments/${commentId}`);
      if (res.success) {
        // Remove from tree locally
        const removeFromTree = (list: FeedComment[]): FeedComment[] =>
          list
            .filter((c) => c.id !== commentId)
            .map((c) => (c.replies?.length ? { ...c, replies: removeFromTree(c.replies) } : c));
        setComments(removeFromTree);
        setCommentsCount((prev) => Math.max(0, prev - 1));
        return true;
      }
      return false;
    } catch (err) {
      logError('Failed to delete comment', err);
      return false;
    }
  }, []);

  /* ───── Reactions ───── */

  const toggleReaction = useCallback(async (commentId: number, emoji: string) => {
    try {
      const res = await api.post<{ action: string; emoji: string; reactions: Record<string, number> }>(
        `/v2/comments/${commentId}/reactions`,
        { emoji }
      );
      if (res.success && res.data) {
        const { action, reactions: updatedReactions } = res.data;
        const updateInTree = (list: FeedComment[]): FeedComment[] =>
          list.map((c) => {
            if (c.id === commentId) {
              const newUserReactions = action === 'added'
                ? [...(c.user_reactions || []), emoji]
                : (c.user_reactions || []).filter((e) => e !== emoji);
              return { ...c, reactions: updatedReactions, user_reactions: newUserReactions };
            }
            if (c.replies?.length) return { ...c, replies: updateInTree(c.replies) };
            return c;
          });
        setComments(updateInTree);
      }
    } catch (err) {
      logError('Failed to toggle reaction', err);
    }
  }, []);

  /* ───── Share ───── */

  const shareToFeed = useCallback(async (content?: string): Promise<boolean> => {
    try {
      const res = await api.post('/social/share', {
        parent_type: targetType,
        parent_id: targetId,
        content: content?.trim() ?? '',
      });
      return res.success ?? (res as unknown as { status?: string }).status === 'success';
    } catch (err) {
      logError('Failed to share', err);
      return false;
    }
  }, [targetType, targetId]);

  /* ───── Mention Search ───── */

  const searchMentions = useCallback(async (query: string): Promise<MentionUser[]> => {
    if (!query.trim()) return [];
    try {
      const res = await api.post<{ users: MentionUser[] }>('/social/mention-search', { query: query.trim() });
      if (res.success && res.data) return res.data.users ?? [];
      // V1 shape: { status: 'success', users: [...] }
      const raw = res as unknown as { users?: MentionUser[] };
      return raw.users ?? [];
    } catch (err) {
      logError('Failed to search mentions', err);
      return [];
    }
  }, []);

  /* ───── Likers ───── */

  const loadLikers = useCallback(async (page = 1): Promise<LikersResult> => {
    try {
      const res = await api.post<LikersResult>('/social/likers', {
        target_type: targetType,
        target_id: targetId,
        page,
        limit: 20,
      });
      if (res.success && res.data) return res.data;
      // V1 shape fallback
      const raw = res as unknown as LikersResult;
      return { likers: raw.likers ?? [], total_count: raw.total_count ?? 0, has_more: raw.has_more ?? false };
    } catch (err) {
      logError('Failed to load likers', err);
      return { likers: [], total_count: 0, has_more: false };
    }
  }, [targetType, targetId]);

  return {
    // Like
    isLiked,
    likesCount,
    isLiking,
    toggleLike,

    // Comments
    comments,
    commentsCount,
    commentsLoading,
    commentsLoaded,
    loadComments,
    submitComment,
    editComment,
    deleteComment,

    // Reactions
    availableReactions: AVAILABLE_REACTIONS,
    toggleReaction,

    // Share
    shareToFeed,

    // Mentions
    searchMentions,

    // Likers
    loadLikers,
  };
}
