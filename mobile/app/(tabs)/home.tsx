// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState, useMemo } from 'react';
import * as Haptics from 'expo-haptics';
import { FlatList, Pressable, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Spinner } from 'heroui-native';

import * as Sentry from '@sentry/react-native';
import { useTranslation } from 'react-i18next';
import { getFeed, type FeedItem as FeedItemType, type FeedResponse } from '@/lib/api/feed';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import FeedItem from '@/components/FeedItem';
import OfflineBanner from '@/components/OfflineBanner';
import StoryCircles from '@/components/StoryCircles';
import TenantBanner from '@/components/TenantBanner';
import { FeedItemSkeleton } from '@/components/ui/Skeleton';
import FAB from '@/components/ui/FAB';

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

export default function HomeScreen() {
  const { t } = useTranslation('home');
  const { user, displayName } = useAuth();
  const primary = usePrimaryColor();

  const fetchFeed = useCallback((cursor: string | null) => getFeed(1, cursor), []);

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItemType, FeedResponse>(fetchFeed, extractFeedPage);

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

  const storyMembers = useMemo(() => {
    const seen = new Set<number>();
    if (user?.id) seen.add(user.id);
    const members: { id: number; name: string; avatar?: string | null }[] = [];
    for (const item of items) {
      if (item.user_id && !seen.has(item.user_id)) {
        seen.add(item.user_id);
        members.push({ id: item.user_id, name: item.author_name || '', avatar: item.author_avatar ?? null });
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
          <View>
            {storyMembers.length > 0 ? (
              <StoryCircles members={storyMembers} onPress={handleStoryPress} />
            ) : null}
            <View className="flex-row items-start justify-between px-4 pt-4 pb-2">
              <View>
                <Text className="text-xl font-bold text-foreground">
                  {t('feed.greeting', { name: (displayName || '').split(' ')[0] || t('common:labels.friend') })} 👋
                </Text>
                <Text className="text-sm text-muted-foreground mt-0.5">{t('feed.subtitle')}</Text>
              </View>
              <Pressable
                onPress={() => {
                  void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                  router.push('/(modals)/notifications');
                }}
                className="relative p-2.5"
                accessibilityLabel={t('notifications.title')}
                accessibilityRole="button"
              >
                <Ionicons name="notifications-outline" size={24} className="text-foreground" />
                {unreadNotifications > 0 ? (
                  <View
                    className="absolute top-0 right-0 min-w-[16px] h-4 rounded-full items-center justify-center px-0.5"
                    style={{ backgroundColor: primary }}
                  >
                    <Text className="text-white text-[9px] font-bold">
                      {unreadNotifications > 9 ? '9+' : unreadNotifications}
                    </Text>
                  </View>
                ) : null}
              </Pressable>
            </View>
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
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-danger text-sm text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-foreground text-[17px] font-semibold text-center mb-2">{t('feed.emptyTitle')}</Text>
              <Text className="text-muted-foreground text-sm text-center leading-5">{t('feed.emptySubtitle')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center">
              <Spinner size="sm" />
            </View>
          ) : !hasMore && items.length > 0 && !isLoading ? (
            <View className="py-4 items-center">
              <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={{ paddingBottom: 24 }}
      />

      <FAB icon="add" onPress={() => router.push('/(modals)/new-exchange')} position="bottom-right" />
    </SafeAreaView>
  );
}
