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

import React, { useState, useEffect, useCallback, useRef, Component, type ReactNode, type ErrorInfo, type KeyboardEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Textarea,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import Newspaper from 'lucide-react/icons/newspaper';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import ImagePlus from 'lucide-react/icons/image-plus';
import BarChart3 from 'lucide-react/icons/chart-column';
import TrendingUp from 'lucide-react/icons/trending-up';
import Flag from 'lucide-react/icons/flag';
import ArrowUp from 'lucide-react/icons/arrow-up';
import CircleX from 'lucide-react/icons/circle-x';
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
import { ConnectionSuggestionsWidget } from '@/components/feed/ConnectionSuggestionsWidget';
import { useAuth, useToast, usePusherOptional, useTenant } from '@/contexts';
import type { FeedPostEvent } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import { useInfiniteScroll } from '@/hooks/useInfiniteScroll';
import { usePullToRefresh } from '@/hooks/usePullToRefresh';
import { resetImpressions } from '@/hooks/useFeedTracking';
import { FeedCard } from '@/components/feed/FeedCard';
import { FeedSkeleton } from '@/components/feed/FeedSkeleton';
import { FeedEmptyIllustration } from '@/components/illustrations';
import type { FeedItem, FeedFilter, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';
import type { ReactionType } from '@/components/social';

/* ───────────────────────── Constants ───────────────────────── */

const SCROLL_THRESHOLD = 200;
const FEED_MODE_KEY = 'nexus_feed_mode';
const VALID_FEED_FILTERS: FeedFilter[] = ['all', 'following', 'saved', 'posts', 'listings', 'events', 'polls', 'goals', 'jobs', 'challenges', 'volunteering', 'blogs', 'discussions'];
const FILTERS_WITH_SUBFILTERS = new Set<FeedFilter>(['listings']);

function coerceFeedFilter(value: string | null): FeedFilter {
  return value && VALID_FEED_FILTERS.includes(value as FeedFilter)
    ? (value as FeedFilter)
    : 'all';
}

function feedItemMatchesFilter(item: FeedItem, activeFilter: FeedFilter): boolean {
  switch (activeFilter) {
    case 'all':
      return true;
    case 'posts':
      return item.type === 'post';
    case 'listings':
      return item.type === 'listing';
    case 'events':
      return item.type === 'event';
    case 'polls':
      return item.type === 'poll';
    case 'goals':
      return item.type === 'goal';
    case 'jobs':
      return item.type === 'job';
    case 'challenges':
      return item.type === 'challenge';
    case 'volunteering':
      return item.type === 'volunteer';
    case 'blogs':
      return item.type === 'blog';
    case 'discussions':
      return item.type === 'discussion';
    case 'saved':
      return item.is_bookmarked === true;
    case 'following':
      return false;
    default: {
      const _exhaustiveCheck: never = activeFilter;
      void _exhaustiveCheck;
      return false;
    }
  }
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
  const [searchParams, setSearchParams] = useSearchParams();
  const { tenantPath, tenant, hasFeature } = useTenant();
  const isAdmin = user?.is_admin === true || user?.role === 'admin' || user?.role === 'tenant_admin' || user?.role === 'super_admin' || user?.is_super_admin === true;
  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Initialize filter from URL param, fallback to 'all'
  const [filter, setFilter] = useState<FeedFilter>(() => {
    return coerceFeedFilter(searchParams.get('filter'));
  });

  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  // Count of real-time posts received while the user hasn't scrolled to top
  const [pendingPostCount, setPendingPostCount] = useState(0);
  // Whether the user is scrolled past the top of the feed
  const [isScrolledDown, setIsScrolledDown] = useState(false);
  // Buffer for real-time posts received while scrolled down
  const pendingPostsRef = useRef<FeedItem[]>([]);

  // Feed mode: EdgeRank vs chronological — persisted in URL + localStorage
  const [feedMode, setFeedMode] = useState<'ranking' | 'recent'>(() => {
    const urlMode = searchParams.get('mode');
    if (urlMode === 'ranking' || urlMode === 'recent') return urlMode;
    const stored = localStorage.getItem(FEED_MODE_KEY);
    return stored === 'ranking' || stored === 'recent' ? stored : 'ranking';
  });

  // Sub-filter (e.g. listings -> offers/requests) — synced to URL
  const [subFilter, setSubFilter] = useState<string | null>(() => {
    const initialFilter = coerceFeedFilter(searchParams.get('filter'));
    return FILTERS_WITH_SUBFILTERS.has(initialFilter) ? searchParams.get('subFilter') || null : null;
  });

  // Compose Hub
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [composeDefaultTab, setComposeDefaultTab] = useState<ComposeTab>('post');
  const openCompose = (tab: ComposeTab = 'post') => { setComposeDefaultTab(tab); onCreateOpen(); };

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  // Use a ref for cursor to avoid infinite re-render loop
  const cursorRef = useRef<string | undefined>();

  // Separate AbortControllers: initial/filter loads vs load-more appends
  // This prevents a fresh load from aborting an in-flight append (or vice versa)
  const abortRef = useRef<AbortController | null>(null);
  const appendAbortRef = useRef<AbortController | null>(null);

  // Sync filter, mode, and subFilter to URL query string without polluting history
  const syncToUrl = useCallback((updates: { filter?: FeedFilter; mode?: 'ranking' | 'recent'; subFilter?: string | null }) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev);
      if (updates.filter !== undefined) {
        if (updates.filter === 'all') next.delete('filter');
        else next.set('filter', updates.filter);
        if (!FILTERS_WITH_SUBFILTERS.has(updates.filter)) next.delete('subFilter');
      }
      if (updates.mode !== undefined) {
        if (updates.mode === 'ranking') next.delete('mode');
        else next.set('mode', updates.mode);
      }
      if (updates.subFilter !== undefined) {
        if (!updates.subFilter) next.delete('subFilter');
        else next.set('subFilter', updates.subFilter);
      }
      return next;
    }, { replace: true });
  }, [setSearchParams]);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  // Stable ref for loadFeed — avoids including it in useEffect deps which causes reset loops
  const loadFeedRef = useRef<(append?: boolean) => Promise<void>>(null!);

  // Stable ref for filter — prevents stale closure in Pusher callback (M5)
  const filterRef = useRef(filter);
  useEffect(() => { filterRef.current = filter; }, [filter]);

  // Stable ref for feedMode — prevents stale closure in Pusher callback
  const feedModeRef = useRef(feedMode);
  useEffect(() => { feedModeRef.current = feedMode; }, [feedMode]);

  // Infinite scroll: auto-load when sentinel nears viewport
  const handleLoadMore = useCallback(() => { loadFeedRef.current?.(true); }, []);
  const infiniteScrollRef = useInfiniteScroll({
    hasMore,
    isLoading: isLoadingMore,
    onLoadMore: handleLoadMore,
  });

  // Pull-to-refresh: touch gesture on mobile
  const handlePullRefresh = useCallback(async () => { await loadFeedRef.current?.(); }, []);
  const { pullDistance, isRefreshing } = usePullToRefresh({
    onRefresh: handlePullRefresh,
    enabled: !isLoading,
  });


  const loadFeed = useCallback(async (append = false) => {
    // Use separate abort controllers so fresh loads don't cancel appends
    const ref = append ? appendAbortRef : abortRef;
    ref.current?.abort();
    const controller = new AbortController();
    ref.current = controller;

    try {
      if (append) {
        setIsLoadingMore(true);
      } else {
        // Fresh load — also cancel any in-flight append to avoid stale data
        appendAbortRef.current?.abort();
        setIsLoading(true);
        setError(null);
        // Clear impression dedup set so each post fires again on the new load
        resetImpressions();
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      params.set('mode', feedMode === 'ranking' ? 'ranked' : 'chronological');
      params.set('tz', Intl.DateTimeFormat().resolvedOptions().timeZone);
      if (filter !== 'all') params.set('type', filter);
      if (subFilter) params.set('subtype', subFilter);
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);

      const response = await api.get<FeedItem[]>(
        `/v2/feed?${params}`
      );

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        const feedItems = Array.isArray(response.data) ? response.data : [];

        if (append) {
          // Deduplicate: prevent double-rendering if API returns overlapping items
          setItems((prev) => {
            const existingKeys = new Set(prev.map((p) => `${p.type}-${p.id}`));
            const newItems = feedItems.filter((fi) => !existingKeys.has(`${fi.type}-${fi.id}`));
            return [...prev, ...newItems];
          });
        } else {
          setItems(feedItems);
        }
        setHasMore(response.meta?.has_more ?? false);
        cursorRef.current = response.meta?.cursor ?? undefined;
      } else {
        if (!append) {
          if (response.code === 'SESSION_EXPIRED') {
            navigate(tenantPath('/login'));
          } else {
            setError(tRef.current('error_load'));
          }
        }
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load feed', err);
      if (!append) {
        setError(tRef.current('error_load_retry'));
        // M9: Reset pending post count so stale buffered posts are discarded on failure
        setPendingPostCount(0);
        pendingPostsRef.current = [];
      }
    } finally {
      if (!controller.signal.aborted) {
        if (append) {
          setIsLoadingMore(false);
        } else {
          setIsLoading(false);
        }
      }
    }
  }, [filter, feedMode, subFilter, navigate, tenantPath]);

  // Keep loadFeedRef in sync with latest loadFeed
  loadFeedRef.current = loadFeed;

  // Track previous filter to detect filter changes and auto-reset subFilter
  const prevFilterRef = useRef(filter);
  useEffect(() => {
    // If the main filter changed and a subFilter was set, reset it.
    // Only early-return when the reset actually triggers a re-render — if subFilter
    // was already null, setSubFilter(null) is a no-op and the effect would never
    // re-fire, leaving loadFeed uncalled (the bug that made filter clicks do nothing).
    if (prevFilterRef.current !== filter) {
      prevFilterRef.current = filter;
      if (subFilter !== null) {
        setSubFilter(null);
        return;
      }
    }
    cursorRef.current = undefined;
    setPendingPostCount(0);
    pendingPostsRef.current = [];
    loadFeedRef.current();
    const currentAbort = abortRef.current;
    const currentAppendAbort = appendAbortRef.current;
    return () => { currentAbort?.abort(); currentAppendAbort?.abort(); };
  }, [filter, feedMode, subFilter]); // loadFeed via ref to avoid dependency loop

  /* ───────── Scroll position tracking ───────── */

  useEffect(() => {
    let ticking = false;

    const handleScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const scrolledDown = window.scrollY > SCROLL_THRESHOLD;
        setIsScrolledDown(scrolledDown);

        // If user scrolled back to top naturally, flush buffered posts.
        // In ranking mode we don't auto-flush on scroll — the banner stays visible
        // so the user can choose to reload (triggering EdgeRank re-sort) by tapping it.
        if (!scrolledDown && pendingPostsRef.current.length > 0 && feedModeRef.current === 'recent') {
          const buffered = [...pendingPostsRef.current];
          pendingPostsRef.current = [];
          setPendingPostCount(0);
          const currentFilter = filterRef.current;
          setItems((prev) => {
            const existingKeys = new Set(prev.map((p) => `${p.type}-${p.id}`));
            const newItems = buffered
              .filter((fi) => !existingKeys.has(`${fi.type}-${fi.id}`))
              .filter((fi) => feedItemMatchesFilter(fi, currentFilter));
            return [...newItems, ...prev];
          });
        }
        ticking = false;
      });
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  /* ───────── Real-time feed subscription ───────── */

  useEffect(() => {
    if (!pusher || isLoading) return;

    const unsub = pusher.onFeedPost((event: FeedPostEvent) => {
      const incoming = event.post;

      // C3: Validate tenant_id — discard posts from other tenants or events without a tenant_id
      if (!incoming.tenant_id || incoming.tenant_id !== tenant?.id) return;

      // If the post was created by the current user it is already prepended
      // optimistically by ComposeHub / the onSuccess reload, so skip it.
      if (user?.id && incoming.author?.id === user.id) return;

      // Only surface posts that match the active filter.
      // M5: Use filterRef.current so the closure always has the latest filter value.
      const currentFilter = filterRef.current;
      const matchesFilter = feedItemMatchesFilter(incoming, currentFilter);

      if (!matchesFilter) return;

      // In ranking mode, never blindly prepend real-time posts — the EdgeRank algorithm
      // determines order, so live posts should be surfaced via the "new posts" banner
      // which triggers a full reload. Always buffer in ranking mode.
      if (feedModeRef.current === 'ranking' || window.scrollY > SCROLL_THRESHOLD) {
        pendingPostsRef.current = [incoming, ...pendingPostsRef.current];
        setPendingPostCount((n) => n + 1);
        return;
      }

      // Chronological mode at top of feed — prepend directly
      setItems((prev) => {
        // Guard against duplicates (e.g. race between Pusher event and manual reload)
        if (prev.some((p) => p.type === incoming.type && p.id === incoming.id)) {
          return prev;
        }
        return [incoming, ...prev];
      });
    });

    return unsub;
  }, [pusher, isLoading, user?.id, tenant?.id]);

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
      toastRef.current.error(tRef.current('toast.like_failed'));
    }
  }, []);

  /* ───────── Emoji Reaction ───────── */

  const handleReact = useCallback(async (item: FeedItem, reactionType: ReactionType) => {
    // Optimistic update
    const prevReactions = item.reactions ?? { counts: {}, total: 0, user_reaction: null, top_reactors: [] };
    const currentReaction = prevReactions.user_reaction;
    const newCounts = { ...prevReactions.counts };
    let newTotal = prevReactions.total;
    let newUserReaction: string | null;

    if (currentReaction === reactionType) {
      // Remove reaction
      newCounts[reactionType] = Math.max(0, (newCounts[reactionType] ?? 0) - 1);
      if (newCounts[reactionType] === 0) delete newCounts[reactionType];
      newTotal = Math.max(0, newTotal - 1);
      newUserReaction = null;
    } else {
      // Switch or add reaction
      if (currentReaction) {
        newCounts[currentReaction] = Math.max(0, (newCounts[currentReaction] ?? 0) - 1);
        if (newCounts[currentReaction] === 0) delete newCounts[currentReaction];
      } else {
        newTotal += 1;
      }
      newCounts[reactionType] = (newCounts[reactionType] ?? 0) + 1;
      newUserReaction = reactionType;
    }

    setItems((prev) =>
      prev.map((fi) =>
        fi.id === item.id && fi.type === item.type
          ? { ...fi, reactions: { ...prevReactions, counts: newCounts, total: newTotal, user_reaction: newUserReaction } }
          : fi
      )
    );

    try {
      const res = await api.post(`/v2/posts/${item.id}/reactions`, { reaction_type: reactionType });
      const resData = res.data as Record<string, unknown> | undefined;
      if (resData?.reactions) {
        const serverReactions = resData.reactions as FeedItem['reactions'];
        setItems((prev) =>
          prev.map((fi) =>
            fi.id === item.id && fi.type === item.type
              ? { ...fi, reactions: serverReactions }
              : fi
          )
        );
      }
    } catch (err) {
      logError('Failed to react', err);
      // Revert on error
      setItems((prev) =>
        prev.map((fi) =>
          fi.id === item.id && fi.type === item.type
            ? { ...fi, reactions: prevReactions }
            : fi
        )
      );
    }
  }, []);

  /* ───────── Moderation ───────── */

  const handleHidePost = useCallback(async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/hide`, { type: item.type });
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toastRef.current.success(tRef.current('toast.post_hidden'));
    } catch (err) {
      logError('Failed to hide post', err);
      toastRef.current.error(tRef.current('toast.hide_failed'));
    }
  }, []);

  const handleMuteUser = useCallback(async (item: FeedItem) => {
    const userId = getAuthor(item).id;
    try {
      await api.post(`/v2/feed/users/${userId}/mute`);
      setItems((prev) => prev.filter((fi) => getAuthor(fi).id !== userId));
      toastRef.current.success(tRef.current('toast.user_muted'));
    } catch (err) {
      logError('Failed to mute user', err);
      toastRef.current.error(tRef.current('toast.mute_failed'));
    }
  }, []);

  const openReportModal = useCallback((item: FeedItem) => {
    setReportPostId(item.id);
    setReportReason('');
    onReportOpen();
  }, [onReportOpen]);

  const handleReport = useCallback(async () => {
    if (!reportPostId || !reportReason.trim()) {
      toastRef.current.error(tRef.current('toast.provide_reason'));
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
      toastRef.current.success(tRef.current('toast.reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toastRef.current.error(tRef.current('toast.report_failed'));
    } finally {
      setIsReporting(false);
    }
  }, [reportPostId, reportReason, onReportClose]);

  const handleDeletePost = useCallback(async (item: FeedItem) => {
    if (item.type !== 'post') return;
    try {
      await api.post(`/v2/feed/posts/${item.id}/delete`);
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toastRef.current.success(tRef.current('toast.deleted'));
    } catch (err) {
      logError('Failed to delete post', err);
      toastRef.current.error(tRef.current('toast.delete_failed'));
    }
  }, []);

  const handleAdminDeletePost = useCallback(async (item: FeedItem) => {
    try {
      const sourceType = item.type || 'post';
      await api.delete(`/v2/admin/feed/posts/${item.id}?type=${encodeURIComponent(sourceType)}`);
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
      toastRef.current.success(tRef.current('toast.deleted'));
    } catch (err) {
      logError('Failed to admin-delete post', err);
      toastRef.current.error(tRef.current('toast.delete_failed'));
    }
  }, []);

  /* ───────── Edit Post ───────── */

  const [editingItem, setEditingItem] = useState<FeedItem | null>(null);

  const handleEditPost = useCallback((item: FeedItem) => {
    setEditingItem(item);
    setComposeDefaultTab('post');
    onCreateOpen();
  }, [onCreateOpen]);

  const handleEditSuccess = useCallback((updatedItem: FeedItem) => {
    // Merge server response into existing item to preserve engagement counts, reactions, etc.
    setItems((prev) =>
      prev.map((fi) =>
        fi.id === updatedItem.id && (fi.type === updatedItem.type || updatedItem.type === undefined)
          ? { ...fi, content: updatedItem.content, updated_at: updatedItem.updated_at ?? new Date().toISOString() }
          : fi
      )
    );
    setEditingItem(null);
    onCreateClose();
  }, [onCreateClose]);

  const handleComposeClose = useCallback(() => {
    setEditingItem(null);
    onCreateClose();
  }, [onCreateClose]);

  /* ───────── Not Interested ───────── */

  const handleNotInterested = useCallback(async (item: FeedItem) => {
    // Optimistic removal — revert by re-inserting on failure
    setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
    try {
      await api.post(`/v2/feed/posts/${item.id}/not-interested`, { type: item.type });
      toastRef.current.success(tRef.current('toast.not_interested'));
    } catch (err) {
      logError('Failed to record not-interested', err);
      // Revert: re-insert the item (may not be in original position, but that's acceptable)
      setItems((prev) => {
        if (prev.some((fi) => fi.id === item.id && fi.type === item.type)) return prev;
        return [item, ...prev];
      });
      toastRef.current.error(tRef.current('toast.hide_failed'));
    }
  }, []);

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
      toastRef.current.error(tRef.current('toast.vote_failed'));
    }
  }, []);

  /* ───────── New-posts banner dismiss ───────── */

  const isScrollingRef = useRef(false);

  const handleScrollToNewPosts = useCallback(() => {
    if (isScrollingRef.current) return;
    isScrollingRef.current = true;
    setPendingPostCount(0);

    if (feedModeRef.current === 'ranking') {
      // In ranking mode, trigger a full reload so EdgeRank re-scores and re-orders
      pendingPostsRef.current = [];
      cursorRef.current = undefined;
      loadFeedRef.current();
    } else {
      // Chronological mode — flush buffered posts directly to the top
      const buffered = [...pendingPostsRef.current];
      pendingPostsRef.current = [];
      const currentFilter = filterRef.current;
      if (buffered.length > 0) {
        setItems((prev) => {
          const existingKeys = new Set(prev.map((p) => `${p.type}-${p.id}`));
          const newItems = buffered
            .filter((fi) => !existingKeys.has(`${fi.type}-${fi.id}`))
            .filter((fi) => feedItemMatchesFilter(fi, currentFilter));
          return [...newItems, ...prev];
        });
      }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
    setTimeout(() => { isScrollingRef.current = false; }, 1000);
  }, []);

  const handleFilterChange = useCallback((nextFilter: FeedFilter) => {
    setFilter(nextFilter);
    if (!FILTERS_WITH_SUBFILTERS.has(nextFilter)) {
      setSubFilter(null);
    }
    syncToUrl({
      filter: nextFilter,
      subFilter: FILTERS_WITH_SUBFILTERS.has(nextFilter) ? undefined : null,
    });
  }, [syncToUrl]);

  const clearFeedControls = useCallback(() => {
    setFilter('all');
    setSubFilter(null);
    syncToUrl({ filter: 'all', subFilter: null });
  }, [syncToUrl]);

  const filterOptions: { key: FeedFilter; label: string }[] = [
    { key: 'all', label: t('filter.all') },
    ...(user ? [
      { key: 'following' as FeedFilter, label: t('filter.following') },
      { key: 'saved' as FeedFilter, label: t('filter.saved') },
    ] : []),
    { key: 'posts', label: t('filter.posts') },
    { key: 'listings', label: t('filter.listings') },
    { key: 'events', label: t('filter.events') },
    ...(hasFeature('polls') ? [{ key: 'polls' as FeedFilter, label: t('filter.polls') }] : []),
    { key: 'goals', label: t('filter.goals') },
    { key: 'jobs', label: t('filter.jobs') },
    { key: 'challenges', label: t('filter.challenges') },
    { key: 'volunteering', label: t('filter.volunteering') },
    { key: 'blogs', label: t('filter.blogs') },
    { key: 'discussions', label: t('filter.discussions') },
  ];

  const hasActiveFeedView = filter !== 'all' || subFilter !== null;


  return (
    <>
    <PageMeta
      title={t("title")}
      description={t("subtitle")}
      noIndex
    />
    <div className="max-w-6xl mx-auto flex gap-6">
      {/* Main Feed Column */}
      <div className="flex-1 min-w-0 max-w-2xl space-y-5">

      {/* Pull-to-refresh indicator (mobile only) */}
      {(pullDistance > 0 || isRefreshing) && (
        <div className="flex h-12 items-center justify-center overflow-hidden sm:hidden">
          <RefreshCw
            className={`w-5 h-5 text-primary transition-opacity ${isRefreshing || pullDistance > 48 ? 'animate-spin opacity-100' : 'opacity-60'}`}
            aria-hidden="true"
          />
        </div>
      )}

      {/* Compact page header */}
      <div className="flex flex-col gap-3 rounded-xl border border-theme-default bg-theme-elevated/70 px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <Newspaper className="w-5 h-5" aria-hidden="true" />
            </span>
            <div className="min-w-0">
              <h1 className="truncate text-xl font-semibold text-theme-primary sm:text-2xl">{t('title')}</h1>
              <p className="text-sm text-theme-muted">{t('subtitle')}</p>
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {!isLoading && items.length > 0 && (
            <Chip
              size="sm"
              variant="flat"
              className="hidden bg-primary/10 text-primary sm:inline-flex"
            >
              {t('items_loaded', { count: items.length })}
            </Chip>
          )}
          <AlgorithmLabel area="feed" />
          {isAuthenticated && (
            <Button
              className="hidden bg-primary text-white shadow-sm sm:flex"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              onPress={() => openCompose('post')}
            >
              {t('new_post')}
            </Button>
          )}
        </div>
      </div>

      {/* Feed controls */}
      <div className="sticky top-[72px] z-30 w-full min-w-0 max-w-full space-y-2 overflow-hidden rounded-xl border border-theme-default bg-[var(--surface-base)]/95 px-3 py-3 shadow-sm backdrop-blur-md">
        <div className="flex min-w-0 items-center justify-between gap-3">
          <FeedModeToggle mode={feedMode} onModeChange={(mode) => { localStorage.setItem(FEED_MODE_KEY, mode); setFeedMode(mode); syncToUrl({ mode }); }} />
          {hasActiveFeedView && (
            <Button
              isIconOnly
              size="sm"
              variant="light"
              className="text-theme-muted hover:text-primary"
              onPress={clearFeedControls}
              aria-label={t('filter.clear')}
            >
              <CircleX className="h-4 w-4" aria-hidden="true" />
            </Button>
          )}
        </div>

        <div className="-mx-1 w-[calc(100%+0.5rem)] min-w-0 overflow-x-auto px-1 pb-1 sm:mx-0 sm:w-full sm:overflow-visible sm:px-0">
          <div className="flex min-w-max gap-2 sm:min-w-0 sm:flex-wrap">
            {filterOptions.map((opt) => (
              <Button
                key={opt.key}
                size="sm"
                variant={filter === opt.key ? 'solid' : 'flat'}
                radius="full"
                aria-pressed={filter === opt.key}
                className={`shrink-0 ${
                  filter === opt.key
                    ? 'bg-primary text-white shadow-sm'
                    : 'bg-theme-elevated text-theme-muted hover:text-primary hover:bg-primary/5 border border-theme-default transition-colors'
                }`}
                onPress={() => handleFilterChange(opt.key)}
              >
                {opt.label}
              </Button>
            ))}
          </div>
        </div>

        {/* Sub-Filter Chips (contextual, e.g. Listings -> Offers/Requests) */}
        <SubFilterChips filter={filter} subFilter={subFilter} onSubFilterChange={(sf) => { setSubFilter(sf); syncToUrl({ subFilter: sf }); }} />
      </div>

      {/* Stories Bar — loads its own data from /v2/stories */}
      {isAuthenticated && (
        <StoriesBar />
      )}

      {/* Quick Post Box */}
      {isAuthenticated && (
        <GlassCard className="p-4 hover:border-primary/20 transition-colors">
          <div
            className="flex items-center gap-3 cursor-pointer"
            role="button"
            tabIndex={0}
            aria-label={t('whats_on_your_mind')}
            onClick={() => openCompose('post')}
            onKeyDown={(e: KeyboardEvent<HTMLDivElement>) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCompose('post'); } }}
          >
            <Avatar
              name={user?.first_name || t('you')}
              src={resolveAvatarUrl(user?.avatar)}
              size="sm"
              isBordered
              className="ring-2 ring-[var(--border-default)]"
            />
            <div className="flex-1 bg-theme-elevated rounded-full px-4 py-2.5 text-theme-subtle text-sm border border-theme-default hover:border-primary/30 transition-colors">
              {t('whats_on_your_mind')}
            </div>
            <div className="flex gap-1">
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-theme-muted"
                onPress={() => openCompose('post')}
                aria-label={t('add_image_aria')}
              >
                <ImagePlus className="w-4 h-4" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="text-theme-muted"
                onPress={() => openCompose('poll')}
                aria-label={t('create_poll_aria')}
              >
                <BarChart3 className="w-4 h-4" />
              </Button>
            </div>
          </div>
        </GlassCard>
      )}

      {/* New posts floating chip — appears when real-time posts arrive while scrolled down */}
      <AnimatePresence>
        {pendingPostCount > 0 && (feedMode === 'ranking' || isScrolledDown) && (
          <motion.div
            key="new-posts-chip"
            initial={{ opacity: 0, y: -20, scale: 0.9 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -20, scale: 0.9 }}
            transition={{ type: 'spring', stiffness: 300, damping: 25 }}
            className="fixed top-20 left-1/2 -translate-x-1/2 z-50"
            role="status"
            aria-live="polite"
          >
            <Chip
              as="button"
              color="primary"
              variant="shadow"
              size="lg"
              classNames={{
                base: 'cursor-pointer bg-gradient-to-r from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transition-shadow px-4 py-2 h-auto',
                content: 'flex items-center gap-2 text-white font-medium text-sm',
              }}
              startContent={<ArrowUp className="w-3.5 h-3.5 text-white" aria-hidden="true" />}
              onClick={handleScrollToNewPosts}
            >
              {t('realtime.new_posts', { count: pendingPostCount })}
            </Chip>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-10 text-center" glow="primary" role="alert">
          <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-500/20 to-orange-500/20 flex items-center justify-center mx-auto mb-5">
            <AlertTriangle className="w-8 h-8 text-amber-500" aria-hidden="true" />
          </div>
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-5 text-sm max-w-xs mx-auto">{error}</p>
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
              {[0, 1, 2].map((i) => (
                <FeedSkeleton key={i} index={i} />
              ))}
            </div>
          ) : items.length === 0 ? (
            <GlassCard className="p-12 text-center">
              <div className="mx-auto mb-6">
                <FeedEmptyIllustration className="w-32 h-32 mx-auto" />
              </div>
              <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('empty_title')}</h2>
              <p className="text-sm text-theme-muted mb-6 max-w-xs mx-auto">
                {filter !== 'all'
                  ? t('empty_filtered')
                  : t('empty_desc')}
              </p>
              {isAuthenticated && (
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25"
                  startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => openCompose('post')}
                >
                  {t('create_post')}
                </Button>
              )}
            </GlassCard>
          ) : (
            <motion.div
              key={`${filter}-${subFilter ?? ''}-${feedMode}`}
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ duration: 0.2 }}
              className="space-y-4"
            >
              <AnimatePresence>
                {items.map((item, index) => (
                  <React.Fragment key={`${item.type}-${item.id}`}>
                    <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, height: 0, overflow: 'hidden' }} transition={{ duration: 0.25, delay: Math.min(index * 0.04, 0.4) }}>
                      <FeedCard
                        item={item}
                        onToggleLike={handleToggleLike}
                        onReact={handleReact}
                        onHidePost={handleHidePost}
                        onMuteUser={handleMuteUser}
                        onReportPost={openReportModal}
                        onDeletePost={handleDeletePost}
                        onAdminDeletePost={isAdmin ? handleAdminDeletePost : undefined}
                        onEditPost={handleEditPost}
                        onNotInterested={handleNotInterested}
                        onVotePoll={handleVotePoll}
                        feedMode={feedMode}
                        isAuthenticated={isAuthenticated}
                        currentUserId={user?.id}
                        isAdmin={isAdmin}
                      />
                    </motion.div>
                    {/* Show connection suggestions inline on mobile after every 10 posts */}
                    {index === 9 && isAuthenticated && (
                      <div className="lg:hidden">
                        <ConnectionSuggestionsWidget layout="inline" />
                      </div>
                    )}
                  </React.Fragment>
                ))}
              </AnimatePresence>

              {/* Infinite scroll sentinel — triggers auto-load before user reaches bottom */}
              {hasMore && <div ref={infiniteScrollRef} className="h-1" aria-hidden="true" />}

              {/* End-of-feed message */}
              {!hasMore && items.length > 0 && !isLoading && (
                <div className="text-center py-10">
                  <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-theme-elevated text-theme-muted text-sm border border-theme-default">
                    <span className="w-1.5 h-1.5 rounded-full bg-indigo-400/60" aria-hidden="true" />
                    {t('feed.end_of_feed')}
                    <span className="w-1.5 h-1.5 rounded-full bg-purple-400/60" aria-hidden="true" />
                  </span>
                </div>
              )}

              {/* Manual fallback: visible only when more items exist but NOT currently loading */}
              {hasMore && !isLoadingMore && (
                <div className="pt-6 pb-2 text-center">
                  <Button
                    variant="bordered"
                    className="border-theme-default text-theme-muted hover:border-primary hover:text-primary transition-colors"
                    onPress={() => loadFeed(true)}
                    startContent={<TrendingUp className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('load_more')}
                  </Button>
                </div>
              )}

              {/* Pagination loading skeletons */}
              {isLoadingMore && (
                <div className="space-y-4 pt-2">
                  {[0, 1].map((i) => (
                    <FeedSkeleton key={`load-more-skeleton-${i}`} index={i} />
                  ))}
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Compose Hub */}
      <ComposeHub
        isOpen={isCreateOpen}
        onClose={handleComposeClose}
        defaultTab={composeDefaultTab}
        editItem={editingItem}
        onEditSuccess={handleEditSuccess}
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
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-lg bg-danger/10 flex items-center justify-center">
                <Flag className="w-4 h-4 text-danger" aria-hidden="true" />
              </div>
              {t('report.title')}
            </div>
          </ModalHeader>
          <ModalBody>
            <p className="text-sm text-theme-muted mb-3">
              {t('report.description')}
            </p>
            <Textarea
              label={t('report.reason_label')}
              placeholder={t('report.reason_placeholder')}
              value={reportReason}
              onChange={(e) => setReportReason(e.target.value)}
              minRows={3}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
              autoFocus
            />
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={onReportClose}
              className="text-theme-muted"
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
        <div className="sticky top-20">
          <SidebarErrorBoundary>
            <FeedSidebar />
          </SidebarErrorBoundary>
        </div>
      </aside>
    </div>

    {/* Mobile FAB */}
    {isAuthenticated && <MobileFAB onPress={() => openCompose('post')} />}
    </>
  );
}

export default FeedPage;
