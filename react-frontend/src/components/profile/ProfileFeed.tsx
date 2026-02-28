// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ProfileFeed — displays a user's activity feed on their profile page.
 * Reuses FeedCard from the main feed system for consistent rendering.
 */

import { useState, useCallback, useEffect, useRef } from 'react';
import { Button, Skeleton, Divider } from '@heroui/react';
import { Rss, TrendingUp, AlertTriangle, RefreshCw } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { useTranslation } from 'react-i18next';
import { FeedCard } from '@/components/feed/FeedCard';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { FeedItem, PollData } from '@/components/feed/types';

interface ProfileFeedProps {
  userId: number;
  isOwnProfile?: boolean;
}

const containerVariants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
};

const itemVariants = {
  hidden: { opacity: 0, y: 12 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.25 } },
};

function FeedSkeleton() {
  return (
    <GlassCard className="p-5">
      <div className="flex items-center gap-3 mb-4">
        <Skeleton className="w-10 h-10 rounded-full" />
        <div className="flex-1">
          <Skeleton className="h-4 w-28 rounded mb-2" />
          <Skeleton className="h-3 w-20 rounded" />
        </div>
      </div>
      <Skeleton className="h-4 w-full rounded mb-2" />
      <Skeleton className="h-4 w-4/5 rounded mb-4" />
      <Divider />
      <div className="flex gap-4 pt-3">
        <Skeleton className="h-8 w-20 rounded-lg" />
        <Skeleton className="h-8 w-24 rounded-lg" />
      </div>
    </GlassCard>
  );
}

export function ProfileFeed({ userId, isOwnProfile = false }: ProfileFeedProps) {
  const { t } = useTranslation('profile');
  const { user, isAuthenticated } = useAuth();
  const toast = useToast();

  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const cursorRef = useRef<string | undefined>();

  const loadFeed = useCallback(async (cursor?: string) => {
    try {
      const isInitial = !cursor;
      if (isInitial) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams({
        user_id: String(userId),
        per_page: '20',
      });
      if (cursor) params.set('cursor', cursor);

      const response = await api.get<FeedItem[]>(`/v2/feed?${params.toString()}`);

      if (response.success && response.data) {
        const feedItems = Array.isArray(response.data) ? response.data : [];
        if (isInitial) {
          setItems(feedItems);
        } else {
          setItems((prev) => [...prev, ...feedItems]);
        }
        cursorRef.current = response.meta?.cursor ?? undefined;
        setHasMore(response.meta?.has_more ?? false);
      }
    } catch (err) {
      logError('Failed to load profile feed', err);
      if (!cursor) setError(t('feed_error', 'Failed to load activity'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [userId, t]);

  useEffect(() => {
    cursorRef.current = undefined;
    setItems([]);
    loadFeed();
  }, [userId, loadFeed]);

  // --- Feed action handlers (matching FeedPage patterns) ---

  const handleToggleLike = async (item: FeedItem) => {
    if (!isAuthenticated) return;
    setItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? {
              ...fi,
              is_liked: !fi.is_liked,
              likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1,
            }
          : fi
      )
    );
    try {
      await api.post('/v2/feed/like', {
        target_type: item.type,
        target_id: item.id,
      });
    } catch (err) {
      logError('Failed to toggle like', err);
      // Rollback
      setItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? {
                ...fi,
                is_liked: !fi.is_liked,
                likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1,
              }
            : fi
        )
      );
    }
  };

  const handleHidePost = async (postId: number) => {
    try {
      await api.post(`/v2/feed/posts/${postId}/hide`);
      setItems((prev) => prev.filter((fi) => !(fi.id === postId && fi.type === 'post')));
    } catch (err) {
      logError('Failed to hide post', err);
    }
  };

  const handleVotePoll = async (pollId: number, optionId: number) => {
    try {
      const response = await api.post<PollData>(`/v2/feed/polls/${pollId}/vote`, {
        option_id: optionId,
      });
      if (response.success && response.data) {
        setItems((prev) =>
          prev.map((fi) =>
            fi.id === pollId && fi.type === 'poll'
              ? { ...fi, poll_data: response.data as PollData }
              : fi
          )
        );
      }
    } catch (err) {
      logError('Failed to vote', err);
      toast.error(t('vote_failed', 'Vote failed'));
    }
  };

  const handleDeletePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success(t('post_deleted', 'Post deleted'));
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error(t('delete_failed', 'Delete failed'));
    }
  };

  // --- Render ---

  if (isLoading) {
    return (
      <div className="space-y-4">
        {[1, 2, 3].map((i) => (
          <FeedSkeleton key={i} />
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <GlassCard className="p-8 text-center">
        <AlertTriangle className="w-10 h-10 text-amber-500 mx-auto mb-3" aria-hidden="true" />
        <p className="text-theme-muted mb-4">{error}</p>
        <Button
          variant="flat"
          className="bg-theme-elevated text-theme-primary"
          startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
          onPress={() => void loadFeed()}
        >
          {t('retry', 'Retry')}
        </Button>
      </GlassCard>
    );
  }

  if (items.length === 0) {
    return (
      <EmptyState
        icon={<Rss className="w-10 h-10" aria-hidden="true" />}
        title={t('no_activity_title', 'No activity yet')}
        description={
          isOwnProfile
            ? t('no_activity_own', 'Share your first post or create a listing to get started!')
            : t('no_activity_other', "This member hasn't posted anything yet.")
        }
      />
    );
  }

  return (
    <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-4">
      <AnimatePresence mode="popLayout">
        {items.map((item) => (
          <motion.div key={`${item.type}-${item.id}`} variants={itemVariants} layout>
            <FeedCard
              item={item}
              onToggleLike={() => void handleToggleLike(item)}
              onHidePost={() => void handleHidePost(item.id)}
              onMuteUser={() => {/* no-op on profile */}}
              onReportPost={() => {/* no-op on profile */}}
              onDeletePost={() => void handleDeletePost(item)}
              onVotePoll={handleVotePoll}
              isAuthenticated={isAuthenticated}
              currentUserId={user?.id}
            />
          </motion.div>
        ))}
      </AnimatePresence>

      {hasMore && (
        <div className="pt-6 pb-2 text-center">
          <Button
            variant="bordered"
            className="border-[var(--border-default)] text-[var(--text-muted)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors"
            onPress={() => void loadFeed(cursorRef.current)}
            isLoading={isLoadingMore}
            startContent={!isLoadingMore ? <TrendingUp className="w-4 h-4" aria-hidden="true" /> : undefined}
          >
            {t('load_more', 'Load more')}
          </Button>
        </div>
      )}
    </motion.div>
  );
}
