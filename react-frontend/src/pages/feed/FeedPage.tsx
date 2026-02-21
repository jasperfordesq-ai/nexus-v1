// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feed Page - Social feed with posts, likes, comments, polls, and moderation
 *
 * Uses V2 API: GET /api/v2/feed, POST /api/v2/feed/posts, POST /api/v2/feed/like
 * Uses V2 API: GET /api/v2/comments, POST /api/v2/comments
 * Uses V2 API: POST /api/v2/feed/posts/{id}/hide, POST /api/v2/feed/users/{id}/mute
 * Uses V2 API: POST /api/v2/feed/posts/{id}/report
 * Uses V2 API: POST /api/v2/feed/polls, POST /api/v2/feed/polls/{id}/vote
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Skeleton,
  Divider,
} from '@heroui/react';
import {
  Newspaper,
  Plus,
  RefreshCw,
  AlertTriangle,
  ImagePlus,
  BarChart3,
  Sparkles,
  TrendingUp,
  Flag,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { ComposeHub } from '@/components/compose';
import type { ComposeTab } from '@/components/compose';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem, FeedFilter, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';

/* ───────────────────────── Feed Card Skeleton ───────────────────────── */

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
      <Skeleton className="h-40 w-full rounded-xl mb-4" />
      <Divider />
      <div className="flex gap-4 pt-3">
        <Skeleton className="h-8 w-20 rounded-lg" />
        <Skeleton className="h-8 w-24 rounded-lg" />
      </div>
    </GlassCard>
  );
}

/* ───────────────────────── Main Component ───────────────────────── */

