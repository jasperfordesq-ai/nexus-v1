// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Tabs } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import {
  createReview,
  deleteReview,
  getPendingReviews,
  getUserReviews,
  type PendingReview,
  type ReviewItem,
  type ReviewUser,
} from '@/lib/api/reviews';
import { useAuth } from '@/lib/hooks/useAuth';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import * as Haptics from '@/lib/haptics';

type ReviewTab = 'received' | 'given' | 'pending';
type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

const TABS: ReviewTab[] = ['received', 'given', 'pending'];

export default function ReviewsScreen() {
  const { t } = useTranslation(['profile', 'common']);
  const { user } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const userId = Number(user?.id ?? 0);
  const [activeTab, setActiveTab] = useState<ReviewTab>('received');
  const [activePending, setActivePending] = useState<PendingReview | null>(null);
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const {
    data: reviewsPage,
    isLoading: reviewsLoading,
    error: reviewsError,
    refresh: refreshReviews,
  } = useApi(() => getUserReviews(userId), [userId], { enabled: userId > 0 });
  const {
    data: pendingReviews,
    isLoading: pendingLoading,
    error: pendingError,
    refresh: refreshPending,
  } = useApi(() => getPendingReviews(), []);

  const reviews = reviewsPage?.items ?? [];
  const receivedReviews = useMemo(
    () => reviews.filter((review) => !isOwnReview(review, userId)),
    [reviews, userId],
  );
  const givenReviews = useMemo(
    () => reviews.filter((review) => isOwnReview(review, userId)),
    [reviews, userId],
  );
  const pending = pendingReviews ?? [];
  const visibleReviews = activeTab === 'given' ? givenReviews : receivedReviews;
  const averageRating = reviews.length
    ? reviews.reduce((total, review) => total + Number(review.rating || 0), 0) / reviews.length
    : 0;
  const isLoading = activeTab === 'pending' ? pendingLoading : reviewsLoading;
  const error = activeTab === 'pending' ? pendingError : reviewsError;

  function resetForm() {
    setActivePending(null);
    setRating(0);
    setComment('');
  }

  async function handleSubmitReview() {
    if (!activePending || rating < 1 || submitting) return;
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    setSubmitting(true);
    try {
      await createReview({
        receiver_id: activePending.receiver_id,
        rating,
        comment: comment.trim() || undefined,
        transaction_id: activePending.transaction_id ?? undefined,
      });
      resetForm();
      refreshPending();
      refreshReviews();
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDeleteReview(review: ReviewItem) {
    if (deletingId !== null) return;
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Warning);
    setDeletingId(review.id);
    try {
      await deleteReview(review.id);
      refreshReviews();
    } finally {
      setDeletingId(null);
    }
  }

  function handleRefresh() {
    if (activeTab === 'pending') {
      refreshPending();
    } else {
      refreshReviews();
    }
  }

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('reviews.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
        <ScrollView
          contentContainerStyle={{ paddingBottom: 40 }}
          refreshControl={<RefreshControl refreshing={isLoading} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />}
        >
          <View className="gap-3">
            <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
              <View className="h-1 w-full" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-4 p-4">
                <View className="flex-row items-start gap-3">
                  <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                    <Ionicons name="star-outline" size={25} color={primary} />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                      {t('reviews.title')}
                    </Text>
                    <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                      {t('reviews.subtitle')}
                    </Text>
                  </View>
                </View>
              </HeroCard.Body>
            </HeroCard>

            <View className="mx-4 flex-row flex-wrap gap-3">
              <StatTile icon="chatbubble-ellipses-outline" label={t('reviews.total')} value={reviews.length} tone={primary} />
              <StatTile icon="star-outline" label={t('reviews.average')} value={averageRating ? averageRating.toFixed(1) : '0.0'} tone="#f59e0b" />
              <StatTile icon="create-outline" label={t('reviews.pendingCount')} value={pending.length} tone="#22c55e" />
            </View>

            <Surface variant="default" className="mx-4 rounded-panel-inner p-2">
              <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as ReviewTab)} variant="secondary">
                <Tabs.List>
                  <Tabs.ScrollView scrollAlign="start" contentContainerClassName="gap-1">
                    <Tabs.Indicator />
                    {TABS.map((tab) => (
                      <Tabs.Trigger key={tab} value={tab}>
                        <Tabs.Label>{t(`reviews.${tab}`)}</Tabs.Label>
                      </Tabs.Trigger>
                    ))}
                  </Tabs.ScrollView>
                </Tabs.List>
              </Tabs>
            </Surface>

            {isLoading ? (
              <View className="items-center justify-center py-14">
                <LoadingSpinner />
              </View>
            ) : error ? (
              <View className="px-4 py-8">
                <EmptyState
                  icon="warning-outline"
                  title={t('reviews.errorTitle')}
                  subtitle={String(error)}
                  actionLabel={t('common:buttons.retry')}
                  onAction={handleRefresh}
                />
              </View>
            ) : activeTab === 'pending' ? (
              <PendingList
                items={pending}
                activePending={activePending}
                rating={rating}
                comment={comment}
                submitting={submitting}
                onStart={(item) => {
                  void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  setActivePending(item);
                  setRating(0);
                  setComment('');
                }}
                onCancel={resetForm}
                onRatingChange={setRating}
                onCommentChange={setComment}
                onSubmit={() => void handleSubmitReview()}
              />
            ) : visibleReviews.length > 0 ? (
              <View className="gap-3 px-4">
                {visibleReviews.map((review) => (
                  <ReviewCard
                    key={review.id}
                    review={review}
                    canDelete={activeTab === 'given'}
                    isDeleting={deletingId === review.id}
                    onDelete={() => void handleDeleteReview(review)}
                  />
                ))}
              </View>
            ) : (
              <View className="px-4 py-8">
                <EmptyState
                  icon="star-outline"
                  title={activeTab === 'given' ? t('reviews.emptyGivenTitle') : t('reviews.emptyReceivedTitle')}
                  subtitle={t('reviews.emptySubtitle')}
                />
              </View>
            )}
          </View>
        </ScrollView>
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function PendingList({
  items,
  activePending,
  rating,
  comment,
  submitting,
  onStart,
  onCancel,
  onRatingChange,
  onCommentChange,
  onSubmit,
}: {
  items: PendingReview[];
  activePending: PendingReview | null;
  rating: number;
  comment: string;
  submitting: boolean;
  onStart: (item: PendingReview) => void;
  onCancel: () => void;
  onRatingChange: (rating: number) => void;
  onCommentChange: (comment: string) => void;
  onSubmit: () => void;
}) {
  const { t } = useTranslation(['profile']);
  const theme = useTheme();
  const primary = usePrimaryColor();

  if (items.length === 0) {
    return (
      <View className="px-4 py-8">
        <EmptyState icon="checkmark-circle-outline" title={t('reviews.emptyPendingTitle')} subtitle={t('reviews.emptySubtitle')} />
      </View>
    );
  }

  return (
    <View className="gap-3 px-4">
      {items.map((item) => {
        const isActive = activePending?.exchange_id === item.exchange_id;
        return (
          <HeroCard key={item.exchange_id} variant="default" className="overflow-hidden rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <View className="flex-row items-center gap-3">
                <Avatar uri={item.receiver_avatar ?? undefined} name={item.receiver_name} size={48} />
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>
                    {item.receiver_name}
                  </Text>
                  {item.exchange_title ? (
                    <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                      {t('reviews.forExchange', { title: item.exchange_title })}
                    </Text>
                  ) : null}
                </View>
                <HeroButton size="sm" variant="primary" onPress={() => onStart(item)} style={{ backgroundColor: primary }}>
                  <HeroButton.Label>{t('reviews.write')}</HeroButton.Label>
                </HeroButton>
              </View>

              {isActive ? (
                <View className="gap-3">
                  <StarPicker rating={rating} onChange={onRatingChange} />
                  <Input
                    label={t('reviews.comment')}
                    placeholder={t('reviews.commentPlaceholder')}
                    value={comment}
                    onChangeText={onCommentChange}
                    multiline
                    numberOfLines={4}
                    textAlignVertical="top"
                    containerClassName="mb-0"
                  />
                  <View className="flex-row gap-2">
                    <HeroButton className="flex-1" variant="secondary" onPress={onCancel} isDisabled={submitting}>
                      <HeroButton.Label>{t('reviews.cancel')}</HeroButton.Label>
                    </HeroButton>
                    <HeroButton className="flex-1" variant="primary" onPress={onSubmit} isDisabled={rating < 1 || submitting} style={{ backgroundColor: primary }}>
                      <HeroButton.Label>{t('reviews.submit')}</HeroButton.Label>
                    </HeroButton>
                  </View>
                </View>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        );
      })}
    </View>
  );
}

function ReviewCard({
  review,
  canDelete,
  isDeleting,
  onDelete,
}: {
  review: ReviewItem;
  canDelete: boolean;
  isDeleting: boolean;
  onDelete: () => void;
}) {
  const { t } = useTranslation(['profile']);
  const theme = useTheme();
  const primary = usePrimaryColor();
  const displayName = review.is_anonymous ? t('reviews.anonymous') : userName(review.reviewer);

  return (
    <HeroCard variant="default" className="overflow-hidden rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start gap-3">
          <Avatar uri={review.is_anonymous ? undefined : review.reviewer?.avatar_url ?? review.reviewer?.avatar ?? undefined} name={displayName} size={48} />
          <View className="min-w-0 flex-1 gap-1">
            <View className="flex-row items-start justify-between gap-2">
              <View className="min-w-0 flex-1">
                <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>
                  {displayName}
                </Text>
                <StarRating rating={review.rating} />
              </View>
              {canDelete ? (
                <HeroButton size="sm" variant="danger-soft" onPress={onDelete} isDisabled={isDeleting} accessibilityLabel={t('reviews.delete')}>
                  <Ionicons name="trash-outline" size={16} color={theme.error} />
                  <HeroButton.Label>{t('reviews.delete')}</HeroButton.Label>
                </HeroButton>
              ) : null}
            </View>
            {review.comment ? (
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
                {review.comment}
              </Text>
            ) : null}
            <Chip size="sm" variant="secondary" className="self-start">
              <Ionicons name="star" size={12} color={primary} />
              <Chip.Label>{t('reviews.ratingLabel', { rating: review.rating })}</Chip.Label>
            </Chip>
          </View>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function StarPicker({ rating, onChange }: { rating: number; onChange: (rating: number) => void }) {
  const { t } = useTranslation(['profile']);
  const theme = useTheme();
  return (
    <View className="flex-row gap-1">
      {[1, 2, 3, 4, 5].map((star) => (
        <HeroButton key={star} isIconOnly variant="secondary" accessibilityLabel={t('reviews.rateStar', { star })} onPress={() => onChange(star)}>
          <Ionicons name={star <= rating ? 'star' : 'star-outline'} size={22} color={star <= rating ? '#f59e0b' : theme.textMuted} />
        </HeroButton>
      ))}
    </View>
  );
}

function StarRating({ rating }: { rating: number }) {
  const { t } = useTranslation(['profile']);
  const theme = useTheme();
  return (
    <View className="flex-row items-center gap-0.5" accessibilityLabel={t('reviews.ratingLabel', { rating })}>
      {[1, 2, 3, 4, 5].map((star) => (
        <Ionicons key={star} name={star <= rating ? 'star' : 'star-outline'} size={14} color={star <= rating ? '#f59e0b' : theme.textMuted} />
      ))}
    </View>
  );
}

function StatTile({
  icon,
  label,
  value,
  tone,
}: {
  icon: IoniconName;
  label: string;
  value: number | string;
  tone: string;
}) {
  const theme = useTheme();

  return (
    <Surface variant="secondary" className="min-w-[30%] flex-1 gap-2 rounded-panel-inner p-4">
      <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
        <Ionicons name={icon} size={18} color={tone} />
      </View>
      <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
        {value}
      </Text>
      <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={2}>
        {label}
      </Text>
    </Surface>
  );
}

function isOwnReview(review: ReviewItem, userId: number): boolean {
  if (review.direction === 'given') return true;
  return Number(review.reviewer_id ?? review.reviewer?.id ?? 0) === userId;
}

function userName(user?: ReviewUser | null): string {
  if (!user) return '';
  const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ').trim();
  return user.name?.trim() || fullName;
}
