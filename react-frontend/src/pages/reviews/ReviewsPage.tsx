// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ReviewsPage — View reviews received, reviews given, and pending reviews to write.
 *
 * API endpoints:
 *   GET /api/v2/reviews/user/{userId}       — reviews received by current user
 *   GET /api/v2/reviews/pending             — exchanges awaiting a review
 *   DELETE /api/v2/reviews/{id}             — delete own review
 */

import { useState, useCallback, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Avatar,
  Tabs,
  Tab,
  Chip,
  Skeleton,
} from '@heroui/react';
import { Star, AlertTriangle, Trash2 } from 'lucide-react';
import { useAuth } from '@/contexts';
import { usePageTitle } from '@/hooks/usePageTitle';
import { api } from '@/lib/api';
import { useToast } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { ReviewModal } from '@/components/reviews/ReviewModal';
import type { JSX } from 'react';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Reviewer {
  id: number | null;
  name: string;
  avatar?: string | null;
  avatar_url?: string | null;
}

interface Review {
  id: number;
  rating: number;
  comment?: string | null;
  is_anonymous: boolean;
  reviewer: Reviewer;
  created_at: string;
}

interface PendingReview {
  exchange_id: number;
  exchange_title?: string | null;
  receiver_id: number;
  receiver_name: string;
  receiver_avatar?: string | null;
  transaction_id?: number | null;
  completed_at?: string | null;
}

// ─── Star display component ───────────────────────────────────────────────────

function StarRating({ rating }: { rating: number }): JSX.Element {
  return (
    <div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
      {Array.from({ length: 5 }, (_, i) => (
        <Star
          key={i}
          className={`w-4 h-4 ${i < rating ? 'fill-amber-400 text-amber-400' : 'text-theme-subtle'}`}
        />
      ))}
    </div>
  );
}

// ─── Review Card ──────────────────────────────────────────────────────────────

