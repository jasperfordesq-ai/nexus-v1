// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Tabs } from 'heroui-native';

import * as Sentry from '@sentry/react-native';
import { useTranslation } from 'react-i18next';
import { getFeed, getFeedAuthor, type FeedFilter, type FeedItem as FeedItemType, type FeedMode, type FeedResponse } from '@/lib/api/feed';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import FeedItem from '@/components/FeedItem';
import OfflineBanner from '@/components/OfflineBanner';
import StoryCircles from '@/components/StoryCircles';
import TenantBanner from '@/components/TenantBanner';
import { FeedItemSkeleton } from '@/components/ui/Skeleton';
import FAB from '@/components/ui/FAB';
import * as Haptics from '@/lib/haptics';

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

export default function HomeScreen() {
  const { t } = useTranslation(['home', 'common']);
  const { user, displayName } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [feedMode, setFeedMode] = useState<FeedMode>('ranking');
  const [filter, setFilter] = useState<FeedFilter>('all');
  const [subFilter, setSubFilter] = useState<string | null>(null);

  const fetchFeed = useCallback(
    (cursor: string | null) => getFeed(1, cursor, { filter, mode: feedMode, subtype: subFilter }),
    [feedMode, filter, subFilter],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItemType, FeedResponse>(fetchFeed, extractFeedPage, [feedMode, filter, subFilter]);

  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
  }, [refresh]);

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

  const renderItem = useCallback(({ item }: { item: FeedItemType }) => <FeedItem item={item} />, []);
  const keyExtractor = useCallback((item: FeedItemType) => `${item.type}-${item.id}`, []);
  const activeFilterLabel = t(`filter.${filter}`);

  const handleFilterChange = useCallback((nextFilter: FeedFilter) => {
    setFilter(nextFilter);
    if (nextFilter !== 'listings') {
      setSubFilter(null);
    }
  }, []);

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
          <View className="gap-3 pb-2">
            <HeroCard variant="default" className="mx-4 mt-4">
              <HeroCard.Body className="flex-row items-start justify-between gap-4">
                <View className="min-w-0 flex-1">
                  <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                    {t('feed.greeting', { name: (displayName || '').split(' ')[0] || t('common:labels.friend') })}
                  </Text>
                  <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>{t('feed.subtitle')}</Text>
                </View>
                <View className="relative h-12 w-12 items-center justify-center">
                  <HeroButton
                    isIconOnly
                    size="md"
                    variant="tertiary"
                    className="h-12 w-12 rounded-full"
                    onPress={() => {
                      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                      router.push('/(modals)/notifications');
                    }}
                    accessibilityLabel={t('notifications.title')}
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
            <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
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
                    onPress={() => {
                      setFilter('all');
                      setSubFilter(null);
                    }}
                    accessibilityLabel={t('filter.clear')}
                  >
                    <Ionicons name="close-circle-outline" size={20} color={primary} />
                  </HeroButton>
                ) : null}
              </View>

              <View className="flex-row flex-wrap gap-2">
                {FILTER_OPTIONS.map((option) => (
                  <HeroButton
                    key={option.key}
                    size="sm"
                    variant={filter === option.key ? 'secondary' : 'ghost'}
                    onPress={() => handleFilterChange(option.key)}
                    accessibilityLabel={t(`filter.${option.key}`)}
                  >
                    <Ionicons name={option.icon} size={15} color={filter === option.key ? primary : undefined} />
                    <HeroButton.Label>{t(`filter.${option.key}`)}</HeroButton.Label>
                  </HeroButton>
                ))}
              </View>

              {filter === 'listings' ? (
                <View className="flex-row gap-2">
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

              <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
                {t('feed.currentView', { filter: activeFilterLabel })}
              </Text>
            </Surface>
            {storyMembers.length > 0 ? <StoryCircles members={storyMembers} onPress={handleStoryPress} /> : null}
            <HeroCard variant="default" className="mx-4">
              <HeroCard.Body className="gap-3">
                <View className="flex-row items-center gap-3">
                  <View className="h-10 w-10 items-center justify-center rounded-full" style={{ backgroundColor: primary }}>
                    <Ionicons name="add" size={22} color="#fff" />
                  </View>
                  <View className="min-w-0 flex-1">
                    <Text className="font-semibold" style={{ color: theme.text }}>{t('composer.title')}</Text>
                    <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                      {t('composer.subtitle')}
                    </Text>
                  </View>
                </View>
                <View className="flex-row gap-2">
                  <HeroButton size="sm" variant="primary" onPress={() => router.push('/(modals)/new-exchange')} style={{ backgroundColor: primary }}>
                    <Ionicons name="swap-horizontal-outline" size={16} color="#fff" />
                    <HeroButton.Label>{t('composer.exchange')}</HeroButton.Label>
                  </HeroButton>
                  <HeroButton size="sm" variant="secondary" onPress={() => setFilter('polls')}>
                    <Ionicons name="stats-chart-outline" size={16} color={primary} />
                    <HeroButton.Label>{t('composer.polls')}</HeroButton.Label>
                  </HeroButton>
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
                <HeroButton variant="primary" onPress={() => void refresh()} style={{ backgroundColor: primary }}>
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
    </SafeAreaView>
  );
}
