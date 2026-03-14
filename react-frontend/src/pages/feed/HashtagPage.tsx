// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HashtagPage - Displays posts for a specific hashtag
 *
 * Shows all feed posts tagged with a particular hashtag.
 * API: GET /api/v2/feed/hashtags/{tag}
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Skeleton, Divider } from '@heroui/react';
import {
  Hash,
  ArrowLeft,
  RefreshCw,
  AlertTriangle,
  TrendingUp,
  Sparkles,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { EmptyState } from '@/components/feedback';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// Skeleton
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

export function HashtagPage() {
  const { t } = useTranslation('feed');
  const { tag } = useParams<{ tag: string }>();
  const { tenantPath } = useTenant();
  const { isAuthenticated, user } = useAuth();
  usePageTitle(tag ? `#${tag}` : t('hashtag.title'));

  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [postCount, setPostCount] = useState(0);
  const cursorRef = useRef<string | undefined>();

  const loadPosts = useCallback(async (append = false) => {
    if (!tag) return;

    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<FeedItem[]>(`/v2/feed/hashtags/${encodeURIComponent(tag)}?${params}`);

      if (response.success && response.data) {
        const feedItems = Array.isArray(response.data) ? response.data : [];
        if (append) {
          setItems((prev) => [...prev, ...feedItems]);
        } else {
          setItems(feedItems);
        }
        setHasMore(response.meta?.has_more ?? false);
        cursorRef.current = response.meta?.cursor ?? undefined;
        if (response.meta?.total_items !== undefined) {
          setPostCount(response.meta.total_items);
        }
      } else {
        if (!append) setError(t('hashtag.load_failed'));
      }
    } catch (err) {
      logError('Failed to load hashtag posts', err);
      if (!append) setError(t('hashtag.load_failed'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [tag]);

  useEffect(() => {
    cursorRef.current = undefined;
    loadPosts();
  }, [loadPosts]);

  // Feed interactions
  const handleToggleLike = async (item: FeedItem) => {
    setItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? { ...fi, is_liked: !fi.is_liked, likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1 }
          : fi
      )
    );
    try {
      await api.post('/v2/feed/like', { target_type: item.type, target_id: item.id });
    } catch {
      setItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? { ...fi, is_liked: !fi.is_liked, likes_count: fi.is_liked ? fi.likes_count - 1 : fi.likes_count + 1 }
            : fi
        )
      );
    }
  };

  const handleHidePost = async (postId: number) => {
    try {
      await api.post(`/v2/feed/posts/${postId}/hide`);
      setItems((prev) => prev.filter((fi) => fi.id !== postId));
    } catch { /* ignore */ }
  };

  const handleMuteUser = async (userId: number) => {
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setItems((prev) => prev.filter((fi) => getAuthor(fi).id !== userId));
    } catch { /* ignore */ }
  };

  const handleDeletePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setItems((prev) => prev.filter((fi) => fi.id !== item.id));
    } catch { /* ignore */ }
  };

  const handleVotePoll = async (pollId: number, optionId: number) => {
    try {
      const response = await api.post<PollData>(`/v2/feed/polls/${pollId}/vote`, { option_id: optionId });
      if (response.success && response.data) {
        setItems((prev) =>
          prev.map((fi) =>
            fi.id === pollId && fi.type === 'poll'
              ? { ...fi, poll_data: response.data as PollData }
              : fi
          )
        );
      }
    } catch { /* ignore */ }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <>
    <PageMeta
      title={tag ? `#${tag}` : t("hashtag.title")}
      description={t("hashtag.page_description", { tag: tag || "" })}
    />
    <div className="max-w-2xl mx-auto space-y-5">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Link to={tenantPath('/feed')}>
          <Button
            isIconOnly
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            aria-label={t('hashtag.back_to_feed')}
          >
            <ArrowLeft className="w-5 h-5" />
          </Button>
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-2">
            <Hash className="w-6 h-6 text-indigo-500" aria-hidden="true" />
            {tag}
          </h1>
          {postCount > 0 && (
            <p className="text-sm text-theme-muted">
              {t('hashtag.post_count', { count: postCount })}
            </p>
          )}
        </div>
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('hashtag.unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadPosts()}
          >
            {t('hashtag.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Feed Items */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => <FeedSkeleton key={i} />)}
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              icon={<Sparkles className="w-12 h-12" aria-hidden="true" />}
              title={t('hashtag.no_posts')}
              description={t('hashtag.no_posts_desc', { tag })}
            />
          ) : (
            <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-4">
              <AnimatePresence mode="popLayout">
                {items.map((item) => (
                  <motion.div key={`${item.type}-${item.id}`} variants={itemVariants} layout>
                    <FeedCard
                      item={item}
                      onToggleLike={() => handleToggleLike(item)}
                      onHidePost={() => handleHidePost(item.id)}
                      onMuteUser={() => handleMuteUser(getAuthor(item).id)}
                      onReportPost={() => {}}
                      onDeletePost={() => handleDeletePost(item)}
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
                    className="border-[var(--border-default)] text-[var(--text-muted)]"
                    onPress={() => loadPosts(true)}
                    isLoading={isLoadingMore}
                    startContent={!isLoadingMore ? <TrendingUp className="w-4 h-4" aria-hidden="true" /> : undefined}
                  >
                    {t('hashtag.load_more')}
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}
    </div>
    </>
  );
}

export default HashtagPage;
