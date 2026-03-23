// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PostDetailPage - View a single feed post by ID.
 *
 * API: GET /api/v2/feed/posts/{id}
 */

import { useState, useEffect, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { Button, Skeleton, Divider } from '@heroui/react';
import { ArrowLeft, AlertTriangle } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { FeedCard } from '@/components/feed/FeedCard';
import type { FeedItem } from '@/components/feed/types';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

function PostSkeleton() {
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

export function PostDetailPage() {
  const { t } = useTranslation('feed');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const navigate = useNavigate();
  usePageTitle(t('post_detail.title', 'Post'));

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

    api.get<FeedItem>(`/v2/feed/posts/${id}`)
      .then((response) => {
        if (controller.signal.aborted) return;
        if (response.success && response.data) {
          setItem(response.data);
        } else {
          setError(t('post_detail.not_found', 'Post not found'));
        }
      })
      .catch((err) => {
        if (controller.signal.aborted) return;
        logError('Failed to load post', err);
        setError(t('post_detail.load_failed', 'Failed to load post'));
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => { abortRef.current?.abort(); };
  }, [id, t]);

  const handleToggleLike = async (feedItem: FeedItem) => {
    setItem((prev) =>
      prev ? {
        ...prev,
        is_liked: !prev.is_liked,
        likes_count: prev.is_liked ? prev.likes_count - 1 : prev.likes_count + 1,
      } : null
    );
    try {
      await api.post('/v2/feed/like', { target_type: feedItem.type, target_id: feedItem.id });
    } catch (err) {
      logError('Failed to toggle like', err);
      setItem((prev) =>
        prev ? {
          ...prev,
          is_liked: !prev.is_liked,
          likes_count: prev.is_liked ? prev.likes_count - 1 : prev.likes_count + 1,
        } : null
      );
    }
  };

  const handleHidePost = async (feedItem: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${feedItem.id}/hide`);
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to hide post', err);
      toast.error(t('toast.hide_failed', 'Failed to hide post'));
    }
  };

  const handleMuteUser = async (feedItem: FeedItem) => {
    if (!feedItem.author?.id) return;
    try {
      await api.post(`/v2/feed/users/${feedItem.author.id}/mute`);
      toast.success(t('toast.user_muted', 'User muted'));
      navigate(tenantPath('/feed'));
    } catch (err) {
      logError('Failed to mute user', err);
      toast.error(t('toast.mute_failed', 'Failed to mute user'));
    }
  };

  const handleReportPost = async (feedItem: FeedItem) => {
    try {
      await api.post(`/v2/feed/posts/${feedItem.id}/report`, { reason: 'inappropriate' });
      toast.success(t('toast.reported', 'Post reported'));
    } catch (err) {
      logError('Failed to report post', err);
      toast.error(t('toast.report_failed', 'Failed to report post'));
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

  return (
    <>
      <PageMeta title={t('post_detail.title', 'Post')} />
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
          <PostSkeleton />
        ) : error ? (
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" />
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
            onReportPost={handleReportPost}
            onDeletePost={handleDeletePost}
            onVotePoll={handleVotePoll}
            isAuthenticated={isAuthenticated}
            currentUserId={user?.id}
          />
        ) : null}
      </div>
    </>
  );
}

export default PostDetailPage;