export function FeedPage() {
  usePageTitle('Feed');
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FeedFilter>('all');
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);

  // Compose Hub
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [composeDefaultTab, setComposeDefaultTab] = useState<ComposeTab>('post');

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  // Use a ref for cursor to avoid infinite re-render loop
  const cursorRef = useRef<string | undefined>();

  const loadFeed = useCallback(async (append = false) => {
    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (filter !== 'all') params.set('type', filter);
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<FeedItem[]>(
        `/v2/feed?${params}`
      );

      if (response.success && response.data) {
        const feedItems = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setItems((prev) => [...prev, ...feedItems]);
        } else {
          setItems(feedItems);
        }
        setHasMore(response.meta?.has_more ?? false);
        cursorRef.current = response.meta?.cursor ?? undefined;
      } else {
        if (!append) setError('Failed to load feed.');
      }
    } catch (err) {
      logError('Failed to load feed', err);
      if (!append) setError('Failed to load feed. Please try again.');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [filter]);

  useEffect(() => {
    cursorRef.current = undefined;
    loadFeed();
  }, [filter, loadFeed]);

  /* ───────── Like Toggle ───────── */

  const handleToggleLike = async (item: FeedItem) => {
    // Optimistic update
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
      // Revert on error
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

  /* ───────── Moderation ───────── */

  const handleHidePost = async (postId: number) => {
    try {
      await api.post(`/v2/feed/posts/${postId}/hide`);
      setItems((prev) => prev.filter((fi) => !(fi.id === postId && fi.type === 'post')));
      toast.success('Post hidden');
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error('Failed to hide post');
    }
  };

  const handleMuteUser = async (userId: number) => {
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setItems((prev) => prev.filter((fi) => getAuthor(fi).id !== userId));
      toast.success('User muted');
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error('Failed to mute user');
    }
  };

  const openReportModal = (postId: number) => {
    setReportPostId(postId);
    setReportReason('');
    onReportOpen();
  };

  const handleReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toast.error('Please provide a reason');
      return;
    }

    try {
      setIsReporting(true);
      await api.post(`/v2/feed/posts/${reportPostId}/report`, {
        reason: reportReason.trim(),
      });
      onReportClose();
      setReportPostId(null);
      setReportReason('');
      toast.success('Post reported. Thank you for helping keep our community safe.');
    } catch (err) {
      logError('Failed to report post', err);
      toast.error('Failed to report post');
    } finally {
      setIsReporting(false);
    }
  };

  const handleDeletePost = async (item: FeedItem) => {
    try {
      await api.post('/social/delete', {
        target_type: item.type,
        target_id: item.id,
      });
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success('Post deleted');
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error('Failed to delete post');
    }
  };

  /* ───────── Poll Voting ───────── */

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
      toast.error('Failed to submit vote');
    }
  };

  const filterOptions: { key: FeedFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'posts', label: 'Posts' },
    { key: 'listings', label: 'Listings' },
    { key: 'events', label: 'Events' },
    { key: 'polls', label: 'Polls' },
    { key: 'goals', label: 'Goals' },
  ];

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.06 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="max-w-2xl mx-auto space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
              <Newspaper className="w-5 h-5 text-white" aria-hidden="true" />
            </div>
            Community Feed
          </h1>
          <p className="text-[var(--text-muted)] mt-1 text-sm">See what&apos;s happening in your community</p>
        </div>

        {isAuthenticated && (
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={onCreateOpen}
          >
            New Post
          </Button>
        )}
      </div>

      {/* Quick Post Box */}
      {isAuthenticated && (
        <GlassCard className="p-4 hover:border-[var(--color-primary)]/20 transition-colors cursor-pointer" onClick={onCreateOpen}>
          <div className="flex items-center gap-3">
            <Avatar
              name={user?.first_name || 'You'}
              src={resolveAvatarUrl(user?.avatar)}
              size="sm"
              isBordered
              className="ring-2 ring-[var(--border-default)]"
            />
            <div className="flex-1 bg-[var(--surface-elevated)] rounded-full px-4 py-2.5 text-[var(--text-subtle)] text-sm border border-[var(--border-default)] hover:border-[var(--color-primary)]/30 transition-colors">
              What&apos;s on your mind?
            </div>
            <div className="flex gap-1">
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--text-muted)]"
                onPress={() => { setComposeDefaultTab('post'); onCreateOpen(); }}
                aria-label="Add image"
              >
                <ImagePlus className="w-4 h-4" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--text-muted)]"
                onPress={() => { setComposeDefaultTab('poll'); onCreateOpen(); }}
                aria-label="Create poll"
              >
                <BarChart3 className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </GlassCard>
      )}

      {/* Filter Tabs */}
      <div className="flex gap-2 flex-wrap">
        {filterOptions.map((opt) => (
          <Button
            key={opt.key}
            size="sm"
            variant={filter === opt.key ? 'solid' : 'flat'}
            radius="full"
            className={
              filter === opt.key
                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md shadow-indigo-500/20'
                : 'bg-[var(--surface-elevated)] text-[var(--text-muted)] hover:text-[var(--text-primary)] border border-[var(--border-default)]'
            }
            onPress={() => setFilter(opt.key)}
          >
            {opt.label}
          </Button>
        ))}
      </div>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-10 text-center" glow="primary">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center mx-auto mb-5">
            <AlertTriangle className="w-8 h-8 text-amber-500" aria-hidden="true" />
          </div>
          <h2 className="text-lg font-semibold text-[var(--text-primary)] mb-2">Unable to Load Feed</h2>
          <p className="text-[var(--text-muted)] mb-5 text-sm max-w-xs mx-auto">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadFeed()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Feed Items */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <FeedSkeleton key={i} />
              ))}
            </div>
          ) : items.length === 0 ? (
            <GlassCard className="p-12 text-center">
              <div className="w-20 h-20 rounded-3xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 border border-indigo-500/20 flex items-center justify-center mx-auto mb-6">
                <Sparkles className="w-10 h-10 text-indigo-400" aria-hidden="true" />
              </div>
              <h2 className="text-lg font-semibold text-[var(--text-primary)] mb-2">No posts yet</h2>
              <p className="text-sm text-[var(--text-muted)] mb-6 max-w-xs mx-auto">
                {filter !== 'all'
                  ? `No ${filter} in the feed right now. Try a different filter!`
                  : 'Be the first to share something with your community!'}
              </p>
              {isAuthenticated && (
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  onPress={onCreateOpen}
                >
                  Create Post
                </Button>
              )}
            </GlassCard>
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
                      onReportPost={() => openReportModal(item.id)}
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
                    className="border-[var(--border-default)] text-[var(--text-muted)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors"
                    onPress={() => loadFeed(true)}
                    isLoading={isLoadingMore}
                    startContent={!isLoadingMore ? <TrendingUp className="w-4 h-4" aria-hidden="true" /> : undefined}
                  >
                    Load More
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Compose Hub */}
      <ComposeHub
        isOpen={isCreateOpen}
        onClose={onCreateClose}
        defaultTab={composeDefaultTab}
        onSuccess={() => { cursorRef.current = undefined; loadFeed(); }}
      />

      {/* Report Post Modal */}
      <Modal
        isOpen={isReportOpen}
        onClose={onReportClose}
        classNames={{
          base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
          backdrop: 'bg-black/60 backdrop-blur-sm',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-[var(--text-primary)]">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-danger/10 flex items-center justify-center">
                <Flag className="w-4 h-4 text-danger" aria-hidden="true" />
              </div>
              Report Post
            </div>
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-[var(--text-muted)] mb-3">
              Please describe why you are reporting this post. Our moderators will review your report.
            </p>
            <Textarea
              label="Reason"
              placeholder="Describe the issue..."
              value={reportReason}
              onChange={(e) => setReportReason(e.target.value)}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-[var(--text-primary)]',
                inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
              }}
              autoFocus
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={onReportClose}
              className="text-[var(--text-muted)]"
            >
              Cancel
            </Button>
            <Button
              color="danger"
              variant="flat"
              onPress={handleReport}
              isLoading={isReporting}
              isDisabled={!reportReason.trim()}
              className="font-medium"
            >
              Submit Report
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default FeedPage;
