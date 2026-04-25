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
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import Hash from 'lucide-react/icons/hash';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import TrendingUp from 'lucide-react/icons/trending-up';
import Sparkles from 'lucide-react/icons/sparkles';
import Flag from 'lucide-react/icons/flag';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { EmptyState } from '@/components/feedback';
import { FeedCard } from '@/components/feed/FeedCard';
import { FeedSkeleton } from '@/components/feed/FeedSkeleton';
import type { FeedItem, PollData } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

export function HashtagPage() {
  const { t } = useTranslation('feed');
  const { tag } = useParams<{ tag: string }>();
  const { tenantPath } = useTenant();
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  usePageTitle(tag ? `#${tag}` : t('hashtag.title'));

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  const [items, setItems] = useState<FeedItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [postCount, setPostCount] = useState(0);
  const cursorRef = useRef<string | undefined>();
  const abortRef = useRef<AbortController | null>(null);
  const tRef = useRef(t);
  tRef.current = t;

  const loadPosts = useCallback(async (append = false) => {
    if (!tag) return;

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

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
      if (controller.signal.aborted) return;

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
        if (!append) setError(tRef.current('hashtag.load_failed'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load hashtag posts', err);
      if (!append) setError(tRef.current('hashtag.load_failed'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  }, [tag]);

  const loadPostsRef = useRef(loadPosts);
  loadPostsRef.current = loadPosts;

  useEffect(() => {
    cursorRef.current = undefined;
    loadPostsRef.current(true);
    return () => { abortRef.current?.abort(); };
  }, [tag]);

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

  const handleHidePost = async (item: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${item.id}/hide`, { type: item.type });
      setItems((prev) => prev.filter((fi) => !(fi.id === item.id && fi.type === item.type)));
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

  const openReportModal = useCallback((item: FeedItem) => {
    setReportPostId(item.id);
    setReportReason('');
    onReportOpen();
  }, [onReportOpen]);

  const handleReport = async () => {
    if (!reportPostId || !reportReason.trim()) {
      toast.error(t('toast.provide_reason', 'Please provide a reason'));
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
      toast.success(t('toast.reported', 'Post reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toast.error(t('toast.report_failed', 'Failed to report post'));
    } finally {
      setIsReporting(false);
    }
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
          <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
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
              {[0, 1, 2].map((i) => <FeedSkeleton key={i} index={i} />)}
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              icon={<Sparkles className="w-12 h-12" aria-hidden="true" />}
              title={t('hashtag.no_posts')}
              description={t('hashtag.no_posts_desc', { tag })}
            />
          ) : (
            <div className="space-y-4">
              <AnimatePresence mode="popLayout">
                {items.map((item) => (
                  <motion.div key={`${item.type}-${item.id}`} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }} layout>
                    <FeedCard
                      item={item}
                      onToggleLike={() => handleToggleLike(item)}
                      onHidePost={() => handleHidePost(item)}
                      onMuteUser={() => handleMuteUser(getAuthor(item).id)}
                      onReportPost={openReportModal}
                      onDeletePost={() => handleDeletePost(item)}
                      onVotePoll={handleVotePoll}
                      feedMode="recent"
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
            </div>
          )}
        </>
      )}
    </div>

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
            {t('report.title', 'Report Post')}
          </div>
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-[var(--text-muted)] mb-3">
            {t('report.description', 'Please describe why you are reporting this post.')}
          </p>
          <Textarea
            label={t('report.reason_label', 'Reason')}
            placeholder={t('report.reason_placeholder', 'Describe the issue...')}
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
            {t('report.cancel', 'Cancel')}
          </Button>
          <Button
            color="danger"
            variant="flat"
            onPress={handleReport}
            isLoading={isReporting}
            isDisabled={!reportReason.trim()}
            className="font-medium"
          >
            {t('report.submit', 'Report')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
    </>
  );
}

export default HashtagPage;
