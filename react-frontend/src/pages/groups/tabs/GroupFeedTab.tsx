// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Group Feed Tab
 * Displays the group activity feed with create-post prompt, feed cards, and load-more.
 */

import { motion, AnimatePresence } from 'framer-motion';
import { Button, Avatar, Skeleton, Divider } from '@heroui/react';
import { Lock, Newspaper, Plus, TrendingUp } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';

interface GroupFeedTabProps {
  isMember: boolean;
  isJoining: boolean;
  feedItems: FeedItem[];
  feedLoading: boolean;
  feedHasMore: boolean;
  feedLoadingMore: boolean;
  onJoinLeave: () => void;
  onComposeOpen: () => void;
  onLoadMore: () => void;
  onToggleLike: (item: FeedItem) => void;
  onHidePost: (postId: number) => void;
  onMuteUser: (userId: number) => void;
  onReportPost: (postId: number) => void;
  onDeletePost: (item: FeedItem) => void;
  onVotePoll: (pollId: number, optionId: number) => void;
}

export function GroupFeedTab({
  isMember,
  isJoining,
  feedItems,
  feedLoading,
  feedHasMore,
  feedLoadingMore,
  onJoinLeave,
  onComposeOpen,
  onLoadMore,
  onToggleLike,
  onHidePost,
  onMuteUser,
  onReportPost,
  onDeletePost,
  onVotePoll,
}: GroupFeedTabProps) {
  const { t } = useTranslation('groups');
  const { user: currentUser, isAuthenticated } = useAuth();

  if (!isMember) {
    return (
      <GlassCard className="p-6">
        <EmptyState
          icon={<Lock className="w-12 h-12" aria-hidden="true" />}
          title={t('detail.join_to_see_feed_title', 'Join to see the feed')}
          description={t('detail.join_to_see_feed_desc', 'Join this group to view posts and participate in conversations.')}
          action={
            isAuthenticated && (
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={onJoinLeave}
                isLoading={isJoining}
              >
                {t('detail.join_group')}
              </Button>
            )
          }
        />
      </GlassCard>
    );
  }

  return (
    <div className="space-y-4">
      {/* Create Post Button */}
      {isAuthenticated && (
        <GlassCard className="p-4 hover:border-[var(--color-primary)]/20 transition-colors cursor-pointer" onClick={onComposeOpen}>
          <div className="flex items-center gap-3">
            <Avatar
              name={currentUser?.first_name || 'You'}
              src={resolveAvatarUrl(currentUser?.avatar)}
              size="sm"
              isBordered
              className="ring-2 ring-[var(--border-default)]"
            />
            <div className="flex-1 bg-[var(--surface-elevated)] rounded-full px-4 py-2.5 text-[var(--text-subtle)] text-sm border border-[var(--border-default)] hover:border-[var(--color-primary)]/30 transition-colors">
              {t('detail.feed_whats_on_your_mind', "What's on your mind?")}
            </div>
          </div>
        </GlassCard>
      )}

      {/* Feed Items */}
      {feedLoading && feedItems.length === 0 ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <GlassCard key={i} className="p-5">
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
          ))}
        </div>
      ) : feedItems.length === 0 ? (
        <EmptyState
          icon={<Newspaper className="w-12 h-12" aria-hidden="true" />}
          title={t('detail.feed_empty_title', 'No posts yet')}
          description={t('detail.feed_empty_desc', 'Be the first to share something with this group!')}
          action={
            isAuthenticated && (
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                onPress={onComposeOpen}
              >
                {t('detail.feed_create_post', 'Create Post')}
              </Button>
            )
          }
        />
      ) : (
        <div className="space-y-4">
          <AnimatePresence mode="popLayout">
            {feedItems.map((item) => (
              <motion.div key={`${item.type}-${item.id}`} layout>
                <FeedCard
                  item={item}
                  onToggleLike={() => onToggleLike(item)}
                  onHidePost={() => onHidePost(item.id)}
                  onMuteUser={() => onMuteUser(getAuthor(item).id)}
                  onReportPost={() => onReportPost(item.id)}
                  onDeletePost={() => onDeletePost(item)}
                  onVotePoll={onVotePoll}
                  isAuthenticated={isAuthenticated}
                  currentUserId={currentUser?.id}
                />
              </motion.div>
            ))}
          </AnimatePresence>

          {feedHasMore && (
            <div className="pt-4 text-center">
              <Button
                variant="bordered"
                className="border-[var(--border-default)] text-[var(--text-muted)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors"
                onPress={onLoadMore}
                isLoading={feedLoadingMore}
                startContent={!feedLoadingMore ? <TrendingUp className="w-4 h-4" aria-hidden="true" /> : undefined}
              >
                {t('detail.feed_load_more', 'Load More')}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
