// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FlatList, RefreshControl, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Tabs } from 'heroui-native';

import * as Sentry from '@sentry/react-native';
import { useTranslation } from 'react-i18next';
import { getFeed, getFeedAuthor, type FeedFilter, type FeedItem as FeedItemType, type FeedMode, type FeedResponse } from '@/lib/api/feed';
import { getWalletBalance } from '@/lib/api/wallet';
import { getEvents } from '@/lib/api/events';
import { getExchanges } from '@/lib/api/exchanges';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import FeedItem, { type FeedCommentTarget } from '@/components/FeedItem';
import CommentSheet from '@/components/comments/CommentSheet';
import OfflineBanner from '@/components/OfflineBanner';
import StoryCircles from '@/components/StoryCircles';
import TenantBanner from '@/components/TenantBanner';
import NativePressable from '@/components/ui/NativePressable';
import { FeedItemSkeleton } from '@/components/ui/Skeleton';
import FAB from '@/components/ui/FAB';
import * as Haptics from '@/lib/haptics';
import { withAlpha } from '@/lib/utils/color';

function extractFeedPage(response: FeedResponse) {
  if (!response?.data || !response?.meta) {
    console.error('Unexpected feed response shape:', response);
    Sentry.captureException(new Error('Unexpected feed response shape'));
    return { items: [], cursor: null, hasMore: false };
  }
  const seen = new Set<string>();
  const unique = response.data.filter((item) => {
    const key = `${item.type}-${item.id}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
  return {
    items: unique,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more ?? false,
  };
}

const FILTER_OPTIONS: Array<{ key: FeedFilter; icon: keyof typeof Ionicons.glyphMap }> = [
  { key: 'all', icon: 'albums-outline' },
  { key: 'following', icon: 'people-outline' },
  { key: 'saved', icon: 'bookmark-outline' },
  { key: 'posts', icon: 'chatbox-ellipses-outline' },
  { key: 'listings', icon: 'swap-horizontal-outline' },
  { key: 'events', icon: 'calendar-outline' },
  { key: 'polls', icon: 'stats-chart-outline' },
  { key: 'challenges', icon: 'trophy-outline' },
  { key: 'volunteering', icon: 'heart-outline' },
];

const LISTING_SUBFILTERS = ['offer', 'request'] as const;

interface DashboardSummary {
  balance: number | null;
  upcomingEvents: number | null;
  openRequests: number | null;
}

interface SummaryCardItem {
  key: string;
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  route: string;
}

export default function HomeScreen() {
  const { t } = useTranslation(['home', 'common', 'exchanges']);
  const { user, displayName } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [feedMode, setFeedMode] = useState<FeedMode>('ranking');
  const [filter, setFilter] = useState<FeedFilter>('all');
  const [subFilter, setSubFilter] = useState<string | null>(null);
  const [summary, setSummary] = useState<DashboardSummary>({
    balance: null,
    upcomingEvents: null,
    openRequests: null,
  });
  const [commentTarget, setCommentTarget] = useState<FeedCommentTarget | null>(null);
  const [commentCountOverrides, setCommentCountOverrides] = useState<Record<string, number>>({});

  const fetchFeed = useCallback(
    (cursor: string | null) => getFeed(1, cursor, { filter, mode: feedMode, subtype: subFilter }),
    [feedMode, filter, subFilter],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItemType, FeedResponse>(fetchFeed, extractFeedPage, [feedMode, filter, subFilter]);

  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const loadDashboardSummary = useCallback(async () => {
    const [walletResult, eventsResult, requestsResult] = await Promise.allSettled([
      getWalletBalance(),
      getEvents('upcoming', null, 5),
      getExchanges(null, { type: 'request', per_page: '5' }),
    ]);

    setSummary({
      balance: walletResult.status === 'fulfilled' ? walletResult.value.data.balance : null,
      upcomingEvents: eventsResult.status === 'fulfilled' ? eventsResult.value.data.length : null,
      openRequests: requestsResult.status === 'fulfilled' ? requestsResult.value.data.length : null,
    });
  }, []);

  useEffect(() => {
    let isMounted = true;
    const load = async () => {
      const [walletResult, eventsResult, requestsResult] = await Promise.allSettled([
        getWalletBalance(),
        getEvents('upcoming', null, 5),
        getExchanges(null, { type: 'request', per_page: '5' }),
      ]);

      if (!isMounted) return;
      setSummary({
        balance: walletResult.status === 'fulfilled' ? walletResult.value.data.balance : null,
        upcomingEvents: eventsResult.status === 'fulfilled' ? eventsResult.value.data.length : null,
        openRequests: requestsResult.status === 'fulfilled' ? requestsResult.value.data.length : null,
      });
    };
    void load();
    return () => {
      isMounted = false;
    };
  }, []);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
    void loadDashboardSummary();
  }, [loadDashboardSummary, refresh]);

  useEffect(() => {
    if (wasRefreshingRef.current && !isLoading) {
      wasRefreshingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isLoading]);

  const { unreadNotifications } = useRealtimeContext();
  const notificationBadgeText =
    unreadNotifications > 99 ? '99+' : unreadNotifications > 0 ? String(unreadNotifications) : null;

  const storyMembers = useMemo(() => {
    const seen = new Set<number>();
    if (user?.id) seen.add(user.id);
    const members: { id: number; name: string; avatar?: string | null }[] = [];
    for (const item of items) {
      const author = getFeedAuthor(item, '');
      if (author.id && author.name && !seen.has(author.id)) {
        seen.add(author.id);
        members.push({ id: author.id, name: author.name, avatar: author.avatar });
      }
      if (members.length >= 10) break;
    }
    return members;
  }, [items, user?.id]);

  const handleStoryPress = useCallback((memberId: number) => {
    router.push({ pathname: '/(modals)/member-profile', params: { id: String(memberId) } });
  }, []);

  const handleOpenComments = useCallback((target: FeedCommentTarget) => {
    const key = `${target.targetType}-${target.targetId}`;
    setCommentTarget({
      ...target,
      initialCount: commentCountOverrides[key] ?? target.initialCount,
    });
  }, [commentCountOverrides]);

  const handleCommentCountChange = useCallback((count: number) => {
    if (!commentTarget) return;
    const key = `${commentTarget.targetType}-${commentTarget.targetId}`;
    setCommentCountOverrides((previous) => ({ ...previous, [key]: count }));
    setCommentTarget({ ...commentTarget, initialCount: count });
  }, [commentTarget]);

  const renderItem = useCallback(({ item }: { item: FeedItemType }) => {
    const commentKey = `${item.type}-${item.id}`;
    return (
      <FeedItem
        item={item}
        commentsCountOverride={commentCountOverrides[commentKey]}
        onOpenComments={handleOpenComments}
      />
    );
  }, [commentCountOverrides, handleOpenComments]);
  const keyExtractor = useCallback((item: FeedItemType) => `${item.type}-${item.id}`, []);
  const activeFilterLabel = t(`filter.${filter}`);

  const handleFilterChange = useCallback((nextFilter: FeedFilter) => {
    setFilter(nextFilter);
    if (nextFilter !== 'listings') {
      setSubFilter(null);
    }
  }, []);

  const summaryCards = useMemo<SummaryCardItem[]>(
    () => [
      {
        key: 'balance',
        icon: 'wallet-outline' as const,
        label: t('dashboard.balance'),
        value: summary.balance === null ? t('dashboard.unavailable') : t('dashboard.hours', { count: summary.balance }),
        route: '/(modals)/wallet' as const,
      },
      {
        key: 'events',
        icon: 'calendar-outline' as const,
        label: t('dashboard.upcomingEvents'),
        value: summary.upcomingEvents === null ? t('dashboard.unavailable') : String(summary.upcomingEvents),
        route: '/(tabs)/events' as const,
      },
      {
        key: 'requests',
        icon: 'help-buoy-outline' as const,
        label: t('dashboard.openRequests'),
        value: summary.openRequests === null ? t('dashboard.unavailable') : String(summary.openRequests),
        route: '/(tabs)/exchanges' as const,
      },
      {
        key: 'notifications',
        icon: 'notifications-outline' as const,
        label: t('dashboard.notifications'),
        value: unreadNotifications > 99 ? '99+' : String(unreadNotifications),
        route: '/(modals)/notifications' as const,
      },
    ],
    [summary.balance, summary.openRequests, summary.upcomingEvents, t, unreadNotifications],
  );

  return (
    <SafeAreaView className="flex-1 bg-background">
      <TenantBanner />
      <OfflineBanner />

      <FlatList<FeedItemType>
        data={items}
        keyExtractor={keyExtractor}
        renderItem={renderItem}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={loadMore}
        onEndReachedThreshold={0.3}
        removeClippedSubviews
        maxToRenderPerBatch={8}
        windowSize={5}
        ListHeaderComponent={
          <View className="gap-3 pb-3">
            <HeroCard variant="default" className="mx-4 mt-3 overflow-hidden rounded-panel p-0">
              <View className="h-1" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="flex-row items-center justify-between gap-4 p-4">
                <View className="min-w-0 flex-1">
                  <Text className="text-[26px] font-bold leading-8" style={{ color: theme.text }} numberOfLines={1}>
                    {t('feed.greeting', { name: (displayName || '').split(' ')[0] || t('common:labels.friend') })}
                  </Text>
                  <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>
                    {t('feed.subtitle')}
                  </Text>
                </View>
                <View className="relative h-12 w-12 items-center justify-center">
                  <HeroButton
                    isIconOnly
                    size="lg"
                    variant="secondary"
                    className="h-12 w-12 rounded-2xl"
                    onPress={() => {
                      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                      router.push('/(modals)/notifications');
                    }}
                    accessibilityLabel={t('notifications.title')}
                    accessibilityRole="button"
                    style={{
                      backgroundColor: withAlpha(primary, 0.12),
                      borderColor: withAlpha(primary, 0.24),
                      borderWidth: 1,
                    }}
                  >
                    <Ionicons name="notifications-outline" size={22} color={primary} />
                  </HeroButton>
                  {notificationBadgeText ? (
                    <View
                      className="absolute right-0 top-0 h-5 min-w-5 items-center justify-center rounded-full border-2 border-background px-1"
                      style={{ backgroundColor: primary }}
                    >
                      <Text className="text-[10px] font-bold leading-3 text-white">
                        {notificationBadgeText}
                      </Text>
                    </View>
                  ) : null}
                </View>
              </HeroCard.Body>
            </HeroCard>
            <View className="mx-4 flex-row flex-wrap gap-2.5">
              {summaryCards.map((card) => (
                <DashboardSummaryCard
                  key={card.key}
                  card={card}
                  primary={primary}
                  theme={theme}
                  accessibilityLabel={t('dashboard.openCard', { label: card.label })}
                  onPress={() => router.push(card.route as never)}
                />
              ))}
            </View>
            <Surface
              variant="default"
              className="mx-4 gap-3 overflow-hidden rounded-panel p-3.5"
              style={{ borderWidth: 1, borderColor: theme.borderSubtle }}
            >
              <View className="flex-row items-center justify-between gap-3">
                <Tabs value={feedMode} onValueChange={(value) => setFeedMode(value as FeedMode)} variant="secondary" className="flex-1">
                  <Tabs.List>
                    <Tabs.Indicator />
                    <Tabs.Trigger value="ranking">
                      <Ionicons name="sparkles-outline" size={15} color={primary} />
                      <Tabs.Label>{t('mode.forYou')}</Tabs.Label>
                    </Tabs.Trigger>
                    <Tabs.Trigger value="recent">
                      <Ionicons name="time-outline" size={15} color={primary} />
                      <Tabs.Label>{t('mode.recent')}</Tabs.Label>
                    </Tabs.Trigger>
                  </Tabs.List>
                </Tabs>
                {filter !== 'all' || subFilter ? (
                  <HeroButton
                    isIconOnly
                    size="sm"
                    variant="ghost"
                    className="h-9 w-9 rounded-2xl"
                    onPress={() => {
                      setFilter('all');
                      setSubFilter(null);
                    }}
                    accessibilityLabel={t('filter.clear')}
                    style={{ backgroundColor: withAlpha(primary, 0.08) }}
                  >
                    <Ionicons name="close-circle-outline" size={20} color={primary} />
                  </HeroButton>
                ) : null}
              </View>

              <ScrollView
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerClassName="gap-2 pr-2"
              >
                {FILTER_OPTIONS.map((option) => (
                  <Chip
                    key={option.key}
                    size="sm"
                    variant={filter === option.key ? 'secondary' : 'soft'}
                    color={filter === option.key ? 'accent' : 'default'}
                    onPress={() => handleFilterChange(option.key)}
                    accessibilityLabel={t(`filter.${option.key}`)}
                  >
                    <Ionicons name={option.icon} size={13} color={filter === option.key ? primary : theme.textSecondary} />
                    <Chip.Label>{t(`filter.${option.key}`)}</Chip.Label>
                  </Chip>
                ))}
              </ScrollView>

              {filter === 'listings' ? (
                <View className="flex-row flex-wrap gap-2">
                  {LISTING_SUBFILTERS.map((option) => (
                    <Chip
                      key={option}
                      size="sm"
                      variant={subFilter === option ? 'primary' : 'soft'}
                      color={subFilter === option ? 'accent' : 'default'}
                      onPress={() => setSubFilter(subFilter === option ? null : option)}
                    >
                      <Chip.Label>{t(`subFilter.${option}`)}</Chip.Label>
                    </Chip>
                  ))}
                </View>
              ) : null}

              <View className="flex-row">
                <Chip size="sm" variant="soft" color="default">
                  <Chip.Label>{t('feed.currentView', { filter: activeFilterLabel })}</Chip.Label>
                </Chip>
              </View>
            </Surface>
            {storyMembers.length > 0 ? <StoryCircles members={storyMembers} onPress={handleStoryPress} /> : null}
            <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
              <HeroCard.Body className="gap-4 p-4">
                <View className="flex-row items-center gap-3">
                  <View className="h-11 w-11 items-center justify-center rounded-2xl" style={{ backgroundColor: primary }}>
                    <Ionicons name="add" size={22} color="#fff" />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="font-semibold leading-5" style={{ color: theme.text }} numberOfLines={2}>
                      {t('composer.title')}
                    </Text>
                    <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                      {t('composer.subtitle')}
                    </Text>
                  </View>
                </View>
                <View className="flex-row flex-wrap gap-2">
                  <ComposerActionPill
                    icon="swap-horizontal-outline"
                    label={t('composer.exchange')}
                    primary={primary}
                    tone="primary"
                    onPress={() => router.push('/(modals)/new-exchange')}
                  />
                  <ComposerActionPill
                    icon="stats-chart-outline"
                    label={t('composer.polls')}
                    primary={primary}
                    tone="secondary"
                    onPress={() => setFilter('polls')}
                  />
                  <ComposerActionPill
                    icon="pricetag-outline"
                    label={t('composer.hashtags')}
                    primary={primary}
                    tone="ghost"
                    onPress={() => router.push('/(modals)/feed-hashtags' as never)}
                  />
                </View>
              </HeroCard.Body>
            </HeroCard>
          </View>
        }
        ListEmptyComponent={
          isLoading ? (
            <>
              <FeedItemSkeleton />
              <FeedItemSkeleton />
              <FeedItemSkeleton />
            </>
          ) : error ? (
            <HeroCard variant="secondary" className="mx-4 my-8">
              <HeroCard.Body className="items-center gap-4">
                <Ionicons name="cloud-offline-outline" size={30} color={primary} />
                <Text className="text-center text-sm leading-5 text-danger">{error}</Text>
                <HeroButton
                  variant="primary"
                  onPress={() => void refresh()}
                  style={{ backgroundColor: primary }}
                >
                  <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>
          ) : (
            <HeroCard variant="secondary" className="mx-4 my-8">
              <HeroCard.Body className="items-center gap-2">
                <Ionicons name="sparkles-outline" size={30} color={primary} />
                <Text className="text-center text-[17px] font-semibold" style={{ color: theme.text }}>{t('feed.emptyTitle')}</Text>
                <Text className="text-center text-sm leading-5" style={{ color: theme.textSecondary }}>{t('feed.emptySubtitle')}</Text>
              </HeroCard.Body>
            </HeroCard>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="items-center py-4">
              <Spinner size="sm" />
            </View>
          ) : !hasMore && items.length > 0 && !isLoading ? (
            <View className="items-center py-4">
              <Text className="text-xs" style={{ color: theme.textSecondary }}>{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={{ paddingBottom: 28 }}
      />

      <FAB icon="add" onPress={() => router.push('/(modals)/new-exchange')} position="bottom-right" />
      <CommentSheet
        visible={Boolean(commentTarget)}
        targetType={commentTarget?.targetType ?? 'post'}
        targetId={commentTarget?.targetId ?? 0}
        initialCount={commentTarget?.initialCount ?? 0}
        strings={{
          title: t('comment'),
          placeholder: t('exchanges:detail.commentPlaceholder'),
          empty: t('exchanges:detail.noComments'),
          loadFailed: t('exchanges:detail.commentsFailed'),
          submitFailed: t('exchanges:detail.commentFailed'),
          actionFailedTitle: t('exchanges:detail.actionFailedTitle'),
          send: t('common:buttons.send'),
          authorFallback: t('common:labels.member'),
        }}
        onClose={() => setCommentTarget(null)}
        onCountChange={handleCommentCountChange}
      />
    </SafeAreaView>
  );
}

function ComposerActionPill({
  icon,
  label,
  primary,
  tone,
  onPress,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  primary: string;
  tone: 'primary' | 'secondary' | 'ghost';
  onPress: () => void;
}) {
  const isPrimary = tone === 'primary';
  return (
    <HeroButton
      className="min-h-10 flex-row items-center justify-center gap-2 rounded-full px-3.5"
      accessibilityLabel={label}
      onPress={onPress}
      size="sm"
      variant={isPrimary ? 'primary' : tone === 'secondary' ? 'secondary' : 'outline'}
      style={{
        backgroundColor: isPrimary ? primary : tone === 'secondary' ? withAlpha(primary, 0.12) : 'transparent',
        borderColor: tone === 'ghost' ? withAlpha(primary, 0.16) : 'transparent',
        borderWidth: tone === 'ghost' ? 1 : 0,
      }}
    >
      <Ionicons name={icon} size={16} color={isPrimary ? '#fff' : primary} />
      <HeroButton.Label className="text-sm font-bold" style={{ color: isPrimary ? '#fff' : primary }} numberOfLines={1}>
        {label}
      </HeroButton.Label>
    </HeroButton>
  );
}

function DashboardSummaryCard({
  card,
  primary,
  theme,
  accessibilityLabel,
  onPress,
}: {
  card: SummaryCardItem;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  accessibilityLabel: string;
  onPress: () => void;
}) {
  return (
    <NativePressable
      className="min-w-[47%] flex-1"
      accessibilityLabel={accessibilityLabel}
      onPress={onPress}
      feedback="highlight"
    >
      <Surface
        variant="secondary"
        className="min-h-[92px] overflow-hidden rounded-panel p-3.5"
        style={{ borderWidth: 1, borderColor: theme.borderSubtle }}
      >
        <View className="absolute bottom-0 left-0 top-0 w-1.5" style={{ backgroundColor: primary }} />
        <View className="gap-2 pl-1">
          <View className="flex-row items-center justify-between gap-2">
            <View
              className="h-9 w-9 items-center justify-center rounded-2xl"
              style={{ backgroundColor: withAlpha(primary, 0.14) }}
            >
              <Ionicons name={card.icon} size={17} color={primary} />
            </View>
            <Ionicons name="chevron-forward" size={15} color={theme.textMuted} />
          </View>
          <Text className="text-xs font-semibold leading-4" style={{ color: theme.textSecondary }} numberOfLines={1}>
            {card.label}
          </Text>
          <Text
            className="text-[17px] font-bold leading-6"
            style={{ color: theme.text }}
            numberOfLines={1}
            adjustsFontSizeToFit
            minimumFontScale={0.78}
          >
            {card.value}
          </Text>
        </View>
      </Surface>
    </NativePressable>
  );
}
