// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostDetailPage - View a single feed item by ID.
 *
 * Supports two routes:
 *   /feed/posts/:id            — legacy, treats target as a feed post
 *   /feed/item/:type/:id       — polymorphic (listing/event/poll/goal/etc.)
 *
 * APIs:
 *   GET /api/v2/feed/posts/{id}            (post-only, legacy)
 *   GET /api/v2/feed/items/{type}/{id}     (any reactable feed item)
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, Link, useNavigate, useSearchParams } from 'react-router-dom';
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
import ArrowLeft from 'lucide-react/icons/arrow-left';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Flag from 'lucide-react/icons/flag';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { FeedCard } from '@/components/feed/FeedCard';
import { FeedSkeleton } from '@/components/feed/FeedSkeleton';
import type { FeedItem } from '@/components/feed/types';
import { getAuthor } from '@/components/feed/types';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { dispatchFeedSync } from '@/lib/feedSync';

// Reactable feed-item types supported by the polymorphic backend endpoint.
// Must stay in sync with the allowlist in SocialController::showItem.
const POLYMORPHIC_TYPES = new Set<FeedItem['type']>([
  'post', 'listing', 'event', 'poll', 'goal', 'review',
  'volunteer', 'challenge', 'blog', 'discussion', 'job',
]);

export function PostDetailPage() {
  const { t } = useTranslation('feed');
  const { id, type: typeParam } = useParams<{ id: string; type?: string }>();
  const [searchParams] = useSearchParams();
  const { tenantPath } = useTenant();
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  usePageTitle(t('post_detail.title'));

  // Resolve target type from either route segment (/feed/item/:type/:id)
  // or query string (?type=listing on the legacy /feed/posts/:id route).
  // Falls back to 'post' for backward compat with the original detail URL.
  const queryType = searchParams.get('type');
  const rawType = typeParam ?? queryType ?? 'post';
  const itemType: FeedItem['type'] = POLYMORPHIC_TYPES.has(rawType as FeedItem['type'])
    ? (rawType as FeedItem['type'])
    : 'post';

  // Report modal
  const { isOpen: isReportOpen, onOpen: onReportOpen, onClose: onReportClose } = useDisclosure();
  const [reportPostId, setReportPostId] = useState<number | null>(null);
  const [reportReason, setReportReason] = useState('');
  const [isReporting, setIsReporting] = useState(false);

  const [item, setItem] = useState<FeedItem | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (!id) return;

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setIsLoading(true);
    setError(null);

    // Use polymorphic endpoint for non-post types (listings, events, polls, etc.)
    // Posts continue to hit the existing /v2/feed/posts/{id} route for back-compat.
    const url = itemType === 'post'
      ? `/v2/feed/posts/${id}`
      : `/v2/feed/items/${itemType}/${id}`;

    api.get<FeedItem>(url)
      .then((response) => {
        if (controller.signal.aborted) return;
        if (response.success && response.data) {
          setItem(response.data);
        } else {
          setError(t('post_detail.not_found'));
        }
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        logError('Failed to load feed item', err);
        setError(t('post_detail.load_failed'));
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => { abortRef.current?.abort(); };
  }, [id, itemType, t]);

  const handleToggleLike = async (feedItem: FeedItem) => {
    const newIsLiked = !feedItem.is_liked;
    const newLikesCount = newIsLiked ? feedItem.likes_count + 1 : feedItem.likes_count - 1;
    setItem((prev) => prev ? { ...prev, is_liked: newIsLiked, likes_count: newLikesCount } : null);
    try {
      await api.post('/v2/feed/like', { target_type: feedItem.type, target_id: feedItem.id });
      dispatchFeedSync({ targetType: feedItem.type, targetId: feedItem.id, patch: { is_liked: newIsLiked, likes_count: newLikesCount } });
    } catch (err) {
      logError('Failed to toggle like', err);
      setItem((prev) => prev ? { ...prev, is_liked: feedItem.is_liked, likes_count: feedItem.likes_count } : null);
    }
  };

  const handleHidePost = async (feedItem: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${feedItem.id}/hide`, { type: feedItem.type });
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error(t('toast.hide_failed', 'Failed to hide post'));
    }
  };

  const handleMuteUser = async (feedItem: FeedItem) => {
    const authorId = getAuthor(feedItem).id;
    if (!authorId) return;
    try {
      await api.post(`/v2/feed/users/${authorId}/mute`);
      toast.success(t('toast.user_muted', 'User muted'));
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error(t('toast.mute_failed', 'Failed to mute user'));
    }
  };

  const openReportModal = (feedItem: FeedItem) => {
    setReportPostId(feedItem.id);
    setReportReason('');
    onReportOpen();
  };

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

  const handleDeletePost = async (feedItem: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${feedItem.id}/delete`);
      toast.success(t('toast.deleted', 'Post deleted'));
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to delete post', err);
      toast.error(t('toast.delete_failed', 'Failed to delete post'));
    }
  };

  const handleVotePoll = async (pollId: number, optionId: number) => {
    try {
      await api.post(`/v2/feed/polls/${pollId}/vote`, { option_id: optionId });
    } catch (err) {
      logError('Failed to vote on poll', err);
      toast.error(t('toast.vote_failed', 'Failed to submit vote'));
    }
  };

  const handleNotInterested = useCallback(async (feedItem: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${feedItem.id}/not-interested`, { type: feedItem.type });
      toast.success(t('toast.not_interested', 'We\'ll show you less like this'));
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to record not-interested', err);
    }
  }, [toast, t, navigate, tenantPath]);

  return (
    <>
      <PageMeta
        title={item?.content?.substring(0, 60) || t('post_detail.title', 'Post')}
        description={item?.content?.substring(0, 160)}
        noIndex
      />
      <div className="space-y-4 max-w-2xl mx-auto">
        <div>
          <Link to={tenantPath('/feed')}>
            <Button
              variant="light"
              startContent={<ArrowLeft className="w-4 h-4" />}
              className="text-theme-muted hover:text-theme-primary -ml-2"
            >
              {t('post_detail.back', 'Back to Feed')}
            </Button>
          </Link>
        </div>

        {isLoading ? (
          <FeedSkeleton />
        ) : error ? (
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
            <p className="text-theme-muted">{error}</p>
            <Link to={tenantPath('/feed')} className="mt-4 inline-block">
              <Button variant="flat" className="bg-theme-elevated text-theme-primary">
                {t('post_detail.back', 'Back to Feed')}
              </Button>
            </Link>
          </GlassCard>
        ) : item ? (
          <FeedCard
            item={item}
            onToggleLike={handleToggleLike}
            onHidePost={handleHidePost}
            onMuteUser={handleMuteUser}
            onReportPost={openReportModal}
            onDeletePost={handleDeletePost}
            onNotInterested={handleNotInterested}
            onVotePoll={handleVotePoll}
            feedMode="recent"
            isAuthenticated={isAuthenticated}
            currentUserId={user?.id}
            defaultShowComments
          />
        ) : null}
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

export default PostDetailPage;
