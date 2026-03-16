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

import { useState, useEffect, useCallback, useRef, Component, type ReactNode, type ErrorInfo } from 'react';
import { useNavigate } from 'react-router-dom';
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
  ArrowUp,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard, AlgorithmLabel } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { ComposeHub } from '@/components/compose';
import type { ComposeTab } from '@/components/compose';
import { FeedSidebar } from '@/components/feed/sidebar';
import { StoriesBar } from '@/components/feed/StoriesBar';
import { FeedModeToggle } from '@/components/feed/FeedModeToggle';
import { SubFilterChips } from '@/components/feed/SubFilterChips';
import { MobileFAB } from '@/components/feed/MobileFAB';
import { useAuth, useToast, usePusherOptional, useTenant } from '@/contexts';
import type { FeedPostEvent } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem, FeedFilter, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';
import type { Friend } from '@/components/feed/sidebar/FriendsWidget';

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

/* ───────────────────────── Sidebar Error Boundary ───────────────────────── */


class SidebarErrorBoundary extends Component<{ children: ReactNode }, { hasError: boolean }> {
  state = { hasError: false };
  static getDerivedStateFromError() { return { hasError: true }; }
  componentDidCatch(error: Error, info: ErrorInfo) { logError('Sidebar crash', { error, info }); }
  render() {
    if (this.state.hasError) return null; // Silently hide broken sidebar
    return this.props.children;
  }
}

/* ───────────────────────── Main Component ───────────────────────── */