function ReviewCard({
  review,
  onDelete,
  canDelete,
}: {
  review: Review;
  onDelete?: (id: number) => void;
  canDelete?: boolean;
}): JSX.Element {
  const { t } = useTranslation('reviews');
  const [deleting, setDeleting] = useState(false);
  const toast = useToast();

  const handleDelete = useCallback(async () => {
    if (!window.confirm(t('review_card.delete_confirm'))) return;
    setDeleting(true);
    try {
      await api.delete(`/v2/reviews/${review.id}`);
      toast.showToast(t('review_card.delete_success'), 'success');
      onDelete?.(review.id);
    } catch {
      toast.showToast(t('review_card.delete_error'), 'error');
    } finally {
      setDeleting(false);
    }
  }, [review.id, t, toast, onDelete]);

  const avatarUrl = resolveAvatarUrl(review.reviewer.avatar_url ?? review.reviewer.avatar);
  const displayName = review.is_anonymous ? t('review_card.anonymous') : review.reviewer.name;

  return (
    <div className="flex gap-3 p-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <Avatar
        src={review.is_anonymous ? undefined : (avatarUrl ?? undefined)}
        name={displayName}
        size="sm"
        className="shrink-0 mt-0.5"
      />
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <div>
            <p className="text-sm font-medium text-[var(--color-text)]">{displayName}</p>
            <StarRating rating={review.rating} />
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <span className="text-xs text-[var(--color-text-muted)]">
              {new Date(review.created_at).toLocaleDateString()}
            </span>
            {canDelete && (
              <Button
                isIconOnly
                size="sm"
                variant="light"
                color="danger"
                isLoading={deleting}
                onPress={handleDelete}
                aria-label={t('review_card.delete')}
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            )}
          </div>
        </div>
        {review.comment && (
          <p className="mt-1.5 text-sm text-[var(--color-text-muted)] leading-relaxed">{review.comment}</p>
        )}
      </div>
    </div>
  );
}

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyState({ message, subtitle }: { message: string; subtitle: string }): JSX.Element {
  return (
    <div className="text-center py-12 text-[var(--color-text-muted)]">
      <Star className="w-12 h-12 mx-auto mb-3 opacity-30" />
      <p className="font-medium">{message}</p>
      <p className="text-sm mt-1 opacity-70">{subtitle}</p>
    </div>
  );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function ReviewsPage(): JSX.Element {
  const { t } = useTranslation('reviews');
  const { user } = useAuth();
  const toast = useToast();
  usePageTitle(t('page_title'));

  const [activeTab, setActiveTab] = useState<string>('received');

  // Reviews received state
  const [received, setReceived] = useState<Review[]>([]);
  const [receivedLoading, setReceivedLoading] = useState(true);
  const [receivedError, setReceivedError] = useState(false);
  const [receivedCursor, setReceivedCursor] = useState<string | null>(null);
  const [receivedHasMore, setReceivedHasMore] = useState(false);
  const [receivedLoadingMore, setReceivedLoadingMore] = useState(false);

  // Pending reviews state
  const [pending, setPending] = useState<PendingReview[]>([]);
  const [pendingLoading, setPendingLoading] = useState(true);
  const [pendingError, setPendingError] = useState(false);

  // Review modal
  const [reviewTarget, setReviewTarget] = useState<PendingReview | null>(null);

  // Overall error
  const [error, setError] = useState<string | null>(null);

  const fetchReceived = useCallback(async (cursor?: string | null) => {
    if (!user?.id) return;
    const isLoadMore = !!cursor;
    if (isLoadMore) setReceivedLoadingMore(true); else setReceivedLoading(true);
    setReceivedError(false);
    try {
      const params: Record<string, string | number> = { per_page: 20 };
      if (cursor) params.cursor = cursor;
      const data = await api.get(`/v2/reviews/user/${user.id}`, { params });
      const items: Review[] = data?.items ?? [];
      setReceived(prev => isLoadMore ? [...prev, ...items] : items);
      setReceivedCursor(data?.cursor ?? null);
      setReceivedHasMore(data?.has_more ?? false);
    } catch {
      if (!isLoadMore) setReceivedError(true);
      else toast.showToast(t('load_error'), 'error');
    } finally {
      if (isLoadMore) setReceivedLoadingMore(false); else setReceivedLoading(false);
    }
  }, [user?.id, t, toast]);

  const fetchPending = useCallback(async () => {
    setPendingLoading(true);
    setPendingError(false);
    try {
      const data = await api.get('/v2/reviews/pending');
      setPending(data?.items ?? data ?? []);
    } catch {
      setPendingError(true);
    } finally {
      setPendingLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchReceived();
    fetchPending();
  }, [fetchReceived, fetchPending]);

  const handleReviewWritten = useCallback((pendingItem: PendingReview) => {
    setPending(prev => prev.filter(p => p.exchange_id !== pendingItem.exchange_id));
    setReviewTarget(null);
  }, []);

  if (error) {
    return (
      <div className="max-w-2xl mx-auto p-6 text-center">
        <AlertTriangle className="w-10 h-10 mx-auto mb-3 text-danger" />
        <p className="text-[var(--color-text-muted)]">{t('load_error')}</p>
        <Button className="mt-4" onPress={() => { setError(null); fetchReceived(); fetchPending(); }}>{t('load_more')}</Button>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-6">
      <h1 className="text-2xl font-bold text-[var(--color-text)] mb-6">{t('page_title')}</h1>

      <Tabs
        selectedKey={activeTab}
        onSelectionChange={key => setActiveTab(key as string)}
        aria-label={t('page_title')}
        className="mb-6"
      >
        {/* ── Received ─────────────────────────────────────────────── */}
        <Tab
          key="received"
          title={
            <span className="flex items-center gap-1.5">
              {t('tabs.received')}
              {received.length > 0 && (
                <Chip size="sm" variant="flat">{received.length}</Chip>
              )}
            </span>
          }
        >
          {receivedLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }, (_, i) => (
                <Skeleton key={i} className="h-24 rounded-xl" />
              ))}
            </div>
          ) : receivedError ? (
            <div className="text-center py-8">
              <p className="text-[var(--color-text-muted)]">{t('load_error')}</p>
              <Button className="mt-3" size="sm" onPress={() => fetchReceived()}>{t('load_more')}</Button>
            </div>
          ) : received.length === 0 ? (
            <EmptyState message={t('received.empty')} subtitle={t('received.empty_subtitle')} />
          ) : (
            <div className="space-y-3">
              {received.map(review => (
                <ReviewCard key={review.id} review={review} />
              ))}
              {receivedHasMore && (
                <Button
                  variant="flat"
                  className="w-full"
                  isLoading={receivedLoadingMore}
                  onPress={() => fetchReceived(receivedCursor)}
                >
                  {t('load_more')}
                </Button>
              )}
            </div>
          )}
        </Tab>

        {/* ── Pending ───────────────────────────────────────────────── */}
        <Tab
          key="pending"
          title={
            <span className="flex items-center gap-1.5">
              {t('tabs.pending')}
              {pending.length > 0 && (
                <Chip size="sm" color="warning" variant="flat">{pending.length}</Chip>
              )}
            </span>
          }
        >
          {pendingLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }, (_, i) => (
                <Skeleton key={i} className="h-20 rounded-xl" />
              ))}
            </div>
          ) : pendingError ? (
            <div className="text-center py-8">
              <p className="text-[var(--color-text-muted)]">{t('load_error')}</p>
              <Button className="mt-3" size="sm" onPress={fetchPending}>{t('load_more')}</Button>
            </div>
          ) : pending.length === 0 ? (
            <EmptyState message={t('pending.empty')} subtitle={t('pending.empty_subtitle')} />
          ) : (
            <div className="space-y-3">
              {pending.map(item => (
                <div
                  key={item.exchange_id}
                  className="flex items-center justify-between gap-3 p-4 rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]"
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <Avatar
                      src={resolveAvatarUrl(item.receiver_avatar) ?? undefined}
                      name={item.receiver_name}
                      size="sm"
                    />
                    <div className="min-w-0">
                      <p className="text-sm font-medium text-[var(--color-text)] truncate">{item.receiver_name}</p>
                      {item.exchange_title && (
                        <p className="text-xs text-[var(--color-text-muted)] truncate">{item.exchange_title}</p>
                      )}
                    </div>
                  </div>
                  <Button
                    size="sm"
                    color="primary"
                    onPress={() => setReviewTarget(item)}
                  >
                    {t('pending.write_review')}
                  </Button>
                </div>
              ))}
            </div>
          )}
        </Tab>
      </Tabs>

      {/* Review submission modal */}
      {reviewTarget && (
        <ReviewModal
          isOpen
          onClose={() => setReviewTarget(null)}
          onSuccess={() => handleReviewWritten(reviewTarget)}
          receiverId={reviewTarget.receiver_id}
          receiverName={reviewTarget.receiver_name}
          receiverAvatar={reviewTarget.receiver_avatar}
          transactionId={reviewTarget.transaction_id ?? undefined}
        />
      )}
    </div>
  );
}