export function FeedPage() {
  const { t } = useTranslation('feed');
  usePageTitle(t('page_title'));
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const pusher = usePusherOptional();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FeedFilter>('all');
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  // Count of real-time posts received while the user hasn't scrolled to top
  const [pendingPostCount, setPendingPostCount] = useState(0);

  // Feed mode: EdgeRank vs chronological
  const [feedMode, setFeedMode] = useState<'ranking' | 'recent'>('ranking');
  // Sub-filter (e.g. listings -> offers/requests)
  const [subFilter, setSubFilter] = useState<string | null>(null);
  // Stories friends
  const [storiesFriends, setStoriesFriends] = useState<Friend[]>([]);

  // Compose Hub
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [composeDefaultTab, setComposeDefaultTab] = useState<ComposeTab>('listing');

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  // Use a ref for cursor to avoid infinite re-render loop
  const cursorRef = useRef<string | undefined>();

  // Load friends for StoriesBar
  useEffect(() => {
    if (!isAuthenticated) return;
    const loadFriends = async () => {
      try {
        const response = await api.get<{ friends?: Friend[] }>('/v2/feed/sidebar');
        if (response.success && response.data?.friends) {
          setStoriesFriends(response.data.friends);
        }
      } catch (err) {
        logError('Failed to load stories friends', err);
      }
    };
    loadFriends();
  }, [isAuthenticated]);

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
      params.set('sort', feedMode);
      params.set('tz', Intl.DateTimeFormat().resolvedOptions().timeZone);
      if (filter !== 'all') params.set('type', filter);
      if (subFilter) params.set('subtype', subFilter);
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
        if (!append) {
          setError(response.code === 'SESSION_EXPIRED'
            ? t('session_expired', 'Your session has expired. Please log in again.')
            : t('error_load'));
        }
      }
    } catch (err) {
      logError('Failed to load feed', err);
      if (!append) setError(t('error_load_retry'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- t excluded intentionally (stable after namespace load)
  }, [filter, feedMode, subFilter]);

  // Track previous filter to detect filter changes and auto-reset subFilter
  const prevFilterRef = useRef(filter);
  useEffect(() => {
    // If the main filter changed, reset subFilter first
    if (prevFilterRef.current !== filter) {
      prevFilterRef.current = filter;
      setSubFilter(null);
      // The state update above will trigger this effect again with subFilter=null
      return;
    }
    cursorRef.current = undefined;
    setPendingPostCount(0);
    loadFeed();
  }, [filter, feedMode, subFilter, loadFeed]);

  /* ───────── Real-time feed subscription ───────── */

  useEffect(() => {
    if (!pusher || isLoading) return;

    const unsub = pusher.onFeedPost((event: FeedPostEvent) => {
      const incoming = event.post;

      // If the post was created by the current user it is already prepended
      // optimistically by ComposeHub / the onSuccess reload, so skip it.
      if (user?.id && incoming.author?.id === user.id) return;

      // Only surface posts that match the active filter.
      const matchesFilter =
        filter === 'all' ||
        (filter === 'posts' && incoming.type === 'post') ||
        (filter === 'listings' && incoming.type === 'listing') ||
        (filter === 'events' && incoming.type === 'event') ||
        (filter === 'polls' && incoming.type === 'poll') ||
        (filter === 'goals' && incoming.type === 'goal') ||
        (filter === 'jobs' && incoming.type === 'job') ||
        (filter === 'challenges' && incoming.type === 'challenge') ||
        (filter === 'volunteering' && incoming.type === 'volunteer');

      if (!matchesFilter) return;

      setItems((prev) => {
        // Guard against duplicates (e.g. race between Pusher event and manual reload)
        if (prev.some((p) => p.type === incoming.type && p.id === incoming.id)) {
          return prev;
        }
        return [incoming, ...prev];
      });

      setPendingPostCount((n) => n + 1);
    });

    return unsub;
  }, [pusher, isLoading, filter, user?.id]);

  /* ───────── Like Toggle ───────── */

  const handleToggleLike = useCallback(async (item: FeedItem) => {
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
  }, []);

  /* ───────── Moderation ───────── */

  const handleHidePost = useCallback(async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/hide`);
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success(t('toast.post_hidden'));
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error(t('toast.hide_failed'));
    }
  }, [toast, t]);

  const handleMuteUser = useCallback(async (item: FeedItem) => {
    const userId = getAuthor(item).id;
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setItems((prev) => prev.filter((fi) => getAuthor(fi).id !== userId));
      toast.success(t('toast.user_muted'));
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error(t('toast.mute_failed'));
    }
  }, [toast, t]);

  const openReportModal = useCallback((item: FeedItem) => {
    setReportPostId(item.id);
    setReportReason('');
    onReportOpen();
  }, [onReportOpen]);

  const handleReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toast.error(t('toast.provide_reason'));
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
      toast.success(t('toast.reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toast.error(t('toast.report_failed'));
    } finally {
      setIsReporting(false);
    }
  };

  const handleDeletePost = useCallback(async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toast.success(t('toast.deleted'));
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error(t('toast.delete_failed'));
    }
  }, [toast, t]);

  /* ───────── Poll Voting ───────── */

  const handleVotePoll = useCallback(async (pollId: number, optionId: number) => {
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
      toast.error(t('toast.vote_failed'));
    }
  }, [toast, t]);

  /* ───────── New-posts banner dismiss ───────── */

  const handleScrollToNewPosts = () => {
    setPendingPostCount(0);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const filterOptions: { key: FeedFilter; label: string }[] = [
    { key: 'all', label: t('filter.all') },
    { key: 'posts', label: t('filter.posts') },
    { key: 'listings', label: t('filter.listings') },
    { key: 'events', label: t('filter.events') },
    { key: 'polls', label: t('filter.polls') },
    { key: 'goals', label: t('filter.goals') },
    { key: 'jobs', label: t('filter.jobs', 'Jobs') },
    { key: 'challenges', label: t('filter.challenges', 'Challenges') },
    { key: 'volunteering', label: t('filter.volunteering', 'Volunteering') },
    { key: 'blogs', label: t('filter.blogs', 'Blog') },
    { key: 'discussions', label: t('filter.discussions', 'Discussions') },
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
    <>
    <PageMeta
      title={t("title")}
      description={t("subtitle")}
    />
    <div className="max-w-5xl mx-auto flex gap-6">
      {/* Main Feed Column */}
      <div className="flex-1 min-w-0 max-w-2xl space-y-5">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
              <Newspaper className="w-5 h-5 text-white" aria-hidden="true" />
            </div>
            {t('title')}
          </h1>
          <div className="flex items-center gap-2 mt-1">
            <p className="text-[var(--text-muted)] text-sm">{t('subtitle')}</p>
            <AlgorithmLabel area="feed" />
          </div>
        </div>

        {isAuthenticated && (
          <Button
            className="hidden sm:flex bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-shadow"
            startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            onPress={onCreateOpen}
          >
            {t('new_post')}
          </Button>
        )}
      </div>

      {/* Feed Mode Toggle (For You / Recent) */}
      <div className="flex items-center justify-between">
        <FeedModeToggle mode={feedMode} onModeChange={setFeedMode} />
        {isAuthenticated && (
          <Button
            size="sm"
            className="sm:hidden bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-md"
            startContent={<Plus className="w-3.5 h-3.5" aria-hidden="true" />}
            onPress={onCreateOpen}
          >
            {t('new_post')}
          </Button>
        )}
      </div>

      {/* Stories Bar */}
      {isAuthenticated && storiesFriends.length > 0 && (
        <StoriesBar friends={storiesFriends} />
      )}

      {/* Quick Post Box */}
      {isAuthenticated && (
        <GlassCard className="p-4 hover:border-[var(--color-primary)]/20 transition-colors cursor-pointer" onClick={() => { setComposeDefaultTab("listing"); onCreateOpen(); }}>
          <div className="flex items-center gap-3">
            <Avatar
              name={user?.first_name || 'You'}
              src={resolveAvatarUrl(user?.avatar)}
              size="sm"
              isBordered
              className="ring-2 ring-[var(--border-default)]"
            />
            <div className="flex-1 bg-[var(--surface-elevated)] rounded-full px-4 py-2.5 text-[var(--text-subtle)] text-sm border border-[var(--border-default)] hover:border-[var(--color-primary)]/30 transition-colors">
              {t('whats_on_your_mind')}
            </div>
            <div className="flex gap-1">
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--text-muted)]"
                onPress={() => { setComposeDefaultTab('post'); onCreateOpen(); }}
                aria-label={t('add_image_aria')}
              >
                <ImagePlus className="w-4 h-4" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-[var(--text-muted)]"
                onPress={() => { setComposeDefaultTab('poll'); onCreateOpen(); }}
                aria-label={t('create_poll_aria')}
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

      {/* Sub-Filter Chips (contextual, e.g. Listings -> Offers/Requests) */}
      <SubFilterChips filter={filter} subFilter={subFilter} onSubFilterChange={setSubFilter} />

      {/* New posts banner — appears when real-time posts arrive off-screen */}
      <AnimatePresence>
        {pendingPostCount > 0 && (
          <motion.div
            key="new-posts-banner"
            initial={{ opacity: 0, y: -12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            transition={{ duration: 0.2 }}
            className="sticky top-4 z-20 flex justify-center"
          >
            <Button
              onPress={handleScrollToNewPosts}
              className="flex items-center gap-2 px-4 py-2 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 text-white text-sm font-medium shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transition-shadow"
              startContent={<ArrowUp className="w-3.5 h-3.5" aria-hidden="true" />}
            >
              {pendingPostCount === 1
                ? t('realtime.new_post_singular')
                : t('realtime.new_posts_plural', { count: pendingPostCount })}
            </Button>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-10 text-center" glow="primary">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center mx-auto mb-5">
            <AlertTriangle className="w-8 h-8 text-amber-500" aria-hidden="true" />
          </div>
          <h2 className="text-lg font-semibold text-[var(--text-primary)] mb-2">{t('unable_to_load')}</h2>
          <p className="text-[var(--text-muted)] mb-5 text-sm max-w-xs mx-auto">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/20"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadFeed()}
          >
            {t('try_again')}
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
              <h2 className="text-lg font-semibold text-[var(--text-primary)] mb-2">{t('empty_title')}</h2>
              <p className="text-sm text-[var(--text-muted)] mb-6 max-w-xs mx-auto">
                {filter !== 'all'
                  ? t('empty_filtered', { filter })
                  : t('empty_desc')}
              </p>
              {isAuthenticated && (
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  onPress={onCreateOpen}
                >
                  {t('create_post')}
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
                      onToggleLike={handleToggleLike}
                      onHidePost={handleHidePost}
                      onMuteUser={handleMuteUser}
                      onReportPost={openReportModal}
                      onDeletePost={handleDeletePost}
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
                    {t('load_more')}
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
        onSuccess={(type) => {
          if (type === 'poll') {
            navigate(tenantPath('/polls'));
          } else {
            cursorRef.current = undefined;
            loadFeed();
          }
        }}
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
              {t('report.title')}
            </div>
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-[var(--text-muted)] mb-3">
              {t('report.description')}
            </p>
            <Textarea
              label={t('report.reason_label')}
              placeholder={t('report.reason_placeholder')}
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
              {t('report.cancel')}
            </Button>
            <Button
              color="danger"
              variant="flat"
              onPress={handleReport}
              isLoading={isReporting}
              isDisabled={!reportReason.trim()}
              className="font-medium"
            >
              {t('report.submit')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
      </div>

      {/* Right Sidebar — Full widget panel (hidden on mobile) */}
      <aside className="hidden lg:block w-72 flex-shrink-0">
        <SidebarErrorBoundary>
          <FeedSidebar />
        </SidebarErrorBoundary>
      </aside>
    </div>

    {/* Mobile FAB */}
    {isAuthenticated && <MobileFAB onPress={onCreateOpen} />}
    </>
  );
}

export default FeedPage;
